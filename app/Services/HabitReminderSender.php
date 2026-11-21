<?php

namespace App\Services;

use App\Models\Habit;
use App\Models\UserIdentity;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

final class HabitReminderSender
{
    public function __construct(private readonly TelegramBot $bot)
    {
    }

    public function sendDue(): int
    {
        $sent = 0;

        Habit::query()
            ->where('reminder_enabled', true)
            ->with('user')
            ->chunkById(100, function ($habits) use (&$sent): void {
                foreach ($habits as $habit) {
                    $user = $habit->user;
                    if (! $user) {
                        continue;
                    }

                    try {
                        $now = Carbon::now($user->timezone ?: 'UTC');
                    } catch (Throwable $exception) {
                        Log::warning('Invalid timezone for habit reminder', ['user_id' => $user->id]);
                        continue;
                    }

                    $date = $now->toDateString();

                    if ($now->format('H:i') !== $habit->reminder_time
                        || $habit->created_at->setTimezone($now->timezone)->toDateString() > $date
                        || ! $habit->isScheduledOn($now)
                        || $habit->checkins()->where('local_date', $date)->exists()) {
                        continue;
                    }

                    $identity = UserIdentity::query()
                        ->where('user_id', $user->id)
                        ->where('provider', 'telegram')
                        ->whereNotNull('telegram_chat_id')
                        ->first();

                    if (! $identity) {
                        continue;
                    }

                    // Atomic claim prevents duplicate messages when cron overlaps.
                    $claimed = Habit::query()
                        ->whereKey($habit->id)
                        ->where('reminder_enabled', true)
                        ->where(function ($query) use ($date): void {
                            $query->whereNull('last_reminded_on')
                                ->orWhere('last_reminded_on', '!=', $date);
                        })
                        ->update(['last_reminded_on' => $date]);

                    if (! $claimed) {
                        continue;
                    }

                    try {
                        $this->bot->call('sendMessage', [
                            'chat_id' => $identity->telegram_chat_id,
                            'text' => 'Пора выполнить привычку: '.$habit->name,
                            'reply_markup' => [
                                'inline_keyboard' => [[
                                    [
                                        'text' => '✅ Выполнено',
                                        'callback_data' => $this->bot->completionCallback(
                                            $habit->id, $date, (string) $identity->provider_user_id,
                                        ),
                                    ],
                                ]],
                            ],
                        ]);
                        $sent++;
                    } catch (Throwable $exception) {
                        // Allow a retry on a later invocation during the matching minute.
                        Habit::query()->whereKey($habit->id)->where('last_reminded_on', $date)
                            ->update(['last_reminded_on' => null]);

                        Log::warning('Habit Telegram reminder failed', [
                            'habit_id' => $habit->id,
                            'message' => $exception->getMessage(),
                        ]);
                    }
                }
            });

        return $sent;
    }
}
