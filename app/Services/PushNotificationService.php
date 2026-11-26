<?php

namespace App\Services;

use App\Enums\FitnessPlanType;
use App\Enums\MembershipStatus;
use App\Enums\MemberStatus;
use App\Jobs\SendAnnouncement;
use App\Jobs\SendPushNotification;
use App\Models\Announcement;
use App\Models\Gym;
use App\Models\Member;
use App\Models\MemberDevice;
use App\Models\MemberNotification;
use App\Models\MemberProgram;
use App\Models\Membership;
use App\Models\MemberStreak;
use App\Models\Payment;
use App\Models\Scopes\GymScope;
use App\Models\User;
use App\Models\WeightGoal;
use App\Services\Push\FcmClient;
use App\Services\Push\PushMessage;
use App\Support\NotificationCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Member push notifications: phone registration, the in-app notification list, delivery through
 * Firebase, custom gym notifications (approved by the Super Admin) and the automatic pushes, whose
 * wording, timing and rules come from NotificationSettings. Runs inside the gym tenant context.
 */
class PushNotificationService
{
    /** Categories a member may switch off in the app (see PushMessage::TYPES). */
    public const OPTIONAL_CATEGORIES = [
        'reminders' => 'Workout, weigh-in and streak reminders',
        'announcements' => 'News and offers from the gym',
    ];

    public function __construct(protected NotificationSettings $config) {}

    // ---- Sending ----------------------------------------------------------------------------

    /**
     * Save a notification to the member's list and push it to their phones (queued).
     * During quiet hours the push waits until they end; the notification is in the list right away.
     */
    public function notify(Member $member, PushMessage $message, ?Announcement $announcement = null): MemberNotification
    {
        $notification = new MemberNotification;
        $notification->forceFill([
            'member_id' => $member->id,
            'announcement_id' => $announcement?->id,
            'type' => $message->type,
            'title' => $message->title,
            'body' => $message->body,
            'image_url' => $message->imageUrl,
            'data' => $message->payload(),
        ])->save();

        if ($this->wantsPush($member, $message) && $member->devices()->exists()) {
            $job = SendPushNotification::dispatch($notification->id, (int) $member->gym_id)->afterCommit();
            if ($until = $this->config->quietUntil()) {
                $job->delay($until);
            }
        }

        return $notification;
    }

    /**
     * An automatic push that must go out only once per member + type + ref (e.g. a membership and
     * the number of days left). Returns false when it was already sent.
     */
    public function notifyOnce(Member $member, string $ref, PushMessage $message): bool
    {
        $claimed = DB::table('member_push_logs')->insertOrIgnore([
            'gym_id' => $member->gym_id,
            'member_id' => $member->id,
            'type' => $message->type,
            'ref' => mb_substr($ref, 0, 100),
            'sent_at' => now(),
        ]);

        if (! $claimed) {
            return false;
        }

        $this->notify($member, $message);

        return true;
    }

    /** Push a stored notification to every phone of the member. Removes tokens Firebase no longer accepts. */
    public function push(MemberNotification $notification, FcmClient $fcm): bool
    {
        $data = $notification->data ?? [];
        $sent = 0;

        foreach (MemberDevice::where('member_id', $notification->member_id)->get() as $device) {
            $result = $fcm->send(
                $device->token,
                $notification->title,
                $notification->body,
                ['notification_id' => $notification->id, 'gym_id' => $notification->gym_id] + $data,
                $data['channel'] ?? 'general',
                $notification->image_url ?? ($data['image_url'] ?? null),
            );

            if ($result === FcmClient::SENT) {
                $sent++;
            } elseif ($result === FcmClient::INVALID_TOKEN) {
                $device->delete();
            }
        }

        if ($sent) {
            $notification->forceFill(['pushed_at' => now()])->save();
        }

        if ($notification->announcement_id) {
            Announcement::whereKey($notification->announcement_id)->increment($sent ? 'pushed_count' : 'failed_count');
        }

        return $sent > 0;
    }

    public function wantsPush(Member $member, PushMessage|string $message): bool
    {
        $category = $message instanceof PushMessage ? $message->category() : (PushMessage::TYPES[$message][0] ?? 'account');

        return $category === 'account' || ($this->settings($member)[$category] ?? true);
    }

    /**
     * An automatic notification for a member, worded from its template.
     *
     * @param  array<string, scalar|null>  $values  values for the template placeholders
     * @param  array<string, scalar|null>  $data  extra data for the app
     */
    public function message(string $type, Member $member, array $values = [], array $data = [], ?string $screen = null): PushMessage
    {
        [$title, $body] = $this->config->render($type, $values + $this->memberValues($member));

        return PushMessage::make($type, $title, $body, $data, $screen);
    }

    // ---- Member settings, phones and inbox ----------------------------------------------------

    /** @return array<string, bool> */
    public function settings(Member $member): array
    {
        $saved = $member->notification_settings ?? [];

        return collect(self::OPTIONAL_CATEGORIES)->mapWithKeys(fn ($label, $key) => [$key => (bool) ($saved[$key] ?? true)])->all();
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, bool>
     */
    public function updateSettings(Member $member, array $values): array
    {
        $settings = $this->settings($member);

        foreach (array_keys(self::OPTIONAL_CATEGORIES) as $key) {
            if (array_key_exists($key, $values)) {
                $settings[$key] = (bool) $values[$key];
            }
        }

        $member->forceFill(['notification_settings' => $settings])->save();

        return $settings;
    }

    /** Save the phone's Firebase token (called on every app launch). A shared phone moves to the member who signed in last. */
    public function registerDevice(Member $member, string $token, string $platform, ?string $appVersion = null, ?string $deviceName = null): MemberDevice
    {
        return DB::transaction(function () use ($member, $token, $platform, $appVersion, $deviceName) {
            // The same phone may have been used by another member (of any gym) before.
            MemberDevice::withoutGlobalScope(GymScope::class)->where('token', $token)->where('member_id', '!=', $member->id)->delete();

            $device = MemberDevice::where('member_id', $member->id)->where('token', $token)->first() ?? new MemberDevice;
            $device->forceFill([
                'member_id' => $member->id,
                'token' => $token,
                'platform' => $platform,
                'app_version' => $appVersion,
                'device_name' => $deviceName ? mb_substr($deviceName, 0, 100) : null,
                'last_seen_at' => now(),
            ])->save();

            return $device;
        });
    }

    /** Remove a token, only if it belongs to this member. */
    public function removeDevice(Member $member, string $token): void
    {
        MemberDevice::where('member_id', $member->id)->where('token', $token)->delete();
    }

    public function unreadCount(Member $member): int
    {
        return MemberNotification::where('member_id', $member->id)->whereNull('read_at')->count();
    }

    public function markRead(Member $member, ?int $notificationId = null): int
    {
        return MemberNotification::where('member_id', $member->id)->whereNull('read_at')
            ->when($notificationId, fn ($q) => $q->whereKey($notificationId))
            ->update(['read_at' => now()]);
    }

    // ---- Custom notifications, written and sent by the gym -------------------------------------

    /**
     * A gym writes a custom notification for its own members. It is ready to send straight away
     * (status approved); no one outside the gym reviews it. Trainers without "see all members" can only reach their own members.
     * Only {member_name}, {first_name} and {gym_name} are filled in per member.
     *
     * @param  array{title: string, body: string, audience: string, trainer_id?: ?int, image_path?: ?string}  $data
     */
    public function submit(User $by, array $data): Announcement
    {
        $audience = $data['audience'];
        $trainerId = $audience === 'trainer' ? (int) ($data['trainer_id'] ?? 0) : null;

        if (! $by->can('members.all')) {
            [$audience, $trainerId] = ['trainer', $by->id];
        }

        if (! array_key_exists($audience, Announcement::AUDIENCES)) {
            throw ValidationException::withMessages(['audience' => 'Choose who should receive this.']);
        }

        if ($audience === 'trainer' && ! User::trainersOf((int) current_gym()?->id)->whereKey($trainerId)->exists()) {
            throw ValidationException::withMessages(['trainerId' => 'Choose a trainer.']);
        }

        foreach (['title' => $data['title'], 'body' => $data['body']] as $field => $text) {
            if ($unknown = array_diff($this->placeholdersIn($text), array_keys(NotificationCatalog::COMMON_PLACEHOLDERS))) {
                throw ValidationException::withMessages([$field => 'Only {member_name}, {first_name} and {gym_name} can be used, not {'.implode('}, {', $unknown).'}.']);
            }
        }

        if (Announcement::where('created_at', '>=', Carbon::today())->count() >= NotificationSettings::DAILY_SUBMISSION_LIMIT) {
            throw ValidationException::withMessages(['title' => 'You can submit up to '.NotificationSettings::DAILY_SUBMISSION_LIMIT.' notifications a day.']);
        }

        $announcement = new Announcement;
        $announcement->forceFill([
            'created_by' => $by->id,
            'title' => trim($data['title']),
            'body' => trim($data['body']),
            'image_path' => $data['image_path'] ?? null,
            'audience' => $audience,
            'trainer_id' => $trainerId,
            'status' => Announcement::APPROVED,
        ]);

        if (! $this->audienceQuery($announcement)->exists()) {
            throw ValidationException::withMessages(['audience' => 'No members match this audience.']);
        }

        $announcement->save();

        return $announcement;
    }

    /** Super Admin: approve a submitted notification so the gym can send it. */
    public function approve(Announcement $announcement, User $reviewer): void
    {
        $this->assertPending($announcement);
        $announcement->forceFill(['status' => Announcement::APPROVED, 'reviewed_by' => $reviewer->id, 'reviewed_at' => now(), 'rejection_reason' => null])->save();
    }

    /** Super Admin: reject a submitted notification, with the reason shown to the gym. */
    public function reject(Announcement $announcement, User $reviewer, string $reason): void
    {
        $this->assertPending($announcement);

        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => 'Tell the gym why it was not approved.']);
        }

        $announcement->forceFill(['status' => Announcement::REJECTED, 'reviewed_by' => $reviewer->id, 'reviewed_at' => now(), 'rejection_reason' => mb_substr(trim($reason), 0, 500)])->save();
    }

    /**
     * The gym sends a ready notification now or at a later time (outside quiet hours).
     * "Now" during quiet hours is moved to the end of quiet hours.
     */
    public function send(Announcement $announcement, ?string $at = null): Announcement
    {
        if ($announcement->status !== Announcement::APPROVED) {
            throw ValidationException::withMessages(['announcement' => 'Only approved notifications can be sent.']);
        }

        $when = $at ? Carbon::parse($at) : now();

        // The picker has no seconds: a time in the current minute means "now".
        if ($at && $when->lt(now()) && $when->gte(now()->startOfMinute())) {
            $when = now();
        }

        if ($at && $when->lt(now()->startOfMinute())) {
            throw ValidationException::withMessages(['scheduledAt' => 'That time has already passed ('.$when->format('d M, h:i A').'). Choose a later time, or use "Send now".']);
        }

        if ($at && $when->gt(now()->addDays(60))) {
            throw ValidationException::withMessages(['scheduledAt' => 'Choose a time within the next 60 days.']);
        }

        if ($at && $this->config->isQuietAt($when)) {
            $quiet = $this->config->quietHours();
            throw ValidationException::withMessages(['scheduledAt' => 'Choose a time outside quiet hours ('.$quiet['start'].' – '.$quiet['end'].').']);
        }

        $when = $this->config->quietUntil($when) ?? $when;
        $limit = $this->config->dailyAnnouncementLimit();
        $sameDay = Announcement::whereIn('status', [Announcement::SCHEDULED, Announcement::SENDING, Announcement::SENT])
            ->whereDate('scheduled_at', $when->toDateString())->count();

        if ($sameDay >= $limit) {
            throw ValidationException::withMessages(['scheduledAt' => 'You can send up to '.$limit.' notifications a day. Choose another day.']);
        }

        $announcement->forceFill(['status' => Announcement::SCHEDULED, 'scheduled_at' => $when])->save();

        if ($when->lte(now())) {
            SendAnnouncement::dispatch($announcement->id, (int) $announcement->gym_id)->afterCommit();
        }

        return $announcement;
    }

    /** Members a notification goes to (only active members of the current gym). */
    public function audienceQuery(Announcement $announcement): Builder
    {
        $week = Carbon::today()->addDays(7)->toDateString();
        $query = Member::query()->active();

        return match ($announcement->audience) {
            'active' => $query->withValidMembership(),
            'expiring' => $query
                ->whereHas('memberships', fn ($q) => $q->valid()->whereDate('end_date', '<=', $week))
                ->whereDoesntHave('memberships', fn ($q) => $q->where('status', '!=', MembershipStatus::Cancelled->value)->whereDate('end_date', '>', $week)),
            'expired' => $query->expired(),
            'trainer' => $query->where('trainer_id', $announcement->trainer_id),
            default => $query,
        };
    }

    /** Deliver a due notification once (safe to call twice: only the first call sends). */
    public function deliver(Announcement $announcement): int
    {
        $claimed = Announcement::whereKey($announcement->id)->where('status', Announcement::SCHEDULED)->where('scheduled_at', '<=', now())
            ->update(['status' => Announcement::SENDING]);

        if (! $claimed) {
            return 0;
        }

        $announcement->refresh();
        $count = 0;

        $this->audienceQuery($announcement)->select('members.*')->chunkById(500, function ($members) use ($announcement, &$count) {
            foreach ($members as $member) {
                $values = $this->memberValues($member);
                $this->notify($member, PushMessage::make('announcement',
                    NotificationCatalog::render($announcement->title, $values),
                    NotificationCatalog::render($announcement->body, $values),
                    ['announcement_id' => $announcement->id], null, $announcement->imageUrl()), $announcement);
                $count++;
            }
        }, 'members.id', 'id');

        $announcement->forceFill(['status' => Announcement::SENT, 'sent_at' => now(), 'recipients_count' => $count])->save();

        return $count;
    }

    /** Cancel a scheduled send (it goes back to approved), or withdraw a notification that was not sent. */
    public function cancel(Announcement $announcement): void
    {
        $status = match ($announcement->status) {
            Announcement::SCHEDULED => Announcement::APPROVED,
            Announcement::PENDING, Announcement::APPROVED, Announcement::REJECTED => Announcement::CANCELLED,
            default => throw ValidationException::withMessages(['announcement' => 'This notification was already sent.']),
        };

        $announcement->forceFill(['status' => $status, 'scheduled_at' => null])->save();
    }

    // ---- Event pushes (called from existing services) -------------------------------------------

    public function paymentReceived(Payment $payment): void
    {
        $this->safely(function () use ($payment) {
            $member = $payment->member;
            if (! $member || ! $this->enabled($member->gym, 'payment_received')) {
                return;
            }

            $this->notify($member, $this->message('payment_received', $member, [
                'amount' => money($payment->amount),
                'receipt' => $payment->receipt_number,
                'plan' => $payment->membership?->plan_name,
            ], ['payment_id' => $payment->id]));
        });
    }

    public function programAssigned(MemberProgram $assignment): void
    {
        $this->safely(function () use ($assignment) {
            $member = $assignment->member;
            if (! $member || ! $this->enabled($member->gym, 'plan_assigned')) {
                return;
            }

            $kind = $assignment->type === FitnessPlanType::Diet ? 'diet' : 'workout';
            $this->notify($member, $this->message('plan_assigned', $member, [
                'plan_type' => $kind,
                'program' => $assignment->program?->title,
                'start_text' => $assignment->start_date->isToday() ? 'starts today' : 'starts '.$assignment->start_date->format('D, d M'),
            ], ['program_type' => $kind, 'assignment_id' => $assignment->id], $kind));
        });
    }

    public function goalAchieved(Member $member, WeightGoal $goal): void
    {
        $this->safely(function () use ($member, $goal) {
            if (! $this->enabled($member->gym, 'goal_achieved')) {
                return;
            }

            $kg = rtrim(rtrim(number_format((float) $goal->target_weight_kg, 1, '.', ''), '0'), '.');
            $this->notify($member, $this->message('goal_achieved', $member, ['weight' => $kg.' kg'], ['goal_id' => $goal->id]));
        });
    }

    // ---- Scheduled pushes (one gym at a time, see push:run) -------------------------------------

    /** Scheduled notifications due now for a gym (their time has come and they did not run yet today). @return list<string> */
    public function dueForGym(Gym $gym, ?Carbon $now = null): array
    {
        $now ??= now();
        $due = [];

        foreach (NotificationCatalog::scheduledKeys() as $key) {
            $setting = $this->config->forGym($gym, $key);

            if (! $setting['enabled'] || ! $setting['time'] || $now->format('H:i') < $setting['time']) {
                continue;
            }

            if (isset($setting['params']['weekday']) && (int) $now->isoWeekday() !== (int) $setting['params']['weekday']) {
                continue;
            }

            $claimed = DB::table('gym_push_runs')->insertOrIgnore([
                'gym_id' => $gym->id, 'type' => $key, 'run_on' => $now->toDateString(), 'created_at' => now(),
            ]);

            if ($claimed) {
                $due[] = $key;
            }
        }

        return $due;
    }

    /** Run one scheduled notification type for a gym. */
    public function runScheduled(Gym $gym, string $key): int
    {
        return match ($key) {
            'membership_expiring' => $this->membershipExpiring($gym),
            'membership_expired' => $this->membershipExpired($gym),
            'balance_due' => $this->balanceDue($gym),
            'workout_reminder' => $this->workoutReminder($gym),
            'weigh_in_due' => $this->weighInDue($gym),
            'streak_at_risk' => $this->streakAtRisk($gym),
            default => 0,
        };
    }

    /** Active memberships ending in the configured number of days, not renewed. */
    public function membershipExpiring(Gym $gym): int
    {
        $setting = $this->config->forGym($gym, 'membership_expiring');
        if (! $setting['enabled']) {
            return 0;
        }

        $sent = 0;
        foreach ($setting['params']['days'] as $days) {
            $memberships = Membership::with('member')->valid()->whereDate('end_date', Carbon::today()->addDays($days)->toDateString())->get();
            foreach ($memberships as $membership) {
                if (! $this->isActiveMember($membership->member) || $this->hasRenewal($membership)) {
                    continue;
                }
                $sent += (int) $this->notifyOnce($membership->member, 'membership:'.$membership->id.':'.$days, $this->message('membership_expiring', $membership->member, [
                    'days' => $days,
                    'days_text' => $days === 1 ? 'tomorrow' : 'in '.$days.' days',
                    'plan' => $membership->plan_name,
                    'end_date' => $membership->end_date->format('d M'),
                ], ['membership_id' => $membership->id, 'days_left' => $days]));
            }
        }

        return $sent;
    }

    /** Memberships that ended yesterday and were not renewed. */
    public function membershipExpired(Gym $gym): int
    {
        if (! $this->enabled($gym, 'membership_expired')) {
            return 0;
        }

        $sent = 0;
        $ended = Membership::with('member')->where('status', '!=', MembershipStatus::Cancelled->value)
            ->whereDate('end_date', Carbon::yesterday()->toDateString())->get();
        foreach ($ended as $membership) {
            if (! $this->isActiveMember($membership->member) || $this->hasRenewal($membership)) {
                continue;
            }
            $sent += (int) $this->notifyOnce($membership->member, 'membership:'.$membership->id, $this->message('membership_expired', $membership->member, [
                'plan' => $membership->plan_name,
                'end_date' => $membership->end_date->format('d M'),
            ], ['membership_id' => $membership->id]));
        }

        return $sent;
    }

    /** Weigh-in due (once per due date; members who never weighed in are asked once). */
    public function weighInDue(Gym $gym): int
    {
        if (! $this->enabled($gym, 'weigh_in_due')) {
            return 0;
        }

        $sent = 0;
        $every = $gym->weighInEveryDays();
        foreach (app(WeightTrackingService::class)->dueQuery($every)->whereHas('devices')->get() as $member) {
            $ref = $member->last_weighed_on
                ? 'due:'.Carbon::parse($member->last_weighed_on)->addDays($every)->toDateString()
                : 'first';
            $sent += (int) $this->notifyOnce($member, $ref, $this->message('weigh_in_due', $member));
        }

        return $sent;
    }

    /** Today's workout and diet from the member's program. */
    public function workoutReminder(Gym $gym): int
    {
        if (! $this->enabled($gym, 'workout_reminder')) {
            return 0;
        }

        $today = Carbon::today();
        $programs = app(ProgramService::class);
        $members = Member::query()->active()->whereHas('devices')
            ->whereHas('programs', fn ($q) => $q->where('status', 'active')
                ->whereDate('start_date', '<=', $today->toDateString())->whereDate('end_date', '>=', $today->toDateString()))
            ->get();

        $sent = 0;
        foreach ($members as $member) {
            $day = $programs->scheduleOn($member, $today, false);
            $workout = $day['workout'] && ! $day['workout']['is_rest'] ? $day['workout']['title'] : null;
            $diet = $day['diet'] && ! $day['diet']['is_rest'] ? $day['diet']['title'] : null;
            if (! $workout && ! $diet) {
                continue;
            }
            $sent += (int) $this->notifyOnce($member, $today->toDateString(), $this->message('workout_reminder', $member, [
                'today_plan' => $workout ?? $diet,
                'diet' => $diet,
            ], ['date' => $today->toDateString()], $workout ? 'workout' : 'diet'));
        }

        return $sent;
    }

    /** On open days: streak of at least the configured length, active membership, no visit today. */
    public function streakAtRisk(Gym $gym): int
    {
        $setting = $this->config->forGym($gym, 'streak_at_risk');
        if (! $setting['enabled'] || ! $gym->isOpenOn(Carbon::today())) {
            return 0;
        }

        $minimum = (int) $setting['params']['min_streak'];
        $streaks = app(StreakService::class);
        $members = Member::query()->active()->withValidMembership()->whereHas('devices')
            ->whereIn('id', MemberStreak::where('current_streak', '>=', $minimum)->select('member_id'))
            ->get();

        $sent = 0;
        foreach ($members as $member) {
            $summary = $streaks->summary($member);
            if ($summary['checked_in_today'] || $summary['current_streak'] < $minimum) {
                continue;
            }
            $sent += (int) $this->notifyOnce($member, Carbon::today()->toDateString(), $this->message('streak_at_risk', $member,
                ['streak' => $summary['current_streak']], ['current_streak' => $summary['current_streak']]));
        }

        return $sent;
    }

    /** Memberships with a balance still to pay (once per week). */
    public function balanceDue(Gym $gym): int
    {
        if (! $this->enabled($gym, 'balance_due')) {
            return 0;
        }

        $sent = 0;
        $week = Carbon::today()->format('o-\WW');
        foreach (Membership::with('member')->withBalance()->get() as $membership) {
            if (! $this->isActiveMember($membership->member) || $membership->balance() <= 0) {
                continue;
            }
            $sent += (int) $this->notifyOnce($membership->member, 'membership:'.$membership->id.':'.$week, $this->message('balance_due', $membership->member, [
                'amount' => money($membership->balance()),
                'plan' => $membership->plan_name,
            ], ['membership_id' => $membership->id, 'amount' => $membership->balance()]));
        }

        return $sent;
    }

    // ---- Helpers ------------------------------------------------------------------------------

    protected function enabled(?Gym $gym, string $key): bool
    {
        return $gym !== null && $this->config->forGym($gym, $key)['enabled'];
    }

    /** @return array{member_name: string, first_name: string, gym_name: string} */
    protected function memberValues(Member $member): array
    {
        return [
            'member_name' => $member->name,
            'first_name' => strtok(trim($member->name), ' ') ?: $member->name,
            'gym_name' => $member->gym?->name ?? '',
        ];
    }

    /** @return list<string> */
    protected function placeholdersIn(string $text): array
    {
        preg_match_all('/\{([a-z_]+)\}/', $text, $matches);

        return array_values(array_unique($matches[1]));
    }

    protected function isActiveMember(?Member $member): bool
    {
        return $member !== null && $member->status === MemberStatus::Active;
    }

    /** The member already has a later membership (renewed), so no "ending" pushes. */
    protected function hasRenewal(Membership $membership): bool
    {
        return Membership::where('member_id', $membership->member_id)->whereKeyNot($membership->id)
            ->where('status', '!=', MembershipStatus::Cancelled->value)
            ->whereDate('end_date', '>', $membership->end_date->toDateString())->exists();
    }

    protected function assertPending(Announcement $announcement): void
    {
        if ($announcement->status !== Announcement::PENDING) {
            throw ValidationException::withMessages(['announcement' => 'This notification was already reviewed.']);
        }
    }

    /** Automatic pushes must never break the action that triggered them (payment, assignment, weigh-in). */
    protected function safely(callable $callback): void
    {
        try {
            $callback();
        } catch (Throwable $e) {
            report($e);
        }
    }
}
