<?php

namespace App\Console\Commands;

use App\Services\TelegramBot;
use Illuminate\Console\Command;

class ShowN8nBridgeKey extends Command
{
    protected $signature = 'du2:n8n-bridge-key';

    protected $description = 'Print the dedicated key for the n8n DU2 bridge';

    public function handle(TelegramBot $bot): int
    {
        $this->line($bot->n8nBridgeKey());

        return self::SUCCESS;
    }
}
