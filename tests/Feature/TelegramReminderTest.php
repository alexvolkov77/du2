<?php

namespace Tests\Feature;

use App\Models\Habit;
use App\Models\User;
use App\Models\UserIdentity;
use App\Services\HabitReminderSender;
use App\Services\TelegramBot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramReminderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('telegram.bot_token', '123:test-token');
        config()->set('app.key', 'base64:'.base64_encode(str_repeat('x', 32)));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_reminder_settings_are_user_scoped(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $habit = Habit::create(['user_id' => $owner->id, 'name' => 'Читать']);

        $this->actingAs($outsider)->patch("/habits/{$habit->id}/reminder", [
            'enabled' => true, 'time' => '09:30',
        ])->assertNotFound();

        $this->actingAs($owner)->patch("/habits/{$habit->id}/reminder", [
            'enabled' => true, 'time' => '09:30', 'timezone' => 'Europe/Warsaw',
        ])->assertRedirect();

        $this->assertTrue($habit->fresh()->reminder_enabled);
        $this->assertSame('09:30', $habit->fresh()->reminder_time);
        $this->assertSame('Europe/Warsaw', $owner->fresh()->timezone);
    }

    public function test_sends_once_to_linked_private_chat_and_skips_completed_habits(): void
    {
        Carbon::setTestNow('2026-11-23 19:00:00 UTC'); // Mon, 20:00 Warsaw
        Http::fake(['https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => []])]);

        $user = User::factory()->create(['timezone' => 'Europe/Warsaw']);
        UserIdentity::create([
            'user_id' => $user->id,
            'provider' => 'telegram',
            'provider_user_id' => '12001',
            'telegram_chat_id' => '12001',
        ]);
        $habit = Habit::create([
            'name' => 'Читать', 'user_id' => $user->id,
            'schedule_type' => 'weekly', 'schedule_days' => [1, 3],
            'reminder_enabled' => true, 'reminder_time' => '20:00',
        ]);

        $sender = app(HabitReminderSender::class);
        $this->assertSame(1, $sender->sendDue());
        $this->assertSame(0, $sender->sendDue());
        Http::assertSentCount(1);
        $this->assertSame('2026-11-23', $habit->fresh()->last_reminded_on->toDateString());
    }

    public function test_webhook_rejects_invalid_secret_and_links_start(): void
    {
        Http::fake(['https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => []])]);

        $user = User::factory()->create();
        $identity = UserIdentity::create([
            'user_id' => $user->id, 'provider' => 'telegram', 'provider_user_id' => '12001',
        ]);

        $payload = ['message' => [
            'text' => '/start',
            'from' => ['id' => 12001],
            'chat' => ['id' => 12001, 'type' => 'private'],
        ]];

        $this->postJson('/telegram/webhook', $payload)->assertForbidden();
        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', app(TelegramBot::class)->secret())
            ->postJson('/telegram/webhook', $payload)->assertOk();

        $this->assertSame('12001', $identity->fresh()->telegram_chat_id);
    }

    public function test_authenticated_callback_marks_only_own_habit(): void
    {
        Carbon::setTestNow('2026-11-23 19:00:00 UTC');
        Http::fake(['https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => []])]);

        $user = User::factory()->create(['timezone' => 'Europe/Warsaw']);
        UserIdentity::create([
            'user_id' => $user->id,
            'provider' => 'telegram',
            'provider_user_id' => '12001',
            'telegram_chat_id' => '12001',
        ]);
        $habit = Habit::create([
            'name' => 'Читать', 'user_id' => $user->id,
            'schedule_type' => 'weekly', 'schedule_days' => [1, 3],
        ]);

        $callback = [
            'callback_query' => [
                'id' => 'cq-123',
                'from' => ['id' => 12001],
                'data' => app(TelegramBot::class)->completionCallback($habit->id, '2026-11-23', '12001'),
                'message' => ['message_id' => 25, 'chat' => ['id' => 12001, 'type' => 'private']],
            ],
        ];

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', app(TelegramBot::class)->secret())
            ->postJson('/telegram/webhook', $callback)->assertOk();
        $this->assertDatabaseCount('habit_checkins', 1);

        $this->postJson('/telegram/webhook', $callback)->assertOk();
        $this->assertDatabaseCount('habit_checkins', 1);
    }
}
