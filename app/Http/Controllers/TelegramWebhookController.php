<?php

namespace App\Http\Controllers;

use App\Models\Habit;
use App\Models\UserIdentity;
use App\Services\TelegramBot;
use App\Services\TelegramAccountLinker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

class TelegramWebhookController extends Controller
{
    public function handle(Request $request, TelegramBot $bot): JsonResponse
    {
        $provided = (string) $request->header('X-Telegram-Bot-Api-Secret-Token', '');

        abort_unless(
            (string) config('telegram.bot_token') !== '' && hash_equals($bot->secret(), $provided),
            403,
        );

        $message = $request->input('message');
        $callback = $request->input('callback_query');

        if (is_array($message)) {
            $this->handleMessage($message, $bot);
        } elseif (is_array($callback)) {
            $this->handleCallback($callback, $bot);
        }

        return response()->json(['ok' => true]);
    }

    private function handleMessage(array $message, TelegramBot $bot): void
    {
        $fromId = (string) data_get($message, 'from.id', '');
        $chatId = (string) data_get($message, 'chat.id', '');
        $command = (string) data_get($message, 'text', '');

        if ($fromId === '' || $chatId === '' || data_get($message, 'chat.type') !== 'private') {
            return;
        }

        if (! preg_match('/^\/(start|stop)(?:@softskillsimprovement_bot)?(?:\s|$)/i', $command, $matches)) {
            return;
        }

        if (preg_match('/^\\/start(?:@softskillsimprovement_bot)?\\s+link_([0-9a-f]{48})\\s*$/i', $command, $tokenMatches)) {
            $result = app(TelegramAccountLinker::class)->consume(strtolower($tokenMatches[1]), $fromId, $chatId);

            $this->safeCall($bot, 'sendMessage', [
                'chat_id' => $chatId,
                'text' => $result,
                'reply_markup' => ['inline_keyboard' => [[
                    ['text' => 'Открыть трекер', 'url' => 'https://lk.du2.ru/telegram'],
                ]]],
            ]);

            return;
        }

        $identity = UserIdentity::query()
            ->where('provider', 'telegram')
            ->where('provider_user_id', $fromId)
            ->first();

        if (strtolower($matches[1]) === 'stop') {
            if ($identity) {
                $identity->update(['telegram_chat_id' => null]);
            }

            $this->safeCall($bot, 'sendMessage', [
                'chat_id' => $chatId,
                'text' => 'Напоминания остановлены. Чтобы включить их снова, отправь /start.',
            ]);

            return;
        }

        if ($identity) {
            $identity->update(['telegram_chat_id' => $chatId]);
            $text = 'Напоминания подключены. Выбери время для нужных привычек в трекере.';
        } else {
            $text = 'Открой трекер, войди через Telegram или привяжи существующий аккаунт, затем отправь /start ещё раз.';
        }

        $this->safeCall($bot, 'sendMessage', [
            'chat_id' => $chatId,
            'text' => $text,
            'reply_markup' => [
                'inline_keyboard' => [[
                    ['text' => 'Открыть трекер', 'url' => 'https://lk.du2.ru/telegram'],
                ]],
            ],
        ]);
    }

    private function handleCallback(array $callback, TelegramBot $bot): void
    {
        $callbackId = (string) ($callback['id'] ?? '');
        $fromId = (string) data_get($callback, 'from.id', '');
        $chatId = (string) data_get($callback, 'message.chat.id', '');

        if ($callbackId === '' || $fromId === '' || $chatId === ''
            || data_get($callback, 'message.chat.type') !== 'private') {
            return;
        }

        $identity = UserIdentity::query()
            ->where('provider', 'telegram')
            ->where('provider_user_id', $fromId)
            ->where('telegram_chat_id', $chatId)
            ->first();

        $signed = $bot->callbackIsValid((string) ($callback['data'] ?? ''), $fromId);
        $answer = 'Кнопка недействительна. Открой трекер.';

        if ($identity && $signed) {
            $habit = Habit::query()
                ->whereKey($signed['habit_id'])
                ->where('user_id', $identity->user_id)
                ->first();

            $timezone = $identity->user->timezone ?: 'UTC';
            $today = Carbon::now($timezone);

            if ($habit && $signed['date'] === $today->toDateString()
                && $habit->isScheduledOn($today)) {
                $habit->checkins()->firstOrCreate(
                    ['local_date' => $signed['date']],
                    ['completed_at' => now()],
                );

                $answer = '✅ Привычка выполнена!';

                $messageId = data_get($callback, 'message.message_id');
                if ($messageId) {
                    $this->safeCall($bot, 'editMessageReplyMarkup', [
                        'chat_id' => $chatId,
                        'message_id' => $messageId,
                        'reply_markup' => ['inline_keyboard' => []],
                    ]);
                }
            } else {
                $answer = 'Это напоминание уже неактуально.';
            }
        }

        $this->safeCall($bot, 'answerCallbackQuery', [
            'callback_query_id' => $callbackId,
            'text' => $answer,
            'show_alert' => false,
        ]);
    }

    private function safeCall(TelegramBot $bot, string $method, array $payload): void
    {
        try {
            $bot->call($method, $payload);
        } catch (Throwable $exception) {
            Log::warning('Telegram webhook reply failed', [
                'method' => $method,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
