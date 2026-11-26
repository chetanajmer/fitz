<?php

namespace App\Support;

/**
 * The automatic member notifications Fitnix can send, with their default wording, timing and rules.
 *
 * The Super Admin edits the wording, default time and rules (notification_templates table).
 * Gyms can only switch a notification on / off and change its time and rules (never the wording).
 * Texts may only use the placeholders listed for their type; the values are filled in by Fitnix.
 */
class NotificationCatalog
{
    /** Placeholders every template may use. */
    public const COMMON_PLACEHOLDERS = [
        'member_name' => 'Member\'s full name',
        'first_name' => 'Member\'s first name',
        'gym_name' => 'Gym name',
    ];

    /** Weekday options (ISO: 1 = Monday). */
    public const WEEKDAYS = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];

    /** Choices offered for "days before the membership ends". */
    public const EXPIRY_DAY_OPTIONS = [1, 2, 3, 5, 7, 10, 14];

    /**
     * @var array<string, array{label: string, description: string, kind: string, time?: string, title: string, body: string,
     *     placeholders: array<string, string>, params?: array<string, array<string, mixed>>}>
     */
    public const TYPES = [
        'membership_expiring' => [
            'label' => 'Membership ending',
            'description' => 'Before an active membership ends, unless the member has already renewed.',
            'kind' => 'scheduled',
            'time' => '10:00',
            'title' => 'Membership ends {days_text}',
            'body' => 'Hi {first_name}, your {plan} at {gym_name} ends on {end_date}. Renew to keep your streak going.',
            'placeholders' => ['days_text' => '"tomorrow" or "in 3 days"', 'plan' => 'Membership plan', 'end_date' => 'End date, e.g. 26 Nov'],
            'params' => ['days' => ['label' => 'Days before it ends', 'type' => 'days', 'default' => [7, 3, 1]]],
        ],
        'membership_expired' => [
            'label' => 'Membership ended',
            'description' => 'The day after a membership ended without a renewal.',
            'kind' => 'scheduled',
            'time' => '10:05',
            'title' => 'Your membership has ended',
            'body' => 'Hi {first_name}, your {plan} at {gym_name} ended on {end_date}. Renew today to continue your progress.',
            'placeholders' => ['plan' => 'Membership plan', 'end_date' => 'End date'],
        ],
        'balance_due' => [
            'label' => 'Pending fees',
            'description' => 'Once a week, when a membership still has a balance to pay.',
            'kind' => 'scheduled',
            'time' => '11:00',
            'title' => 'Pending fees: {amount}',
            'body' => 'Hi {first_name}, please clear {amount} for your {plan} at {gym_name}.',
            'placeholders' => ['amount' => 'Amount to pay', 'plan' => 'Membership plan'],
            'params' => ['weekday' => ['label' => 'Day of the week', 'type' => 'weekday', 'default' => 1]],
        ],
        'workout_reminder' => [
            'label' => 'Daily workout reminder',
            'description' => 'Today\'s workout (or diet) from the member\'s assigned program.',
            'kind' => 'scheduled',
            'time' => '07:30',
            'title' => 'Today: {today_plan}',
            'body' => 'Good morning {first_name}! Tap to see your full plan for today.',
            'placeholders' => ['today_plan' => 'Today\'s workout, or the diet on rest days', 'diet' => 'Today\'s diet'],
        ],
        'weigh_in_due' => [
            'label' => 'Weigh-in reminder',
            'description' => 'When it is time to record their weight again (gym weigh-in interval).',
            'kind' => 'scheduled',
            'time' => '08:00',
            'title' => 'Time for your weigh-in',
            'body' => 'Hi {first_name}, record your weight in the app to track your progress.',
            'placeholders' => [],
        ],
        'streak_at_risk' => [
            'label' => 'Streak at risk',
            'description' => 'On days the gym is open, for members on a streak who have not visited today.',
            'kind' => 'scheduled',
            'time' => '19:00',
            'title' => 'Keep your {streak}-day streak alive',
            'body' => '{gym_name} is open today. A quick visit keeps your streak going!',
            'placeholders' => ['streak' => 'Current streak in days'],
            'params' => ['min_streak' => ['label' => 'Minimum streak (days)', 'type' => 'number', 'default' => 3, 'min' => 2, 'max' => 30]],
        ],
        'payment_received' => [
            'label' => 'Payment received',
            'description' => 'Right after a payment is recorded.',
            'kind' => 'event',
            'title' => 'Payment received',
            'body' => '{amount} received by {gym_name}. Receipt {receipt}. Thank you!',
            'placeholders' => ['amount' => 'Amount paid', 'receipt' => 'Receipt number', 'plan' => 'Membership plan'],
        ],
        'plan_assigned' => [
            'label' => 'New program',
            'description' => 'When a workout or diet program is assigned to the member.',
            'kind' => 'event',
            'title' => 'Your new {plan_type} plan is ready',
            'body' => '"{program}" {start_text}. Tap to see today\'s plan.',
            'placeholders' => ['plan_type' => '"workout" or "diet"', 'program' => 'Program name', 'start_text' => '"starts today" or "starts Mon, 30 Nov"'],
        ],
        'goal_achieved' => [
            'label' => 'Target weight reached',
            'description' => 'When a weigh-in reaches the member\'s target weight.',
            'kind' => 'event',
            'title' => 'You reached your target weight!',
            'body' => 'You hit {weight}, {first_name}. Congratulations from everyone at {gym_name}!',
            'placeholders' => ['weight' => 'Target weight, e.g. 70 kg'],
        ],
    ];

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::TYPES);
    }

    /** @return list<string> */
    public static function scheduledKeys(): array
    {
        return array_keys(array_filter(self::TYPES, fn ($t) => $t['kind'] === 'scheduled'));
    }

    /** @return array<string, string> placeholder => description */
    public static function placeholders(string $key): array
    {
        return self::COMMON_PLACEHOLDERS + (self::TYPES[$key]['placeholders'] ?? []);
    }

    /** @return array<string, mixed> */
    public static function defaultParams(string $key): array
    {
        return array_map(fn ($p) => $p['default'], self::TYPES[$key]['params'] ?? []);
    }

    /**
     * Clean a set of rules for a type (unknown keys dropped, values kept in range).
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public static function normalizeParams(string $key, array $values): array
    {
        $params = [];

        foreach (self::TYPES[$key]['params'] ?? [] as $name => $definition) {
            $value = $values[$name] ?? $definition['default'];

            $params[$name] = match ($definition['type']) {
                'days' => collect((array) $value)->map(fn ($d) => (int) $d)
                    ->filter(fn ($d) => in_array($d, self::EXPIRY_DAY_OPTIONS, true))->unique()->sortDesc()->values()->all() ?: $definition['default'],
                'weekday' => array_key_exists((int) $value, self::WEEKDAYS) ? (int) $value : $definition['default'],
                default => max($definition['min'] ?? 0, min($definition['max'] ?? 1000, (int) $value)),
            };
        }

        return $params;
    }

    /** Placeholders used in a text that this type does not support. @return list<string> */
    public static function unknownPlaceholders(string $key, string $text): array
    {
        preg_match_all('/\{([a-z_]+)\}/', $text, $matches);

        return array_values(array_diff(array_unique($matches[1]), array_keys(self::placeholders($key))));
    }

    /** @param  array<string, scalar|null>  $values */
    public static function render(string $text, array $values): string
    {
        $pairs = [];
        foreach ($values as $name => $value) {
            $pairs['{'.$name.'}'] = (string) $value;
        }

        return trim(preg_replace('/\s+/', ' ', strtr($text, $pairs)));
    }
}
