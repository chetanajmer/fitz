<?php

namespace App\Services\Push;

/**
 * One push notification for the member app.
 *
 * screen  = the app screen to open: home, profile, membership, payments, progress, weight, plans,
 *           workout, diet, streak, checkin, gym (anything else opens Home).
 * channel = the logical channel: general, reminders, water, membership, announcements. config/push.php maps it
 *           to the Android channel id (fitz_general, ...) and sound the app ships.
 */
final class PushMessage
{
    public const SCREENS = ['home', 'profile', 'membership', 'payments', 'progress', 'weight', 'plans', 'workout', 'diet', 'streak', 'checkin', 'gym'];

    public const CHANNELS = ['general', 'reminders', 'water', 'membership', 'announcements'];

    /**
     * type => [member-settings category, default screen, Android channel].
     * "account" notifications are always delivered; the member can switch off the other categories.
     */
    public const TYPES = [
        'announcement' => ['announcements', 'gym', 'announcements'],
        'membership_expiring' => ['account', 'profile', 'membership'],
        'membership_expired' => ['account', 'profile', 'membership'],
        'payment_received' => ['account', 'payments', 'membership'],
        'balance_due' => ['account', 'payments', 'membership'],
        'plan_assigned' => ['account', 'workout', 'general'],
        'goal_achieved' => ['account', 'progress', 'general'],
        'ai_report_ready' => ['account', 'progress', 'general'],
        'weigh_in_due' => ['reminders', 'progress', 'reminders'],
        'workout_reminder' => ['reminders', 'workout', 'reminders'],
        'streak_at_risk' => ['reminders', 'streak', 'reminders'],
        'test' => ['account', 'home', 'general'],
    ];

    /** @param  array<string, scalar|null>  $data */
    public function __construct(
        public readonly string $type,
        public readonly string $title,
        public readonly string $body,
        public readonly string $screen,
        public readonly string $channel,
        public readonly array $data = [],
        public readonly ?string $imageUrl = null,
    ) {}

    /**
     * Build a message with the screen and channel of its type.
     *
     * @param  array<string, scalar|null>  $data
     */
    public static function make(string $type, string $title, string $body, array $data = [], ?string $screen = null, ?string $imageUrl = null): self
    {
        [, $defaultScreen, $channel] = self::TYPES[$type] ?? ['account', 'home', 'general'];
        $screen = in_array($screen, self::SCREENS, true) ? $screen : $defaultScreen;

        return new self($type, mb_substr($title, 0, 150), mb_substr($body, 0, 1000), $screen, $channel, $data, $imageUrl);
    }

    public function category(): string
    {
        return self::TYPES[$this->type][0] ?? 'account';
    }

    /**
     * The data stored with the in-app notification and sent in the FCM "data" block.
     *
     * @return array<string, scalar>
     */
    public function payload(): array
    {
        return array_filter(
            ['type' => $this->type, 'screen' => $this->screen, 'channel' => $this->channel, 'image_url' => $this->imageUrl] + $this->data,
            fn ($value) => $value !== null,
        );
    }
}
