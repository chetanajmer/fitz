<?php

namespace App\Services;

use App\Jobs\GenerateAiReport;
use App\Models\AiReport;
use App\Models\Attendance;
use App\Models\GymAiSetting;
use App\Models\Member;
use App\Models\MemberMeasurement;
use App\Models\ProgressPhoto;
use App\Models\User;
use App\Services\Ai\OpenRouterClient;
use App\Services\Push\PushMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * AI progress reports and photo comparisons. A request reserves credits (a pending report) under a
 * lock and queues the generation; a failed generation releases them. Runs inside the gym tenant.
 */
class AiReportService
{
    protected const SYSTEM_PROMPT = <<<'TXT'
        You are a friendly, encouraging fitness coach inside an Indian gym's member app.
        You write a short progress report for one gym member from the data you are given (JSON).
        Rules:
        - Speak to the member as "you". Simple English, short sentences. Use kg, cm and Indian context.
        - Base every statement on the data. If data is missing or too little, say what to log next instead of guessing.
        - The member reads this on a phone, so be brief: summary is at most 2 short sentences, every other line one short sentence of at most 15 words. Never say the same thing twice.
        - wins: what is working, at most 3. focus_areas: 2 or 3 specific, practical suggestions (title of at most 4 words) about training consistency, weigh-ins, measurements and general healthy habits.
        - confidence: "low" when there is little data.
        - Coach training and habits only; never comment on account details such as the joining date.
        - Never diagnose, never mention diseases or medication, never prescribe calorie numbers or supplements.
        - Be body-positive. No shaming language.
        - Never call the member overweight, obese, fat or similar, and do not mention BMI.
        - Plain text in every field, no Markdown. Keep it under 150 words in total.
        TXT;

    protected const PHOTO_PROMPT = <<<'TXT'
        You are a friendly, encouraging fitness coach inside an Indian gym's member app.
        You compare two progress photos of the same gym member (first = before, second = after) together with their data (JSON).
        Rules:
        - Speak to the member as "you". Simple English, short sentences.
        - Describe only visible, fitness-related changes (posture, muscle definition, waist/shape). If the photos differ in pose, light or angle, say so briefly.
        - Never comment on attractiveness, skin, identity or anything not related to fitness. Never diagnose.
        - Be body-positive. No shaming language.
        - Never call the member overweight, obese, fat or similar, and do not mention BMI.
        - The member reads this on a phone, so be brief: summary is at most 2 short sentences, every other line one short sentence of at most 15 words. Never say the same thing twice.
        - Put readings (kg, cm, %) only in numbers; the app shows them separately. Use none in summary, changes or motivation.
        - changes: each visible change with the body area, at most 4. Leave it empty when nothing clear is visible.
        - change_level: judge the body shape you can see (waist, arms, chest, legs, muscle definition). "none" when the two photos look the same, "slight", "moderate" or "strong" for real differences.
          Use "unclear" only when a person's body is missing, hidden or cut off in a photo, or the poses are so different that shape cannot be judged. Different light, muscle pump, background or framing lower photo_quality and confidence, not change_level.
        - Progress photos in shorts, sportswear or without a shirt are normal. If a photo shows nudity or is not of a person, use "unclear" and photo_quality "poor".
        - Both photos are of this member, months apart; a large change is possible. Ignore the face and never judge whether it is the same person.
        - Never guess weight or body fat from a photo.
        - numbers: what the readings in the data say. If there are no readings, say that none were logged around these photos.
        - photo_quality and photo_tips: how comparable the photos are and how to take the next ones, at most 3 tips.
        - Plain text in every field, no Markdown. Keep it under 150 words in total.
        TXT;

    public function __construct(
        protected AiAccessService $access,
        protected OpenRouterClient $client,
        protected BodyProgressService $body,
        protected WeightTrackingService $weights,
        protected StreakService $streaks,
        protected ProgramService $programs,
        protected PushNotificationService $notifications,
    ) {}

    /**
     * Reserve credits and queue a report. $requestedBy is "member", "staff" (with $staff) or "auto".
     */
    public function request(Member $member, string $type, string $requestedBy = 'member', ?User $staff = null, ?int $beforePhotoId = null, ?int $afterPhotoId = null): AiReport
    {
        [$before, $after] = $type === AiReport::PHOTO_COMPARISON ? $this->comparisonPhotos($member, $beforePhotoId, $afterPhotoId) : [null, null];

        return DB::transaction(function () use ($member, $type, $requestedBy, $staff, $before, $after) {
            // Lock the gym's AI settings row: concurrent requests are checked one at a time.
            $settings = GymAiSetting::forGym($member->gym_id)->lockForUpdate()->first() ?? $this->access->defaultsFor($member->gym);
            $this->access->authorize($member, $type, $settings);

            $report = new AiReport;
            $report->forceFill([
                'member_id' => $member->id,
                'type' => $type,
                'status' => AiReport::PENDING,
                'credits' => $this->access->cost($type),
                'before_photo_id' => $before?->id,
                'after_photo_id' => $after?->id,
                'requested_by' => $requestedBy,
                'requested_by_user' => $staff?->id,
            ])->save();

            GenerateAiReport::dispatch($report->id, (int) $member->gym_id)->afterCommit();

            return $report;
        });
    }

    /** Call the AI model and store the result. Failures release the credits. */
    public function generate(AiReport $report): void
    {
        if ($report->status !== AiReport::PENDING) {
            return;
        }

        $member = $report->member;

        // Re-check right before any data leaves: consent or access may have changed while the job waited.
        if (! $this->access->maySend($member, $report->type)) {
            $report->forceFill(['status' => AiReport::FAILED, 'error' => 'AI coach is no longer available for this member.'])->save();

            return;
        }

        try {
            $result = $report->type === AiReport::PHOTO_COMPARISON
                ? $this->client->complete(self::PHOTO_PROMPT, [
                    ['type' => 'text', 'text' => json_encode($this->comparisonData($member, $report->beforePhoto, $report->afterPhoto), JSON_UNESCAPED_UNICODE)],
                    ['type' => 'image_url', 'image_url' => ['url' => $this->body->photoDataUrl($report->beforePhoto)]],
                    ['type' => 'image_url', 'image_url' => ['url' => $this->body->photoDataUrl($report->afterPhoto)]],
                ], 'photo_comparison', self::schema($report->type))
                : $this->client->complete(self::SYSTEM_PROMPT, json_encode($this->progressData($member), JSON_UNESCAPED_UNICODE), 'progress_report', self::schema($report->type));
        } catch (Throwable $e) {
            Log::warning('AI report failed', ['report' => $report->id, 'error' => $e->getMessage()]);
            $report->forceFill(['status' => AiReport::FAILED, 'error' => mb_substr($e->getMessage(), 0, 255)])->save();

            return;
        }

        $report->forceFill([
            'status' => AiReport::COMPLETED,
            'result' => $result['data'],
            'content' => self::markdown($report->type, $result['data']),
            'model' => $result['model'],
            'input_tokens' => $result['input_tokens'],
            'output_tokens' => $result['output_tokens'],
            'cost_usd' => $result['cost_usd'],
            'completed_at' => now(),
        ])->save();

        $this->notifications->notify($member, PushMessage::make(
            'ai_report_ready',
            'Your AI '.($report->type === AiReport::PHOTO_COMPARISON ? 'photo comparison' : 'progress report').' is ready',
            'Open the app to read what is working and what to focus on next.',
            ['report_id' => $report->id],
        ));
    }

    /**
     * The member's data sent to the AI model. Never includes name, phone, medical or staff notes.
     *
     * @return array<string, mixed>
     */
    public function progressData(Member $member): array
    {
        $today = Carbon::today();
        $weight = $this->weights->summary($member, 40);
        $streak = $this->streaks->summary($member);
        $programs = $this->programs->overview($member);
        $visits = Attendance::where('member_id', $member->id)->whereDate('check_in_at', '>=', $today->copy()->subWeeks(8)->startOfWeek())
            ->pluck('check_in_at')->countBy(fn (Carbon $at) => $at->copy()->startOfWeek()->toDateString());

        return [
            'today' => $today->toDateString(),
            'member' => [
                'gender' => $member->gender,
                'age' => $member->date_of_birth?->age,
                'height_cm' => $weight['height_cm'],
                'fitness_goal' => $member->fitness_goal,
                'member_since' => $member->joining_date?->toDateString(),
            ],
            'weight' => [
                'current_kg' => $weight['current_weight_kg'],
                'change_since_start_kg' => $weight['change_since_start_kg'],
                'goal' => $weight['goal'] ? array_intersect_key($weight['goal'], array_flip(['direction', 'start_weight_kg', 'target_weight_kg', 'target_date', 'progress_percent', 'remaining_kg', 'status'])) : null,
                'readings' => collect($weight['history'])->map(fn ($h) => [$h['measured_on'], $h['weight_kg']])->all(),
            ],
            'measurements' => MemberMeasurement::where('member_id', $member->id)->orderByDesc('measured_on')->limit(10)->get()->sortBy('measured_on')
                ->map(fn (MemberMeasurement $m) => ['date' => $m->measured_on->toDateString()] + array_filter($m->only(array_keys(MemberMeasurement::FIELDS)), fn ($v) => $v !== null))
                ->values()->all(),
            'attendance' => [
                'visits_per_week_last_8_weeks' => $visits->all(),
                'current_streak_days' => $streak['current_streak'],
                'longest_streak_days' => $streak['longest_streak'],
                'visits_this_month' => $streak['visits_this_month'],
            ],
            'programs' => collect(['workout', 'diet'])->mapWithKeys(fn ($type) => [$type => $programs[$type] ? [
                'title' => $programs[$type]['program']['title'],
                'goal' => $programs[$type]['program']['goal_label'],
                'progress_percent' => $programs[$type]['progress_percent'],
                'adherence' => $programs[$type]['adherence'],
            ] : null])->all(),
        ];
    }

    /** @return array<string, mixed> the JSON schema of the AI answer */
    protected static function schema(string $type): array
    {
        $text = ['type' => 'string'];
        $level = ['type' => 'string', 'enum' => ['low', 'medium', 'high']];
        $object = fn (array $properties) => ['type' => 'object', 'properties' => $properties, 'required' => array_keys($properties), 'additionalProperties' => false];
        $list = fn (array $items) => ['type' => 'array', 'items' => $items];

        return $object(['summary' => $text] + ($type === AiReport::PHOTO_COMPARISON ? [
            'change_level' => ['type' => 'string', 'enum' => array_keys(AiReport::CHANGE_LEVELS)],
            'changes' => $list($object(['area' => $text, 'observation' => $text])),
            'numbers' => $text,
            'photo_quality' => ['type' => 'string', 'enum' => ['good', 'fair', 'poor']],
            'photo_tips' => $list($text),
        ] : [
            'wins' => $list($text),
            'focus_areas' => $list($object(['title' => $text, 'tip' => $text])),
        ]) + ['motivation' => $text, 'confidence' => $level]);
    }

    /** The same answer as Markdown (the "content" older app versions show). @param  array<string, mixed>  $r */
    protected static function markdown(string $type, array $r): string
    {
        $bullets = fn (array $lines) => implode("\n", array_map(fn ($line) => '- '.$line, $lines));
        $sections = $type === AiReport::PHOTO_COMPARISON ? [
            'Visible changes' => trim($r['summary']."\n\n".$bullets(array_map(fn ($c) => "**{$c['area']}:** {$c['observation']}", $r['changes']))),
            'What the numbers say' => $r['numbers'],
            'Tips for your next photo' => $bullets($r['photo_tips']),
        ] : [
            'Summary' => $r['summary'],
            "What's working" => $bullets($r['wins']),
            'What to focus on next' => $bullets(array_map(fn ($f) => "**{$f['title']}:** {$f['tip']}", $r['focus_areas'])),
        ];

        return collect($sections + ['Motivation' => $r['motivation']])->filter()->map(fn ($body, $heading) => "## {$heading}\n\n{$body}")->implode("\n\n");
    }

    /** Reports whose job never finished must not keep credits reserved. Returns how many were released. */
    public function releaseStuck(): int
    {
        return AiReport::query()->where('status', AiReport::PENDING)->where('created_at', '<', now()->subHours(2))
            ->update(['status' => AiReport::FAILED, 'error' => 'The report was not processed in time.', 'updated_at' => now()]);
    }

    /**
     * The data sent with a photo comparison: only readings from around the two photos, so the AI
     * cannot mix in progress made at other times.
     *
     * @return array<string, mixed>
     */
    public function comparisonData(Member $member, ProgressPhoto $before, ProgressPhoto $after): array
    {
        $data = $this->progressData($member);
        $from = $before->taken_on->copy()->subWeeks(2)->toDateString();
        $to = $after->taken_on->copy()->addWeeks(2)->toDateString();

        return [
            'before_photo_taken_on' => $before->taken_on->toDateString(),
            'after_photo_taken_on' => $after->taken_on->toDateString(),
            'member' => $data['member'],
            'weight_readings_kg' => array_values(array_filter($data['weight']['readings'], fn ($r) => $r[0] >= $from && $r[0] <= $to)),
            'measurements' => array_values(array_filter($data['measurements'], fn ($r) => $r['date'] >= $from && $r['date'] <= $to)),
        ];
    }

    /** @return array{0: ProgressPhoto, 1: ProgressPhoto} */
    protected function comparisonPhotos(Member $member, ?int $beforeId, ?int $afterId): array
    {
        $photos = ProgressPhoto::where('member_id', $member->id)->whereIn('id', array_filter([$beforeId, $afterId]))->get()->keyBy('id');
        $before = $photos->get((int) $beforeId);
        $after = $photos->get((int) $afterId);

        if (! $before || ! $after || $before->id === $after->id) {
            throw ValidationException::withMessages(['after_photo_id' => 'Choose two different progress photos.']);
        }

        // Always compare older → newer.
        return $before->taken_on->gt($after->taken_on) ? [$after, $before] : [$before, $after];
    }

    /** @return array<string, mixed> */
    public function toArray(AiReport $report): array
    {
        return [
            'id' => $report->id,
            'type' => $report->type,
            'type_label' => AiReport::TYPES[$report->type] ?? $report->type,
            'status' => $report->status,
            'credits' => $report->credits,
            'content' => $report->content,
            'result' => $report->result,
            'before_photo_id' => $report->before_photo_id,
            'after_photo_id' => $report->after_photo_id,
            'requested_by' => $report->requested_by,
            'created_at' => $report->created_at->toIso8601String(),
            'completed_at' => $report->completed_at?->toIso8601String(),
            'disclaimer' => AiReport::DISCLAIMER,
        ];
    }
}
