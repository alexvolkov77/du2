<?php

namespace Tests\Feature;

use App\Models\Habit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class HabitScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_daily_habit_is_default_for_existing_records(): void
    {
        $habit = Habit::create(['name' => 'Старая привычка', 'user_id' => User::factory()->create()->id]);
        $this->assertTrue($habit->fresh()->isScheduledOn(Carbon::parse('2026-11-24')));
        $this->assertSame('daily', $habit->fresh()->schedule_type);
    }

    public function test_weekly_habit_only_checks_in_on_selected_days(): void
    {
        Carbon::setTestNow('2026-11-23 12:00:00 UTC'); // Monday
        $user = User::factory()->create();
        $this->actingAs($user)->post('/habits', [
            'name' => 'Тренировка',
            'schedule_type' => 'weekly',
            'schedule_days' => [1, 3],
            'timezone' => 'Europe/Warsaw',
        ])->assertRedirect();

        $habit = Habit::where('user_id', $user->id)->firstOrFail();
        $this->assertSame([1, 3], $habit->schedule_days);
        $this->actingAs($user)->post("/habits/{$habit->id}/complete", [
            'timezone' => 'Europe/Warsaw',
        ])->assertRedirect();
        $this->assertDatabaseCount('habit_checkins', 1);

        Carbon::setTestNow('2026-11-24 12:00:00 UTC'); // Tuesday
        $this->actingAs($user)->post("/habits/{$habit->id}/complete", [
            'timezone' => 'Europe/Warsaw',
        ])->assertSessionHasErrors('schedule');
        $this->assertDatabaseCount('habit_checkins', 1);
    }

    public function test_weekly_schedule_requires_a_day(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/habits', [
            'name' => 'Тренировка',
            'schedule_type' => 'weekly',
            'schedule_days' => [],
            'timezone' => 'Europe/Warsaw',
        ])->assertSessionHasErrors('schedule_days');

        $this->assertDatabaseCount('habits', 0);
    }

    public function test_schedule_can_be_edited_but_not_by_another_user(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $habit = Habit::create(['name' => 'Чтение', 'user_id' => $owner->id]);

        $this->actingAs($stranger)->patch("/habits/{$habit->id}", [
            'name' => 'Другое', 'schedule_type' => 'weekly', 'schedule_days' => [2],
        ])->assertNotFound();

        $this->actingAs($owner)->patch("/habits/{$habit->id}", [
            'name' => 'Чтение вечером', 'schedule_type' => 'weekly', 'schedule_days' => [2, 4],
        ])->assertRedirect();

        $this->assertSame([2, 4], $habit->fresh()->schedule_days);
        $this->assertSame('Чтение вечером', $habit->fresh()->name);
    }
}
