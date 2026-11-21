<?php

namespace App\Services;

use App\Models\Habit;
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

            $merged = false;

            if ($telegramIdentity && $telegramIdentity->user_id !== $link->user_id) {
                $oldUser = $telegramIdentity->user;
                $temporaryEmail = 'telegram-'.$telegramId.'@users.lk.du2.invalid';

                // Only accounts automatically created by the Telegram login flow may be merged.
                if (! $oldUser || $oldUser->email !== $temporaryEmail
                    || UserIdentity::query()
                        ->where('user_id', $oldUser->id)
                        ->where('id', '!=', $telegramIdentity->id)
                        ->exists()) {
                    return 'Этот Telegram уже привязан к другому аккаунту.';
                }

                // Move existing habits and their check-ins to the verified email account.
                Habit::query()->where('user_id', $oldUser->id)->update(['user_id' => $link->user_id]);

                $telegramIdentity->update([
                    'user_id' => $link->user_id,
                    'telegram_chat_id' => $chatId,
                ]);

                $oldUser->delete();
                $merged = true;
            }

            if ($ownerIdentity) {
                $ownerIdentity->update(['telegram_chat_id' => $chatId]);
            } elseif (! $merged) {
                UserIdentity::create([
                    'user_id' => $link->user_id,
                    'provider' => 'telegram',
                    'provider_user_id' => $telegramId,
                    'telegram_chat_id' => $chatId,
                ]);
            }

            $link->update(['used_at' => now()]);
            TelegramLinkToken::query()->where('user_id', $link->user_id)->whereNull('used_at')->delete();

            return $merged
                ? '✅ Аккаунты объединены! Привычки из Telegram перенесены в email-аккаунт. Напоминания подключены.'
                : '✅ Telegram привязан! Привычки доступны на сайте и в Mini App. Напоминания подключены.';
        });
    }
}
