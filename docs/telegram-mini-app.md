# Telegram Mini App v0.2

Bot: [@softskillsimprovement_bot](https://t.me/softskillsimprovement_bot)

Mini App URL (BotFather /setmenubutton): https://lk.du2.ru/telegram

## One-time server setup

Set the bot token in the server's existing Laravel .env:

```dotenv
TELEGRAM_BOT_TOKEN=<token received from BotFather>
```

The token must be configured locally on the server. No token is committed to GitHub.

Run the normal deployment command:

```bash
bash ~/du2-app/deploy.sh
```

This updates the code, builds the frontend, migrates the database and refreshes the Laravel config cache.

## User journeys

- New Telegram user: open the bot Mini App, click **Начать через Telegram**. Creates a Laravel user and identity.
- Existing web user: open the bot Mini App, select **Уже есть аккаунт**, sign in with email/password, then click **Привязать Telegram**. Reuses existing web user and habits.
- Returning linked Telegram user: opening Mini App logs into the same account automatically.

The backend verifies Telegram.WebApp.initData, including hash and auth_date, before identifying the user. It never trusts initDataUnsafe.

## Test

- Open the bot and launch Mini App.
- Link an existing web user and check habits remain.
- Close and reopen: the same user and habits should appear.
- Mark a habit completed; confirm the web cabinet sees the same mark.

## Rollback

Revert the Git commit and run deploy.sh again. The user_identities migration can remain safely present, as it does not modify the existing habits data.
