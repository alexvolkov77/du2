<?php

namespace App\Services;

use App\Models\TelegramLinkToken;
use App\Models\UserIdentity;
use Illuminate\Support\Facades\DB;

final class TelegramAccountLinker
{
    public function consume(string $token, string $telegramId, string $chatId): string
    {
        if (! preg_match('/^[0-9a-f]{48}$/', $token) || $telegramId === '' || $chatId !== $telegramId) {
            return 'Ссылка для привязки недействительна.';
        }

        return DB::transaction(function () use ($token, $telegramId, $chatId): string {
            $link = TelegramLinkToken::query()
                ->where('token_hash', hash('sha256', $token))
                ->lockForUpdate()
                ->first();

            if (! $link || $link->used_at || $link->expires_at->isPast()) {
                return 'Ссылка истекла или уже использована. Создай новую в кабинете.';
            }

            $ownerIdentity = UserIdentity::query()
                ->where('provider', 'telegram')
                ->where('user_id', $link->user_id)
                ->first();

            if ($ownerIdentity && $ownerIdentity->provider_user_id !== $telegramId) {
                return 'К этому аккаунту уже привязан другой Telegram.';
            }

            $telegramIdentity = UserIdentity::query()
                ->where('provider', 'telegram')
                ->where('provider_user_id', $telegramId)
                ->first();

            if ($telegramIdentity && $telegramIdentity->user_id !== $link->user_id) {
                return 'Этот Telegram уже привязан к другому аккаунту.';
            }

            if ($ownerIdentity) {
                $ownerIdentity->update(['telegram_chat_id' => $chatId]);
            } else {
                UserIdentity::create([
                    'user_id' => $link->user_id,
                    'provider' => 'telegram',
                    'provider_user_id' => $telegramId,
                    'telegram_chat_id' => $chatId,
                ]);
            }

            $link->update(['used_at' => now()]);
            TelegramLinkToken::query()->where('user_id', $link->user_id)->whereNull('used_at')->delete();

            return '✅ Telegram привязан! Привычки доступны на сайте и в Mini App. Напоминания тоже подключены.';
        });
    }
}
