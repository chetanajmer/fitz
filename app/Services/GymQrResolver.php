<?php

namespace App\Services;

use App\Models\Gym;

/**
 * Works out which gym a scanned QR code belongs to. It understands every gym QR Fitnix prints:
 *  - join:     /join/{join_token}          (the public "join this gym" link)
 *  - register: /register/{register_token}  (the desk self-registration link)
 *  - checkin:  /portal…?checkin={code}     (the check-in QR, printed or live on a screen), or the bare code
 *
 * Links must be http(s) on one of our own domains; trailing slashes, query strings and http vs https don't matter.
 */
class GymQrResolver
{
    public const KIND_JOIN = 'join';

    public const KIND_REGISTER = 'register';

    /** The gym's printed check-in QR (fixed token). */
    public const KIND_CHECKIN_PRINTED = 'checkin_printed';

    /** The live check-in QR on the gym's screen (changes every 30 seconds). */
    public const KIND_CHECKIN_SCREEN = 'checkin_screen';

    private const TOKEN = '[A-Za-z0-9]{20,64}';

    public function __construct(protected CheckInCodeService $codes) {}

    /** The gym a scanned QR belongs to, or null when it isn't one of our gym QR codes. */
    public function fromUrl(string $qr, ?Gym $expected = null): ?Gym
    {
        return $this->resolve($qr, $expected)['gym'] ?? null;
    }

    /**
     * The gym and the kind of QR. A live screen code can only be recognised for a known gym, so pass the gym
     * the member belongs to as $expected.
     *
     * @return array{gym: Gym, kind: string}|null
     */
    public function resolve(string $qr, ?Gym $expected = null): ?array
    {
        $qr = trim($qr);

        if ($qr === '' || strlen($qr) > 2000) {
            return null;
        }

        // Legacy: the bare check-in code (what the old app sent as "code").
        if (preg_match('/^[A-Za-z0-9]{20,64}$/', $qr)) {
            return $this->fromCheckInCode($qr, $expected);
        }

        $parts = parse_url($qr);

        if (! $parts || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true) || ! $this->isAllowedHost($parts['host'] ?? '')) {
            return null;
        }

        $path = rtrim((string) ($parts['path'] ?? ''), '/');

        if (preg_match('#/join/('.self::TOKEN.')$#', $path, $m)) {
            return $this->found(Gym::where('join_token', $m[1])->first(), self::KIND_JOIN);
        }

        if (preg_match('#/register/('.self::TOKEN.')$#', $path, $m)) {
            return $this->found(Gym::where('register_token', $m[1])->first(), self::KIND_REGISTER);
        }

        parse_str((string) ($parts['query'] ?? ''), $query);

        if (is_string($query['checkin'] ?? null) && preg_match('/^'.self::TOKEN.'$/', $query['checkin'])) {
            return $this->fromCheckInCode($query['checkin'], $expected);
        }

        return null;
    }

    /** @return list<string> */
    public function allowedHosts(): array
    {
        $appHost = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
        $hosts = array_map('strtolower', (array) config('app.qr_allowed_hosts', []));

        if ($appHost !== '') {
            $bare = preg_replace('/^www\./', '', $appHost);
            array_push($hosts, $bare, 'www.'.$bare);
        }

        return array_values(array_unique(array_filter($hosts)));
    }

    protected function isAllowedHost(string $host): bool
    {
        return $host !== '' && in_array(strtolower($host), $this->allowedHosts(), true);
    }

    /** @return array{gym: Gym, kind: string}|null */
    protected function fromCheckInCode(string $code, ?Gym $expected): ?array
    {
        if ($expected && ! blank($expected->checkin_token)) {
            if (hash_equals($this->codes->printedCode($expected), $code)) {
                return $this->found($expected, self::KIND_CHECKIN_PRINTED);
            }
            if (hash_equals($this->codes->screenCode($expected), $code) || hash_equals($this->codes->screenCode($expected, 1), $code)) {
                return $this->found($expected, self::KIND_CHECKIN_SCREEN);
            }
        }

        return $this->found(Gym::where('checkin_token', $code)->first(), self::KIND_CHECKIN_PRINTED);
    }

    /** @return array{gym: Gym, kind: string}|null */
    protected function found(?Gym $gym, string $kind): ?array
    {
        return $gym && $gym->isOperational() ? ['gym' => $gym, 'kind' => $kind] : null;
    }
}
