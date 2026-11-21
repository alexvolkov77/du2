# DU2 0.4.2 — n8n Telegram bridge

Входящие Telegram updates принимает только существующий сценарий n8n «Клубы softskillsimprovement_bot telegram trigger ▫» (workflow 124). Laravel больше не регистрирует Telegram webhook и не меняет webhook при deploy.

n8n передаёт приватные:
- `/start link_<token>` — привязка/объединение аккаунтов;
- `/du2start` — подключение личного чата к напоминаниям;
- `/du2stop` — отключение напоминаний;
- `done:<habit_id>:<YYYY-MM-DD>:<HMAC>` — завершение привычки по кнопке.

Все остальные команды и callback продолжают обрабатываться прежними ветками workflow 124.

n8n делает POST `https://lk.du2.ru/api/integrations/n8n/telegram` с JSON оригинального Telegram update и заголовком `X-DU2-N8N-Key`. Laravel проверяет ключ и возвращает `{kind:'message',chat_id,text}` или `{kind:'callback',query_id,text}`. Ответ отправляет n8n через Telegram credential «Бот tg softskillsimprovement_bot».

## Настройка общего ключа

В Laravel используется производный от APP_KEY отдельный 64-символьный ключ. После `bash ~/du2-app/deploy.sh` выполнить:

```bash
cd ~/du2-app && php artisan du2:n8n-bridge-key
```

Скопировать результат в n8n Settings → Variables → `DU2_N8N_SECRET`. В HTTP Request узле заголовок использует `{{ $vars.DU2_N8N_SECRET }}`.

После настройки переменной опубликовать новую версию workflow 124 и повторно активировать Telegram Trigger в n8n, чтобы Telegram webhook снова указывал на n8n. Проверить привязку, кнопку выполнения и старые команды клуба.

Cron Laravel `habits:send-reminders` раз в минуту продолжает отправлять только исходящие уведомления через Telegram Bot API. Кнопки-ответы всегда обрабатывает n8n.
