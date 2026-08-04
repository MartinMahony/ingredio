<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

#[Signature('scan:test-key')]
#[Description('Send a minimal request to Gemini to verify the API key, model, and quota.')]
class TestGeminiKey extends Command
{
    public function handle(): int
    {
        $apiKey = (string) config('services.gemini.key');
        $model = (string) config('scanning.model');
        $baseUrl = rtrim((string) config('scanning.gemini.base_url'), '/');

        if (trim($apiKey) === '') {
            $this->error('GEMINI_API_KEY is not set.');

            return self::FAILURE;
        }

        $this->line("Model:    {$model}");
        $this->line('Endpoint: '.$baseUrl."/models/{$model}:generateContent");
        $this->line('Key:      '.substr($apiKey, 0, 6).'…'.substr($apiKey, -4));
        $this->newLine();

        try {
            $response = Http::timeout((int) config('scanning.gemini.timeout'))
                ->withHeaders(['x-goog-api-key' => $apiKey])
                ->acceptJson()
                ->post($baseUrl."/models/{$model}:generateContent", [
                    'contents' => [['parts' => [['text' => 'Reply with the single word: pong']]]],
                ]);
        } catch (ConnectionException $e) {
            $this->error('Connection failed: '.$e->getMessage());

            return self::FAILURE;
        }

        if ($response->failed()) {
            $this->error("Gemini returned HTTP {$response->status()}.");
            $this->line($response->json('error.status') ?? '');
            $this->line((string) $response->json('error.message'));

            return self::FAILURE;
        }

        $text = trim((string) $response->json('candidates.0.content.parts.0.text'));
        $tokens = $response->json('usageMetadata.totalTokenCount');

        $this->info('Success! Gemini accepted the request.');
        $this->line('Reply:  '.$text);
        $this->line('Tokens: '.($tokens ?? 'n/a'));

        return self::SUCCESS;
    }
}
