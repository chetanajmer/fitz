<?php

namespace App\Services;

use App\Models\Gym;

/**
 * The QR code members scan at their gym to check in and out. A gym uses one of two kinds:
 *  - printed: the gym's fixed token, until an admin replaces it;
 *  - screen: a code that changes every 30 seconds, for a tablet or PC at the desk. It is signed with the
 *    app key, so knowing the printed token is not enough to make one.
 */
class CheckInCodeService
{
    public const PRINTED = 'printed';

    public const SCREEN = 'screen';

    public const MODES = [self::PRINTED => 'Printed QR', self::SCREEN => 'Live screen QR'];

    /** Seconds one screen code is shown; the one before it is still accepted. */
    public const SCREEN_SECONDS = 30;

    public function mode(Gym $gym): string
    {
        return ($gym->settings['checkin']['qr'] ?? null) === self::SCREEN ? self::SCREEN : self::PRINTED;
    }

    /** What a QR code holds: the member dashboard's address with the code, so a phone camera app opens it too. */
    public function url(string $code): string
    {
        return route('portal.dashboard', ['checkin' => $code]);
    }

    public function printedCode(Gym $gym): string
    {
        return (string) $gym->checkin_token;
    }

    public function screenCode(Gym $gym, int $slotsAgo = 0): string
    {
        $slot = intdiv(now()->timestamp, self::SCREEN_SECONDS) - $slotsAgo;

        return substr(hash_hmac('sha256', "checkin|{$gym->id}|{$gym->checkin_token}|{$slot}", (string) config('app.key')), 0, 40);
    }

    /** Whether the scanned text (the QR's address or the bare code) is this gym's current check-in code. */
    public function accepts(Gym $gym, string $scanned): bool
    {
        if (blank($gym->checkin_token)) {
            return false;
        }

        parse_str((string) parse_url(trim($scanned), PHP_URL_QUERY), $query);
        $code = is_string($query['checkin'] ?? null) ? $query['checkin'] : trim($scanned);
        $valid = $this->mode($gym) === self::SCREEN ? [$this->screenCode($gym), $this->screenCode($gym, 1)] : [$this->printedCode($gym)];

        return collect($valid)->contains(fn (string $v) => hash_equals($v, $code));
    }
}
