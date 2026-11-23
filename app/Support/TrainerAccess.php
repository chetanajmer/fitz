<?php

namespace App\Support;

/**
 * The gym-panel sections a gym admin can switch on or off for each trainer.
 *
 * Grants are stored as direct Spatie permissions on the trainer's user. The trainer role
 * itself only carries `gym.dashboard`, so a trainer sees exactly the sections granted here.
 * Gym settings, Gymai billing and trainer management are never grantable (no escalation).
 */
class TrainerAccess
{
    /** @var array<string, array<string, array{label: string, hint: string}>> */
    public const SECTIONS = [
        'Members' => [
            'members.view' => ['label' => 'View members', 'hint' => 'Opens the Members section. Only their assigned members unless "See all members" is on.'],
            'members.all' => ['label' => 'See all members', 'hint' => 'Every member of the gym, not just the ones assigned to them.'],
            'members.manage' => ['label' => 'Register & edit members', 'hint' => 'Add new members and edit member details. Members they register are assigned to them.'],
            'members.import' => ['label' => 'Import members', 'hint' => 'Bulk upload from CSV / Excel.'],
        ],
        'Front desk' => [
            'enquiries.manage' => ['label' => 'Enquiries', 'hint' => 'Walk-ins and QR join requests; convert them to members (needs "Register & edit").'],
            'attendance.manage' => ['label' => 'Attendance & check-ins', 'hint' => 'Mark check-ins and scan member QR codes.'],
            'attendance.reports' => ['label' => 'Reports & streaks', 'hint' => 'Gym-wide attendance reports and streak leaderboards.'],
        ],
        'Sales' => [
            'plans.manage' => ['label' => 'Membership plans', 'hint' => 'Create and edit plans and prices.'],
            'memberships.manage' => ['label' => 'Memberships', 'hint' => 'Assign, renew and cancel memberships.'],
            'payments.manage' => ['label' => 'Payments', 'hint' => 'Collect payments, receipts and invoices.'],
        ],
        'Diet & workouts' => [
            'content.custom' => ['label' => 'Diet charts & workout plans', 'hint' => 'View the library and send custom plans to their assigned members.'],
            'content.manage' => ['label' => 'Manage the library', 'hint' => 'Edit default plans and categories for the whole gym.'],
        ],
    ];

    /** What every trainer gets unless the admin changes it. */
    public const DEFAULTS = ['members.view', 'attendance.manage', 'content.custom'];

    /** @var array<string, array{label: string, permissions: list<string>}> */
    public const PRESETS = [
        'trainer' => ['label' => 'Default trainer', 'permissions' => self::DEFAULTS],
        'registration' => ['label' => 'Member registration only', 'permissions' => ['members.view', 'members.manage']],
        'front_desk' => ['label' => 'Front desk', 'permissions' => ['members.view', 'members.all', 'members.manage', 'enquiries.manage', 'attendance.manage', 'memberships.manage', 'payments.manage']],
        'none' => ['label' => 'Dashboard only', 'permissions' => []],
    ];

    /** Permissions that only make sense together with another one. */
    protected const REQUIRES = [
        'members.all' => 'members.view',
        'members.manage' => 'members.view',
        'members.import' => 'members.view',
        'content.manage' => 'content.custom',
    ];

    /** @return list<string> */
    public static function all(): array
    {
        return array_merge(...array_map('array_keys', array_values(self::SECTIONS)));
    }

    /**
     * Drop anything not grantable and add required companions.
     *
     * @param  array<int, string>  $permissions
     * @return list<string>
     */
    public static function normalize(array $permissions): array
    {
        $granted = array_values(array_intersect(self::all(), $permissions));

        foreach ($granted as $permission) {
            if (isset(self::REQUIRES[$permission]) && ! in_array(self::REQUIRES[$permission], $granted, true)) {
                $granted[] = self::REQUIRES[$permission];
            }
        }

        return array_values(array_intersect(self::all(), $granted));
    }

    public static function label(string $permission): string
    {
        foreach (self::SECTIONS as $items) {
            if (isset($items[$permission])) {
                return $items[$permission]['label'];
            }
        }

        return $permission;
    }
}
