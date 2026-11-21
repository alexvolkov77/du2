<?php

namespace App\Console\Commands;

use App\Services\TelegramBot;
use Illuminate\Console\Command;
use Throwable;

class ConfigureTelegramWebhook extends Command
{
    protected $signature = 'telegram:configure-webhook {--force : Replace an existing webhook at a different URL}';

    protected $description = 'Register DU2 Telegram webhook without replacing an unrelated endpoint';

    public function handle(TelegramBot $bot): int
    {
        if ((string) config('telegram.bot_token') === '') {
            $this->error('TELEGRAM_BOT_TOKEN is not configured.');

            return self::FAILURE;
        }

        $url = rtrim((string) config('app.url'), '/').'/telegram/webhook';

        try {
            $info = $bot->call('getWebhookInfo');
            $current = (string) data_get($info, 'result.url', '');

            if ($current !== '' && $current !== $url && ! $this->option('force')) {
                $this->error('Another webhook is already configured: '.$current);
                $this->warn('DU2 will not replace it automatically. Check the owner before running with --force.');

                return self::FAILURE;
            }

            $bot->call('setWebhook', [
                'url' => $url,
                'secret_token' => $bot->secret(),
                'allowed_updates' => ['message', 'callback_query'],
                'drop_pending_updates' => false,
            ]);

            $this->info('Telegram webhook configured: '.$url);

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('Telegram webhook setup failed: '.$exception->getMessage());

            return self::FAILURE;
        }
    }
}
