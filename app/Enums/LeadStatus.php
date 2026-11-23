<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum LeadStatus: string
{
    use HasOptions;

    case New = 'new';
    case Contacted = 'contacted';
    case FollowUp = 'follow_up';
    case Converted = 'converted';
    case NotInterested = 'not_interested';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::FollowUp => 'Follow-up',
            self::Converted => 'Connected to gym',
            default => ucwords(str_replace('_', ' ', $this->value)),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::New => 'blue',
            self::Contacted => 'indigo',
            self::FollowUp => 'amber',
            self::Converted => 'green',
            self::NotInterested, self::Closed => 'gray',
        };
    }

    /** Statuses that mean the lead still needs work. */
    public static function openValues(): array
    {
        return [self::New->value, self::Contacted->value, self::FollowUp->value];
    }
}
