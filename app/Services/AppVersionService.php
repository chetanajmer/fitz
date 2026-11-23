<?php

namespace App\Services;

use App\Models\PlatformSetting;

/**
 * Tells the member app whether it should update.
 *  - version <  min_version     → force_update (block the app until updated)
 *  - version <  latest_version  → update_available (optional prompt)
 */
class AppVersionService
{
    public const PLATFORMS = ['android', 'ios'];

    /**
     * @return array{platform:string, current_version:string, latest_version:string, min_supported_version:string, update_available:bool, force_update:bool, store_url:?string, release_notes:?string, maintenance:array{enabled:bool, message:?string}}
     */
    public function check(string $platform, string $currentVersion): array
    {
        $latest = (string) PlatformSetting::get("{$platform}_latest_version", '1.0.0');
        $minimum = (string) PlatformSetting::get("{$platform}_min_version", '1.0.0');

        return [
            'platform' => $platform,
            'current_version' => $currentVersion,
            'latest_version' => $latest,
            'min_supported_version' => $minimum,
            'update_available' => version_compare($currentVersion, $latest, '<'),
            'force_update' => version_compare($currentVersion, $minimum, '<'),
            'store_url' => PlatformSetting::get("{$platform}_store_url"),
            'release_notes' => PlatformSetting::get("{$platform}_release_notes"),
            'maintenance' => [
                'enabled' => PlatformSetting::get('app_maintenance_mode', '0') === '1',
                'message' => PlatformSetting::get('app_maintenance_message'),
            ],
        ];
    }
}
