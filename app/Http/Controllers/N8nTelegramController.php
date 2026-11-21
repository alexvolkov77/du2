<?php

namespace App\Http\Controllers;

use App\Models\Habit;
use App\Models\UserIdentity;
use App\Services\TelegramAccountLinker;
use App\Services\TelegramBot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class N8nTelegramController extends Controller
{
    public function __invoke(Request $request, TelegramBot $bot, TelegramAccountLinker $linker): JsonResponse
    {
        $provided = (string) $request->header('X-DU2-N8N-Key', '');
        abort_unless(hash_equals($bot->n8nBridgeKey(), $provided), 403);

        if (is_array($message = $request->input('message'))) {
            return response()->json($this->message($message, $linker));
        }

        if (is_array($callback = $request->input('callback_query'))) {
            return response()->json($this->callback($callback, $bot));
        }

        return response()->json(['kind' => 'ignored']);
    }

    private function message(array $message, TelegramAccountLinker $linker): array
    {
        $telegramId = (string) data_get($message, 'from.id', '');
        $chatId = (string) data_get($message, 'chat.id', '');
        $chatType = data_get($message, 'chat.type');
        $command = trim((string) ($message['text'] ?? ''));

        if (! ctype_digit($telegramId) || ! ctype_digit($chatId)
            || $telegramId !== $chatId || $chatType !== 'private') {
            return ['kind' => 'ignored'];
        }

        if (preg_match('/^\/start(?:@softskillsimprovement_bot)?\s+link_([0-9a-f]{48})$/i', $command, $matches)) {
            return $this->messageReply(
                $chatId,
                $linker->consume(strtolower($matches[1]), $telegramId, $chatId),
            );
        }

        if (! preg_match('/^\/du2(start|stop)(?:@softskillsimprovement_bot)?$/i', $command, $matches)) {
            return ['kind' => 'ignored'];
        }

        $identity = UserIdentity::query()
            ->where('provider', 'telegram')
            ->where('provider_user_id', $telegramId)
            ->first();

        if (strtolower($matches[1]) === 'stop') {
            $identity?->update(['telegram_chat_id' => null]);

            return $this->messageReply($chatId, 'Напоминания о привычках отключены. Для включения отправь /du2start.');
        }

        if (! $identity) {
            return $this->messageReply($chatId, 'Чтобы подключить напоминания, привяжи Telegram в кабинете https://lk.du2.ru/settings/telegram');
        }

        $identity->update(['telegram_chat_id' => $chatId]);

        return $this->messageReply($chatId, 'Напоминания подключены. Время можно выбрать для каждой привычки в трекере.');
    }

    private function callback(array $callback, TelegramBot $bot): array
    {
        $queryId = (string) ($callback['id'] ?? '');
        $telegramId = (string) data_get($callback, 'from.id', '');
        $chatId = (string) data_get($callback, 'message.chat.id', '');
        $chatType = data_get($callback, 'message.chat.type');

        if ($queryId === '' || ! ctype_digit($telegramId)
            || $telegramId !== $chatId || $chatType !== 'private') {
            return ['kind' => 'ignored'];
        }

        $identity = UserIdentity::query()
            ->where('provider', 'telegram')
            ->where('provider_user_id', $telegramId)
            ->where('telegram_chat_id', $chatId)
            ->first();

        $signed = $bot->callbackIsValid((string) ($callback['data'] ?? ''), $telegramId);
        $answer = 'Кнопка недействительна. Открой трекер.';

        if ($identity && $signed) {
            $habit = Habit::query()
                ->whereKey($signed['habit_id'])
                ->where('user_id', $identity->user_id)
                ->first();

            $today = Carbon::now($identity->user->timezone ?: 'UTC');

            if ($habit && $signed['date'] === $today->toDateString()
                && $habit->isScheduledOn($today)) {
                $checkin = $habit->checkins()->firstOrCreate(
                    ['local_date' => $signed['date']],
                    ['completed_at' => now()],
                );

                $answer = $checkin->wasRecentlyCreated
                    ? '✅ Привычка выполнена!'
                    : 'Привычка уже выполнена.';
            } else {
                $answer = 'Это напоминание уже неактуально.';
            }
        }

        return ['kind' => 'callback', 'query_id' => $queryId, 'text' => $answer];
    }

    private function messageReply(string $chatId, string $text): array
    {
        return ['kind' => 'message', 'chat_id' => $chatId, 'text' => $text];
    }
}
