<?php

namespace App\Console\Commands;

use App\Models\Habit;
use App\Models\UserIdentity;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;

class HabitReminderStatus extends Command
{
    protected $signature = 'habits:reminders-status';

    protected $description = 'Show reminder configuration and the last scheduler execution';

    public function handle(): int
    {
        $this->line('UTC now: '.Carbon::now('UTC')->format('Y-m-d H:i:s'));
        $this->line('App timezone: '.config('app.timezone', 'UTC'));
        $this->line('Telegram bot configured: '.((string) config('telegram.bot_token') !== '' ? 'yes' : 'no'));
        $this->line('Enabled habit reminders: '.Habit::query()->where('reminder_enabled', true)->count());
        $this->line('Linked Telegram private chats: '.UserIdentity::query()
            ->where('provider', 'telegram')->whereNotNull('telegram_chat_id')->count());

        $path = storage_path('app/habit-reminders-last-run.json');

        if (! File::exists($path)) {
            $this->warn('Scheduler heartbeat: NEVER RECORDED. Check crontab and run php artisan schedule:run.');

            return self::SUCCESS;
        }

        $last = json_decode(File::get($path), true);

        if (! is_array($last) || ! isset($last['finished_at'])) {
            $this->warn('Scheduler heartbeat: unreadable.');

            return self::SUCCESS;
        }

        $lastAt = Carbon::parse($last['finished_at']);
        $this->line('Last scheduler run: '.$lastAt->toIso8601String());
        $this->line('Run status: '.($last['status'] ?? 'unknown'));
        $this->line('Reminders sent in last run: '.($last['sent'] ?? '-'));

        if ($lastAt->diffInMinutes(now()) > 3) {
            $this->warn('Last run is older than 3 minutes. Check crontab / system cron.');
        }

        if (($last['status'] ?? '') === 'failed') {
            $this->error((string) ($last['error'] ?? 'Unknown sender error'));
        }

        return self::SUCCESS;
    }
}
