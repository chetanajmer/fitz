<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Verifies an invisible Google reCAPTCHA v3 token with Google's siteverify API.
 * The token must be valid, belong to the expected action, and score at least the configured minimum.
 */
class Recaptcha implements ValidationRule
{
    public const VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';

    public function __construct(public string $action) {}

    /**
     * reCAPTCHA is only enforced when both keys are configured, so local setups and tests keep working without keys.
     */
    public static function isEnabled(): bool
    {
        return filled(config('services.recaptcha.site_key')) && filled(config('services.recaptcha.secret_key'));
    }

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            $fail('Security check failed. Please refresh the page and try again.');

            return;
        }

        try {
            $response = Http::asForm()->timeout(5)->post(self::VERIFY_URL, [
                'secret' => config('services.recaptcha.secret_key'),
                'response' => $value,
                'remoteip' => request()->ip(),
            ]);
        } catch (ConnectionException $exception) {
            Log::warning('reCAPTCHA verification could not reach Google.', ['message' => $exception->getMessage()]);
            $fail('Could not run the security check. Please try again in a moment.');

            return;
        }

        /** @var array{success?: bool, score?: float, action?: string, hostname?: string, error-codes?: list<string>} $result */
        $result = $response->json() ?? [];
        $minimumScore = (float) config('services.recaptcha.min_score', 0.5);
        $passed = $response->successful()
            && ($result['success'] ?? false) === true
            && ($result['action'] ?? null) === $this->action
            && is_numeric($result['score'] ?? null)
            && (float) $result['score'] >= $minimumScore
            && $this->hostnameMatches($result['hostname'] ?? null);

        if (! $passed) {
            Log::info('reCAPTCHA rejected a submission.', [
                'action' => $this->action,
                'score' => $result['score'] ?? null,
                'returned_action' => $result['action'] ?? null,
                'hostname' => $result['hostname'] ?? null,
                'errors' => $result['error-codes'] ?? [],
            ]);

            $fail('We could not verify you are human. Please try again.');
        }
    }

    /**
     * The token must come from the host the form was served on. Google leaves the hostname out
     * only when the key's domain check is turned off, in which case there is nothing to compare.
     */
    protected function hostnameMatches(?string $hostname): bool
    {
        if (blank($hostname)) {
            return true;
        }

        return strcasecmp($hostname, request()->getHost()) === 0;
    }
}
