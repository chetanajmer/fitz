<?php

namespace App\Services\Ai;

use App\Models\PlatformSetting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * OpenRouter chat completions (OpenAI-compatible) for the AI progress module.
 *
 * Every request asks OpenRouter to route only to providers that do not store or train on
 * prompts (provider.data_collection = deny), as promised in the member's consent text.
 */
class OpenRouterClient
{
    /** Platform setting holding the key saved by the Super Admin, encrypted with the app key. */
    public const KEY_SETTING = 'ai_api_key';

    public function isConfigured(): bool
    {
        return filled($this->apiKey());
    }

    /** The key saved on the Super Admin's AI Coach page, otherwise OPENROUTER_API_KEY from .env. */
    public function apiKey(): ?string
    {
        return $this->savedKey() ?? (config('services.openrouter.key') ?: null);
    }

    /** Null when none is saved, or when it can no longer be decrypted (APP_KEY changed). */
    public function savedKey(): ?string
    {
        $stored = PlatformSetting::get(self::KEY_SETTING);

        try {
            return $stored ? Crypt::decryptString($stored) : null;
        } catch (DecryptException) {
            return null;
        }
    }

    public function saveKey(?string $key): void
    {
        PlatformSetting::put([self::KEY_SETTING => $key === null ? null : Crypt::encryptString($key)]);
    }

    /** Whether OpenRouter accepts the key. Throws when OpenRouter cannot be reached. */
    public function keyWorks(string $key): bool
    {
        $response = Http::withToken($key)->timeout(15)->get($this->url('/key'));

        if (in_array($response->status(), [401, 403], true)) {
            return false;
        }

        $response->throw();

        return true;
    }

    /**
     * Models that can do our reports: they read images and have a provider that keeps no data (the consent text
     * promises that) and supports the structured output and reasoning settings complete() requires. Cached for an hour. Throws when OpenRouter cannot be reached.
     *
     * @return list<string>
     */
    public function suitableModels(): array
    {
        return Cache::remember('openrouter_suitable_models', 3600, function () {
            $readsImages = collect(Http::timeout(15)->get($this->url('/models'))->throw()->json('data'))
                ->filter(fn (array $m) => in_array('image', $m['architecture']['input_modalities'] ?? [], true))
                ->pluck('id');
            $keepsNoData = collect(Http::timeout(15)->get($this->url('/endpoints/zdr'))->throw()->json('data'))
                ->filter(fn (array $e) => ! array_diff(['structured_outputs', 'reasoning'], $e['supported_parameters'] ?? []))
                ->pluck('model_id');

            return $readsImages->intersect($keepsNoData)->filter(fn (string $id) => ! str_ends_with($id, ':batch'))->sort()->values()->all();
        });
    }

    protected function url(string $path): string
    {
        return rtrim(config('services.openrouter.url'), '/').$path;
    }

    /**
     * Ask for an answer that follows a JSON schema. Cut-off or invalid answers throw.
     *
     * @param  string|list<array<string, mixed>>  $userContent  text, or OpenAI-style content parts (text + image_url)
     * @param  array<string, mixed>  $schema  JSON schema of the answer (every property required)
     * @return array{data: array<string, mixed>, model: string, input_tokens: ?int, output_tokens: ?int, cost_usd: ?float}
     */
    public function complete(string $system, string|array $userContent, string $schemaName, array $schema, int $maxTokens = 4000): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('OpenRouter is not configured (OPENROUTER_API_KEY).');
        }

        $response = Http::withToken($this->apiKey())
            ->withHeaders(['X-Title' => (string) PlatformSetting::get('platform_name')])
            ->timeout(75) // stays under the queue's retry_after (90 s), so a slow call is never run twice
            ->post($this->url('/chat/completions'), [
                'model' => (string) PlatformSetting::get('ai_model'),
                'max_tokens' => $maxTokens,
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $userContent],
                ],
                // Thinking counts towards max_tokens; a low effort leaves room for the answer.
                'reasoning' => ['effort' => 'low'],
                'response_format' => ['type' => 'json_schema', 'json_schema' => ['name' => $schemaName, 'strict' => true, 'schema' => $schema]],
                // The consent text promises providers that neither store nor train on the data.
                'provider' => ['data_collection' => 'deny', 'zdr' => true, 'require_parameters' => true],
                'usage' => ['include' => true],
            ]);

        $content = $response->json('choices.0.message.content');

        if ($response->failed() || ! is_string($content) || trim($content) === '') {
            $error = $response->json('error.message') ?? 'HTTP '.$response->status();

            throw new RuntimeException('OpenRouter request failed: '.mb_substr((string) $error, 0, 200));
        }

        $data = json_decode($content, true);

        if ($response->json('choices.0.finish_reason') === 'length' || ! is_array($data) || array_diff($schema['required'], array_keys($data))) {
            throw new RuntimeException('The AI answer was incomplete.');
        }

        return [
            'data' => $data,
            'model' => (string) ($response->json('model') ?? PlatformSetting::get('ai_model')),
            'input_tokens' => $response->json('usage.prompt_tokens'),
            'output_tokens' => $response->json('usage.completion_tokens'),
            'cost_usd' => $response->json('usage.cost'),
        ];
    }
}
