<?php

namespace App\Services;

use App\Models\Gym;
use App\Models\NotificationTemplate;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Support\NotificationCatalog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

/**
 * Configuration of the automatic member notifications:
 *  - templates (wording, default time, default rules, on/off for the whole platform): Super Admin;
 *  - per gym: on/off, time and rules (never the wording): gym admins;
 *  - platform rules: quiet hours and the daily custom-notification limit: Super Admin.
 */
class NotificationSettings
{
    public const QUIET_START = '21:00';

    public const QUIET_END = '07:00';

    public const DAILY_ANNOUNCEMENT_LIMIT = 3;

    /** Custom notifications a gym may submit for review per day. */
    public const DAILY_SUBMISSION_LIMIT = 10;

    public const TITLE_MAX = 65;

    public const BODY_MAX = 200;

    /** Custom gym notifications can be longer: the app shows the full text on the detail page. */
    public const ANNOUNCEMENT_BODY_MAX = 1000;

    /** Picture on a custom notification (jpg / png / webp), in KB. */
    public const IMAGE_MAX_KB = 2048;

    // ---- Templates ---------------------------------------------------------------------------

    /** @return array{key: string, title: string, body: string, is_enabled: bool, send_time: ?string, params: array<string, mixed>} */
    public function template(string $key): array
    {
        $definition = NotificationCatalog::TYPES[$key];
        $row = $this->templates()[$key] ?? null;

        return [
            'key' => $key,
            'title' => $row['title'] ?? $definition['title'],
            'body' => $row['body'] ?? $definition['body'],
            'is_enabled' => (bool) ($row['is_enabled'] ?? true),
            'send_time' => $definition['kind'] === 'scheduled' ? ($row['send_time'] ?? $definition['time']) : null,
            'params' => NotificationCatalog::normalizeParams($key, $row['params'] ?? []),
        ];
    }

    /** @param  array{title: string, body: string, is_enabled: bool, send_time?: ?string, params?: array<string, mixed>}  $data */
    public function saveTemplate(string $key, array $data, User $by): void
    {
        abort_unless(isset(NotificationCatalog::TYPES[$key]), 404);
        $title = trim($data['title']);
        $body = trim($data['body']);

        $errors = [];
        foreach (['title' => $title, 'body' => $body] as $field => $text) {
            if ($unknown = NotificationCatalog::unknownPlaceholders($key, $text)) {
                $errors[$field] = 'Unknown placeholder: {'.implode('}, {', $unknown).'}. Use only the ones listed.';
            }
        }

        $time = null;
        if (NotificationCatalog::TYPES[$key]['kind'] === 'scheduled') {
            $time = $this->validTime((string) ($data['send_time'] ?? ''), 'send_time');
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        $template = NotificationTemplate::firstOrNew(['key' => $key]);
        $template->forceFill([
            'key' => $key,
            'title' => $title,
            'body' => $body,
            'is_enabled' => (bool) $data['is_enabled'],
            'send_time' => $time,
            'params' => NotificationCatalog::normalizeParams($key, $data['params'] ?? []),
            'updated_by' => $by->id,
        ])->save();

        Cache::forget('notification_templates');
    }

    /** Activate or deactivate a notification for every gym. Returns the new state. */
    public function setTemplateEnabled(string $key, bool $enabled, User $by): bool
    {
        $template = $this->template($key);
        $this->saveTemplate($key, ['is_enabled' => $enabled] + $template, $by);

        return $enabled;
    }

    /** Put a template back to the Gymai default wording, time and rules. */
    public function resetTemplate(string $key, User $by): void
    {
        $definition = NotificationCatalog::TYPES[$key];
        $this->saveTemplate($key, [
            'title' => $definition['title'], 'body' => $definition['body'], 'is_enabled' => true,
            'send_time' => $definition['time'] ?? null, 'params' => NotificationCatalog::defaultParams($key),
        ], $by);
    }

    /**
     * The final title and body for a member.
     *
     * @param  array<string, scalar|null>  $values  type values (days, amount ...) plus member_name, first_name, gym_name
     * @return array{0: string, 1: string}
     */
    public function render(string $key, array $values): array
    {
        $template = $this->template($key);

        return [
            mb_substr(NotificationCatalog::render($template['title'], $values), 0, 150),
            mb_substr(NotificationCatalog::render($template['body'], $values), 0, 1000),
        ];
    }

    // ---- Per gym ------------------------------------------------------------------------------

    /**
     * What a gym actually uses for a notification: the platform template with the gym's own choices.
     * A notification switched off by the Super Admin is off for every gym.
     *
     * enabled = it will actually be sent (platform on, gym on, gym not paused); gym_enabled = the gym's own switch.
     *
     * @return array{enabled: bool, gym_enabled: bool, platform_enabled: bool, time: ?string, params: array<string, mixed>, customised: bool}
     */
    public function forGym(Gym $gym, string $key): array
    {
        $template = $this->template($key);
        $own = $gym->settings['notifications'][$key] ?? null;
        $own = is_bool($own) ? ['enabled' => $own] : (array) $own;

        $time = $template['send_time'];
        if ($time && ! empty($own['time']) && $this->isAllowedTime($own['time'])) {
            $time = $own['time'];
        }

        $gymEnabled = (bool) ($own['enabled'] ?? true);

        return [
            'enabled' => $template['is_enabled'] && $gymEnabled && ! $this->isPausedFor($gym),
            'gym_enabled' => $gymEnabled,
            'platform_enabled' => $template['is_enabled'],
            'time' => $time,
            'params' => NotificationCatalog::normalizeParams($key, ($own['params'] ?? []) + $template['params']),
            'customised' => isset($own['time']) || ! empty($own['params']),
        ];
    }

    /** @param  array{enabled: bool, time?: ?string, params?: array<string, mixed>}  $values */
    public function saveForGym(Gym $gym, string $key, array $values): void
    {
        abort_unless(isset(NotificationCatalog::TYPES[$key]), 404);
        $scheduled = NotificationCatalog::TYPES[$key]['kind'] === 'scheduled';
        $template = $this->template($key);

        $time = $scheduled && ! empty($values['time']) ? $this->validTime($values['time'], 'time') : null;
        $params = NotificationCatalog::normalizeParams($key, ($values['params'] ?? []) + $template['params']);

        $own = array_filter([
            'enabled' => (bool) $values['enabled'],
            'time' => $time !== $template['send_time'] ? $time : null,
            'params' => $params !== $template['params'] ? $params : null,
        ], fn ($v) => $v !== null);

        $settings = $gym->settings ?? [];
        $settings['notifications'][$key] = $own;
        $gym->settings = $settings;
        $gym->save();
    }

    /** Switch one automatic notification on / off for a gym, keeping its time and rules. */
    public function setEnabledForGym(Gym $gym, string $key, bool $enabled): void
    {
        abort_unless(isset(NotificationCatalog::TYPES[$key]), 404);
        $settings = $gym->settings ?? [];
        $own = $settings['notifications'][$key] ?? [];
        $own = is_bool($own) ? [] : (array) $own;
        $settings['notifications'][$key] = ['enabled' => $enabled] + $own;
        $gym->settings = $settings;
        $gym->save();
    }

    /** A gym admin can pause every automatic notification at once (their own choices are kept). */
    public function isPausedFor(Gym $gym): bool
    {
        return (bool) ($gym->settings['notifications_paused'] ?? false);
    }

    public function setPausedFor(Gym $gym, bool $paused): void
    {
        $settings = $gym->settings ?? [];
        $settings['notifications_paused'] = $paused;
        $gym->settings = $settings;
        $gym->save();
    }

    public function resetForGym(Gym $gym, string $key): void
    {
        $settings = $gym->settings ?? [];
        $enabled = $this->forGym($gym, $key)['gym_enabled'];
        $settings['notifications'][$key] = ['enabled' => $enabled];
        $gym->settings = $settings;
        $gym->save();
    }

    // ---- Platform rules: quiet hours, limits ------------------------------------------------------

    /** @return array{start: string, end: string} */
    public function quietHours(): array
    {
        return [
            'start' => $this->clock(PlatformSetting::get('push_quiet_start'), self::QUIET_START),
            'end' => $this->clock(PlatformSetting::get('push_quiet_end'), self::QUIET_END),
        ];
    }

    public function dailyAnnouncementLimit(): int
    {
        return max(1, min(20, (int) (PlatformSetting::get('push_daily_announcements') ?? self::DAILY_ANNOUNCEMENT_LIMIT)));
    }

    public function isQuietAt(Carbon $at): bool
    {
        ['start' => $start, 'end' => $end] = $this->quietHours();
        $time = $at->format('H:i');

        return $start > $end ? ($time >= $start || $time < $end) : ($time >= $start && $time < $end);
    }

    /** When pushes may go out again, or null if not in quiet hours now. */
    public function quietUntil(?Carbon $at = null): ?Carbon
    {
        $at ??= now();

        if (! $this->isQuietAt($at)) {
            return null;
        }

        [$hour, $minute] = array_map('intval', explode(':', $this->quietHours()['end']));
        $end = $at->copy()->setTime($hour, $minute);

        return $end->lte($at) ? $end->addDay() : $end;
    }

    public function isAllowedTime(string $time): bool
    {
        return (bool) preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time) && ! $this->isQuietAt(Carbon::today()->setTimeFromTimeString($time));
    }

    /** Times a gym can pick (every 30 minutes outside quiet hours). @return list<string> */
    public function timeOptions(): array
    {
        $options = [];
        for ($minutes = 0; $minutes < 24 * 60; $minutes += 30) {
            $time = sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
            if ($this->isAllowedTime($time)) {
                $options[] = $time;
            }
        }

        return $options;
    }

    /** @param  array{start: string, end: string, daily_limit: int}  $values */
    public function saveRules(array $values): void
    {
        $start = $this->validClock($values['start'], 'quietStart');
        $end = $this->validClock($values['end'], 'quietEnd');

        if ($start === $end) {
            throw ValidationException::withMessages(['quietEnd' => 'Quiet hours must start and end at different times.']);
        }

        PlatformSetting::put([
            'push_quiet_start' => $start,
            'push_quiet_end' => $end,
            'push_daily_announcements' => (string) max(1, min(20, (int) $values['daily_limit'])),
        ]);
    }

    // ---- Internals ----------------------------------------------------------------------------

    /** @return array<string, array<string, mixed>> */
    protected function templates(): array
    {
        return Cache::rememberForever('notification_templates', fn () => NotificationTemplate::all()
            ->mapWithKeys(fn (NotificationTemplate $t) => [$t->key => [
                'title' => $t->title, 'body' => $t->body, 'is_enabled' => $t->is_enabled, 'send_time' => $t->send_time, 'params' => $t->params ?? [],
            ]])->all());
    }

    protected function validTime(string $time, string $field): string
    {
        if (! $this->isAllowedTime($time)) {
            $quiet = $this->quietHours();
            throw ValidationException::withMessages([$field => 'Choose a time outside quiet hours ('.$quiet['start'].' – '.$quiet['end'].').']);
        }

        return $time;
    }

    protected function validClock(string $time, string $field): string
    {
        if (! preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time)) {
            throw ValidationException::withMessages([$field => 'Use the HH:MM format.']);
        }

        return $time;
    }

    protected function clock(mixed $value, string $default): string
    {
        return is_string($value) && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value) ? $value : $default;
    }
}
