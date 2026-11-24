<?php

namespace App\Services\Ai;

use App\Models\PlatformSetting;
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
    public function isConfigured(): bool
    {
        return filled(config('services.openrouter.key'));
    }

    /**
     * @param  string|list<array<string, mixed>>  $userContent  text, or OpenAI-style content parts (text + image_url)
     * @return array{content: string, model: string, input_tokens: ?int, output_tokens: ?int, cost_usd: ?float}
     */
    public function complete(string $system, string|array $userContent, int $maxTokens = 1200): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('OpenRouter is not configured (OPENROUTER_API_KEY).');
        }

        $response = Http::withToken(config('services.openrouter.key'))
            ->withHeaders(['X-Title' => (string) PlatformSetting::get('platform_name')])
            ->timeout(75) // stays under the queue's retry_after (90 s), so a slow call is never run twice
            ->post(rtrim(config('services.openrouter.url'), '/').'/chat/completions', [
                'model' => (string) PlatformSetting::get('ai_model'),
                'max_tokens' => $maxTokens,
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $userContent],
                ],
                'provider' => ['data_collection' => 'deny'],
                'usage' => ['include' => true],
            ]);

        $content = $response->json('choices.0.message.content');

        if ($response->failed() || ! is_string($content) || trim($content) === '') {
            $error = $response->json('error.message') ?? 'HTTP '.$response->status();

            throw new RuntimeException('OpenRouter request failed: '.mb_substr((string) $error, 0, 200));
        }

        return [
            'content' => trim($content),
            'model' => (string) ($response->json('model') ?? PlatformSetting::get('ai_model')),
            'input_tokens' => $response->json('usage.prompt_tokens'),
            'output_tokens' => $response->json('usage.completion_tokens'),
            'cost_usd' => $response->json('usage.cost'),
        ];
    }
}
