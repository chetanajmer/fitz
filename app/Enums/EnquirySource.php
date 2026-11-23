<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum EnquirySource: string
{
    use HasOptions;

    case WalkIn = 'walk_in';
    case Qr = 'qr';
    case Website = 'website';
    case Phone = 'phone';
    case Referral = 'referral';
    case SocialMedia = 'social_media';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::WalkIn => 'Walk-in',
            self::Qr => 'QR Code',
            default => ucwords(str_replace('_', ' ', $this->value)),
        };
    }
}
