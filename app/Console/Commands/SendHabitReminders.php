<?php

namespace App\Console\Commands;

use App\Services\HabitReminderSender;
use Illuminate\Console\Command;

class SendHabitReminders extends Command
{
    protected $signature = 'habits:send-reminders';

    protected $description = 'Send scheduled habit reminders through Telegram';

    public function handle(HabitReminderSender $sender): int
    {
        $sent = $sender->sendDue();
        $this->info('Sent reminders: '.$sent);

        return self::SUCCESS;
    }
}
