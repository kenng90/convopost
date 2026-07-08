<?php

namespace App\Services\Platform;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenRouterService
{
    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     * @return array{content: string, model: string, usage: array<string, mixed>}
     */
    public function chatCompletion(
        string $apiKey,
        array $messages,
        string $model,
        float $temperature = 0.7,
        int $maxTokens = 4000,
        bool $jsonMode = false,
    ): array {
        $payload = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => $temperature,
            'max_tokens' => $maxTokens,
        ];

        if ($jsonMode) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        $response = Http::timeout(90)
            ->withHeaders([
                'Authorization' => 'Bearer '.$apiKey,
                'Content-Type' => 'application/json',
                'HTTP-Referer' => config('app.url'),
                'X-Title' => config('app.name', 'Convocon'),
            ])
            ->post('https://openrouter.ai/api/v1/chat/completions', $payload);

        if (! $response->successful()) {
            Log::error('OpenRouter API error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new \RuntimeException('OpenRouter API call failed: '.$response->status());
        }

        $data = $response->json();
        $content = $data['choices'][0]['message']['content'] ?? '';

        return [
            'content' => $content,
            'model' => $data['model'] ?? $model,
            'usage' => $data['usage'] ?? [],
        ];
    }

    /**
     * @return array<int, float>|null
     */
    public function createEmbedding(string $apiKey, string $text, string $model): ?array
    {
        $response = Http::timeout(60)
            ->withHeaders([
                'Authorization' => 'Bearer '.$apiKey,
                'Content-Type' => 'application/json',
                'HTTP-Referer' => config('app.url'),
                'X-Title' => config('app.name', 'Convocon'),
            ])
            ->post('https://openrouter.ai/api/v1/embeddings', [
                'input' => $text,
                'model' => $model,
            ]);

        if (! $response->successful()) {
            Log::error('OpenRouter embedding API error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new \RuntimeException('OpenRouter embedding API call failed: '.$response->status());
        }

        $data = $response->json();

        return $data['data'][0]['embedding'] ?? null;
    }
}
