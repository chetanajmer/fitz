<?php

namespace App\Services\Push;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Firebase Cloud Messaging (HTTP v1) sender for the member app (Android and iOS).
 *
 * Authenticates with a Google service-account JSON (config services.fcm.credentials):
 * a signed JWT is exchanged for an OAuth access token, cached for 50 minutes.
 */
class FcmClient
{
    public const SENT = 'sent';

    /** The token no longer belongs to an installed app: the device should be removed. */
    public const INVALID_TOKEN = 'invalid_token';

    public const FAILED = 'failed';

    protected const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    /** @var array{project_id: string, client_email: string, private_key: string, token_uri?: string}|null */
    protected ?array $credentials = null;

    public function isConfigured(): bool
    {
        try {
            $this->credentials();

            return true;
        } catch (RuntimeException) {
            return false;
        }
    }

    /**
     * Send one notification to one device token.
     *
     * @param  array<string, scalar|null>  $data  extra key/values for the app (type, screen ...); sent as strings
     * @param  string  $channel  Android notification channel id (must exist in the app)
     * @return string self::SENT | self::INVALID_TOKEN | self::FAILED
     */
    public function send(string $token, string $title, string $body, array $data = [], string $channel = 'general', ?string $imageUrl = null): string
    {
        $credentials = $this->credentials();

        // Phones cannot load pictures from a developer machine: localhost URLs are left out.
        $imageUrl = self::publicImageUrl($imageUrl);
        $data['image_url'] = $imageUrl;

        $response = Http::withToken($this->accessToken())
            ->acceptJson()
            ->timeout(10)
            ->post("https://fcm.googleapis.com/v1/projects/{$credentials['project_id']}/messages:send", [
                'message' => [
                    'token' => $token,
                    'notification' => array_filter(['title' => $title, 'body' => $body, 'image' => $imageUrl]),
                    'data' => array_map(fn ($value) => is_bool($value) ? ($value ? 'true' : 'false') : (string) $value, array_filter($data, fn ($value) => $value !== null)),
                    'android' => [
                        'priority' => 'high',
                        'notification' => array_filter(['channel_id' => $channel, 'sound' => 'default', 'image' => $imageUrl]),
                    ],
                    'apns' => $this->apns($imageUrl),
                ],
            ]);

        if ($response->successful()) {
            return self::SENT;
        }

        if ($this->isDeadToken($response)) {
            Log::info('FCM token no longer valid, device removed', ['status' => $response->status(), 'error' => $response->json('error.message')]);

            return self::INVALID_TOKEN;
        }

        if ($response->status() === 401) {
            Cache::forget($this->cacheKey());
        }

        Log::warning('FCM send failed', ['status' => $response->status(), 'error' => $response->json('error.message')]);

        return self::FAILED;
    }

    /** A full http(s) URL a phone can load, or null (missing, relative, localhost or a private address). */
    public static function publicImageUrl(?string $url): ?string
    {
        if (! is_string($url) || ! preg_match('#^https?://#i', $url)) {
            return null;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        if ($host === '' || $host === 'localhost' || str_ends_with($host, '.localhost') || str_ends_with($host, '.test')
            || (filter_var($host, FILTER_VALIDATE_IP) && ! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE))) {
            return null;
        }

        return $url;
    }

    /**
     * iOS options. APNs refuses null values (e.g. "mutable-content" must be exactly 1 when present),
     * and FCM then rejects the whole message, also for Android phones.
     *
     * @return array<string, mixed>
     */
    protected function apns(?string $imageUrl): array
    {
        $aps = ['sound' => 'default'];

        if ($imageUrl) {
            $aps['mutable-content'] = 1;
        }

        return array_filter([
            'payload' => ['aps' => $aps],
            'fcm_options' => $imageUrl ? ['image' => $imageUrl] : null,
        ]);
    }

    /**
     * Only a token Firebase no longer knows means "remove the phone". Other errors (a bad message,
     * quota, server trouble) must never delete devices.
     */
    protected function isDeadToken(Response $response): bool
    {
        $codes = collect($response->json('error.details', []))->pluck('errorCode')->filter()->all();

        if ($response->status() === 404 || in_array('UNREGISTERED', $codes, true)) {
            return true;
        }

        if ($response->status() !== 400) {
            return false;
        }

        $fields = collect($response->json('error.details', []))->flatMap(fn ($detail) => $detail['fieldViolations'] ?? [])->pluck('field')->all();

        return in_array('message.token', $fields, true)
            || str_contains(strtolower((string) $response->json('error.message')), 'registration token');
    }

    protected function accessToken(): string
    {
        return Cache::remember($this->cacheKey(), now()->addMinutes(50), function () {
            $credentials = $this->credentials();
            $tokenUri = $credentials['token_uri'] ?? 'https://oauth2.googleapis.com/token';
            $now = time();

            $jwt = $this->jwt([
                'iss' => $credentials['client_email'],
                'scope' => self::SCOPE,
                'aud' => $tokenUri,
                'iat' => $now,
                'exp' => $now + 3600,
            ], $credentials['private_key']);

            $response = Http::asForm()->timeout(10)->post($tokenUri, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

            $token = $response->json('access_token');

            if (! $response->successful() || ! is_string($token)) {
                throw new RuntimeException('Firebase authentication failed: '.($response->json('error_description') ?? $response->status()));
            }

            return $token;
        });
    }

    /** @param  array<string, mixed>  $claims */
    protected function jwt(array $claims, string $privateKey): string
    {
        $encode = fn (string $value) => rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
        $unsigned = $encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])).'.'.$encode(json_encode($claims));

        if (! openssl_sign($unsigned, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Could not sign the Firebase token: check the private key in the service-account file.');
        }

        return $unsigned.'.'.$encode($signature);
    }

    /** @return array{project_id: string, client_email: string, private_key: string, token_uri?: string} */
    protected function credentials(): array
    {
        if ($this->credentials !== null) {
            return $this->credentials;
        }

        $path = $this->credentialsPath();

        if ($path === '' || ! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException('Firebase is not configured: set FIREBASE_CREDENTIALS to the service-account JSON file.');
        }

        $json = json_decode((string) file_get_contents($path), true);

        if (! is_array($json) || empty($json['project_id']) || empty($json['client_email']) || empty($json['private_key'])) {
            throw new RuntimeException('The Firebase service-account file is not valid (project_id, client_email and private_key are required).');
        }

        return $this->credentials = $json;
    }

    /** FIREBASE_CREDENTIALS may be absolute or relative to the project folder (e.g. storage/app/firebase/service-account.json). */
    protected function credentialsPath(): string
    {
        $path = trim((string) config('services.fcm.credentials'));

        if ($path === '' || str_starts_with($path, '/') || preg_match('#^[A-Za-z]:[\\\\/]#', $path)) {
            return $path;
        }

        return base_path($path);
    }

    protected function cacheKey(): string
    {
        return 'fcm.access_token.'.md5((string) config('services.fcm.credentials'));
    }
}
