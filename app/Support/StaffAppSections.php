<?php

namespace App\Support;

use App\Models\User;

/**
 * The sections of the staff mobile app and the permissions that open them.
 *
 * Each rule mirrors the staff.can middleware on that section's routes: every entry of the list is
 * required, and "a|b" inside an entry means any one of them. The app shows a section only when it
 * is true here, so a trainer sees exactly what their gym admin switched on (App\Support\TrainerAccess).
 */
class StaffAppSections
{
    /** @var array<string, list<string>> */
    public const RULES = [
        'dashboard' => ['gym.dashboard'],
        'members' => ['members.view'],
        'members_all' => ['members.all'],
        'member_add' => ['members.manage'],
        'member_import' => ['members.import'],
        'check_in' => ['attendance.manage'],
        'attendance' => ['attendance.manage|attendance.reports'],
        'attendance_reports' => ['attendance.reports'],
        'enquiries' => ['enquiries.manage'],
        'join_requests' => ['enquiries.manage', 'members.manage'],
        'fees' => ['memberships.manage|payments.manage'],
        'memberships' => ['memberships.manage'],
        'payments' => ['payments.manage'],
        'finance_reports' => ['payments.manage'],
        'plans' => ['plans.manage'],
        'programs' => ['content.custom|content.manage'],
        'ai_progress' => ['ai.view'],
        'announcements' => ['announcements.send'],
        'trainers' => ['trainers.manage'],
        'gym_settings' => ['gym.settings'],
        'ai_coach' => ['ai.manage'],
        'billing' => ['gym.billing'],
    ];

    /** @return array<string, bool> Every section, true when the user may open it. */
    public static function for(User $user): array
    {
        $granted = self::permissionsOf($user);

        return array_map(
            fn (array $rule): bool => collect($rule)->every(
                fn (string $anyOf): bool => array_intersect(explode('|', $anyOf), $granted) !== []
            ),
            self::RULES,
        );
    }

    /**
     * A short fingerprint of what the user can open. The staff app compares it with the
     * X-Staff-Access header of every response and reloads GET /admin/me when it changes.
     */
    public static function version(User $user): string
    {
        return substr(sha1(implode(',', self::permissionsOf($user))), 0, 12);
    }

    /** @return list<string> */
    public static function permissionsOf(User $user): array
    {
        return $user->getAllPermissions()->pluck('name')->sort()->values()->all();
    }
}
