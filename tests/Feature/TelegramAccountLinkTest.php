<?php

namespace Tests\Feature;

use App\Models\TelegramLinkToken;
use App\Models\User;
use App\Models\UserIdentity;
use App\Services\TelegramBot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramAccountLinkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('telegram.bot_token', '123:test-token');
        config()->set('telegram.bot_username', 'softskillsimprovement_bot');
        Http::fake(['https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => []])]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function issueLink(User $user): string
    {
        $response = $this->actingAs($user)->post('/settings/telegram/link');
        $response->assertRedirect('/settings/telegram');
        $response->assertSessionHas('telegram_link_url');

        $url = (string) session('telegram_link_url');
        parse_str((string) parse_url($url, PHP_URL_QUERY), $params);

        $this->assertStringStartsWith('link_', $params['start']);
        $this->assertSame(53, strlen($params['start']));

        return substr($params['start'], 5);
    }

    private function sendStart(string $token, string $telegramId): void
    {
        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', app(TelegramBot::class)->secret())
            ->postJson('/telegram/webhook', [
                'message' => [
                    'text' => '/start link_'.$token,
                    'from' => ['id' => (int) $telegramId],
                    'chat' => ['id' => (int) $telegramId, 'type' => 'private'],
                ],
            ])->assertOk();
    }

    public function test_only_authenticated_user_can_create_link(): void
    {
        $this->post('/settings/telegram/link')->assertRedirect('/login');
    }

    public function test_email_account_is_linked_by_one_time_telegram_start(): void
    {
        $user = User::factory()->create();
        $token = $this->issueLink($user);

        $this->assertDatabaseHas('telegram_link_tokens', [
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $token),
        ]);

        $this->sendStart($token, '12001');

        $this->assertDatabaseHas('user_identities', [
            'user_id' => $user->id,
            'provider' => 'telegram',
            'provider_user_id' => '12001',
            'telegram_chat_id' => '12001',
        ]);
        $this->assertNotNull(TelegramLinkToken::firstOrFail()->used_at);

        $this->sendStart($token, '12002');
        $this->assertDatabaseMissing('user_identities', ['provider_user_id' => '12002']);

        $this->actingAs($user)->get('/settings/telegram')->assertOk();
    }

    public function test_expired_link_cannot_be_used(): void
    {
        Carbon::setTestNow('2026-11-21 12:00:00 UTC');
        $user = User::factory()->create();
        $token = $this->issueLink($user);

        Carbon::setTestNow('2026-11-21 12:11:00 UTC');
        $this->sendStart($token, '12001');

        $this->assertDatabaseCount('user_identities', 0);
    }

    public function test_telegram_linked_to_another_user_is_not_taken_over(): void
    {
        $emailUser = User::factory()->create();
        $otherUser = User::factory()->create();

        UserIdentity::create([
            'user_id' => $otherUser->id,
            'provider' => 'telegram',
            'provider_user_id' => '12001',
            'telegram_chat_id' => '12001',
        ]);

        $token = $this->issueLink($emailUser);
        $this->sendStart($token, '12001');

        $this->assertDatabaseHas('user_identities', [
            'user_id' => $otherUser->id,
            'provider_user_id' => '12001',
        ]);
        $this->assertDatabaseMissing('user_identities', [
            'user_id' => $emailUser->id,
            'provider_user_id' => '12001',
        ]);
    }

    public function test_new_link_invalidates_the_previous_one(): void
    {
        $user = User::factory()->create();
        $old = $this->issueLink($user);
        $new = $this->issueLink($user);

        $this->sendStart($old, '12001');
        $this->assertDatabaseCount('user_identities', 0);

        $this->sendStart($new, '12001');
        $this->assertDatabaseCount('user_identities', 1);
    }
}
