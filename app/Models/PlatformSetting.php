<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class PlatformSetting extends Model
{
    protected $fillable = ['key', 'value'];

    public const DEFAULTS = [
        'platform_name' => 'Gymai',
        'support_email' => 'support@gymai.com',
        'support_phone' => null,
        'invoice_prefix' => 'GYMAI',
        'invoice_due_days' => '15',
        'company_address' => null,
        'gst_number' => null,
        'allow_registrations' => '1',

        // Member mobile app release management (used by GET /api/v1/app/version).
        'android_latest_version' => '1.0.0',
        'android_min_version' => '1.0.0',
        'android_store_url' => 'https://play.google.com/store/apps/details?id=com.gymai.member',
        'android_release_notes' => null,
        'ios_latest_version' => '1.0.0',
        'ios_min_version' => '1.0.0',
        'ios_store_url' => null,
        'ios_release_notes' => null,
        'app_maintenance_mode' => '0',
        'app_maintenance_message' => null,

        // AI progress module (OpenRouter). Gyms get their own limits on the AI page.
        'ai_enabled' => '0',
        'ai_model' => 'anthropic/claude-haiku-5.5',
        'ai_credits_progress' => '1',
        'ai_credits_photo_comparison' => '2',
        'ai_default_gym_credits' => '100',
        'ai_default_member_ceiling' => '4',
    ];

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = Cache::rememberForever('platform_settings', fn () => static::pluck('value', 'key')->all());

        return $all[$key] ?? self::DEFAULTS[$key] ?? $default;
    }

    public static function put(array $values): void
    {
        foreach ($values as $key => $value) {
            static::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        Cache::forget('platform_settings');
    }
}
