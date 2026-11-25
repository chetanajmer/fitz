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
