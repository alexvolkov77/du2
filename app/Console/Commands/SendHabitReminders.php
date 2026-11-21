<?php

namespace App\Console\Commands;

use App\Services\HabitReminderSender;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendHabitReminders extends Command
{
    protected $signature = 'habits:send-reminders';

    protected $description = 'Send scheduled habit reminders through Telegram';

    public function handle(HabitReminderSender $sender): int
    {
        $startedAt = now()->toIso8601String();

        try {
            $sent = $sender->sendDue();
            $this->recordRun([
                'started_at' => $startedAt,
                'finished_at' => now()->toIso8601String(),
                'status' => 'ok',
                'sent' => $sent,
            ]);
            $this->info('Sent reminders: '.$sent);

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->recordRun([
                'started_at' => $startedAt,
                'finished_at' => now()->toIso8601String(),
                'status' => 'failed',
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    private function recordRun(array $data): void
    {
        try {
            $directory = storage_path('app');
            File::ensureDirectoryExists($directory);
            File::put(
                $directory.'/habit-reminders-last-run.json',
                json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            );
        } catch (Throwable $exception) {
            Log::warning('Could not record habit reminder scheduler heartbeat', [
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
