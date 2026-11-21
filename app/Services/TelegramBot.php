<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

final class TelegramBot
{
    public function secret(): string
    {
        return substr(hash_hmac('sha256', 'du2-telegram-webhook', (string) config('telegram.bot_token')), 0, 48);
    }

    /** @return array<string, mixed> */
    public function call(string $method, array $payload = []): array
    {
        $token = (string) config('telegram.bot_token');

        if ($token === '') {
            throw new RuntimeException('TELEGRAM_BOT_TOKEN is not configured.');
        }

        $response = Http::timeout(15)
            ->acceptJson()
            ->asJson()
            ->post('https://api.telegram.org/bot'.$token.'/'.$method, $payload);

        $data = $response->json();

        if (! $response->successful() || ! is_array($data) || ($data['ok'] ?? false) !== true) {
            throw new RuntimeException('Telegram API '.$method.' failed: '.(string) ($data['description'] ?? $response->status()));
        }

        return $data;
    }

    public function completionCallback(int $habitId, string $date, string $telegramId): string
    {
        $payload = 'done:'.$habitId.':'.$date;
        $signature = substr(hash_hmac('sha256', $payload.':'.$telegramId, (string) config('app.key')), 0, 16);

        return $payload.':'.$signature;
    }

    public function callbackIsValid(string $data, string $telegramId): ?array
    {
        if (! preg_match('/^done:([1-9][0-9]*):([0-9]{4}-[0-9]{2}-[0-9]{2}):([0-9a-f]{16})$/', $data, $matches)) {
            return null;
        }

        $habitId = (int) $matches[1];
        $date = $matches[2];
        $expected = $this->completionCallback($habitId, $date, $telegramId);

        if (! hash_equals($expected, $data)) {
            return null;
        }

        return ['habit_id' => $habitId, 'date' => $date];
    }
}
