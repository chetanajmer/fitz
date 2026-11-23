<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum EnquiryStatus: string
{
    use HasOptions;

    case New = 'new';
    case Contacted = 'contacted';
    case FollowUp = 'follow_up';
    case Interested = 'interested';
    case Converted = 'converted';
    case NotInterested = 'not_interested';

    public function label(): string
    {
        return match ($this) {
            self::FollowUp => 'Follow-up',
            default => ucwords(str_replace('_', ' ', $this->value)),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::New => 'blue',
            self::Contacted => 'indigo',
            self::FollowUp => 'amber',
            self::Interested => 'purple',
            self::Converted => 'green',
            self::NotInterested => 'gray',
        };
    }

    /** Statuses a user may set manually. "Converted" is only reachable via conversion. */
    public static function manualOptions(): array
    {
        return collect(self::options())->except(self::Converted->value)->all();
    }
}
