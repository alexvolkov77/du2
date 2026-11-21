<?php

namespace App\Http\Controllers;

use App\Models\Habit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class HabitController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'timezone' => ['sometimes', 'timezone'],
        ]);

        $user = $request->user();
        $timezone = $validated['timezone'] ?? ($user->timezone ?: 'UTC');

        if ($user->timezone !== $timezone) {
            $user->forceFill(['timezone' => $timezone])->save();
        }

        $today = Carbon::now($timezone)->startOfDay();
        $dates = collect(range(13, 0))
            ->map(fn (int $daysAgo) => $today->copy()->subDays($daysAgo));
        $from = $dates->first()->toDateString();
        $to = $today->toDateString();

        $habits = Habit::query()
            ->where('user_id', $user->id)
            ->with(['checkins' => fn ($query) => $query->whereBetween('local_date', [$from, $to])])
            ->latest()
            ->get()
            ->map(function (Habit $habit) use ($dates, $timezone) {
                $completedDates = array_fill_keys(
                    $habit->checkins->map(fn ($checkin) => $checkin->local_date->toDateString())->all(),
                    true,
                );

                $createdDate = $habit->created_at->copy()->setTimezone($timezone)->toDateString();

                $history = $dates->map(function (Carbon $date) use ($habit, $createdDate, $completedDates) {
                    $day = $date->toDateString();

                    return [
                        'date' => $day,
                        'weekday' => (int) $date->dayOfWeekIso,
                        'scheduled' => $day >= $createdDate && $habit->isScheduledOn($date),
                        'completed' => isset($completedDates[$day]),
                    ];
                })->values();

                $week = $history->slice(-7);

                return [
                    'id' => $habit->id,
                    'name' => $habit->name,
                    'schedule_type' => $habit->schedule_type,
                    'schedule_days' => $habit->schedule_days ?? [],
                    'scheduled_today' => $history->last()['scheduled'],
                    'completed_today' => $history->last()['completed'],
                    'completed_7_days' => $week->where('completed', true)->count(),
                    'scheduled_7_days' => $week->where('scheduled', true)->count(),
                    'history' => $history,
                ];
            });

        return Inertia::render('Dashboard', [
            'habits' => $habits,
            'today' => $to,
            'timezone' => $timezone,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedHabit($request);

        // Establish the local calendar before creating the habit.
        $this->setTimezone($request);

        Habit::create([
            ...$data,
            'user_id' => $request->user()->id,
        ]);

        return back();
    }

    public function update(Request $request, Habit $habit): RedirectResponse
    {
        $this->assertOwnership($request, $habit);
        $habit->update($this->validatedHabit($request));

        return back();
    }

    public function destroy(Request $request, Habit $habit): RedirectResponse
    {
        $this->assertOwnership($request, $habit);
        $habit->delete();

        return back();
    }

    public function complete(Request $request, Habit $habit): RedirectResponse
    {
        $this->assertOwnership($request, $habit);
        $day = $this->todayForUser($request);

        if (! $habit->isScheduledOn(Carbon::parse($day, $request->user()->timezone))) {
            throw ValidationException::withMessages([
                'schedule' => 'Сегодня эта привычка не запланирована.',
            ]);
        }

        $habit->checkins()->firstOrCreate(
            ['local_date' => $day],
            ['completed_at' => now()],
        );

        return back();
    }

    public function uncomplete(Request $request, Habit $habit): RedirectResponse
    {
        $this->assertOwnership($request, $habit);
        $habit->checkins()->where('local_date', $this->todayForUser($request))->delete();

        return back();
    }

    private function validatedHabit(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'schedule_type' => ['sometimes', 'in:daily,weekly'],
            'schedule_days' => ['sometimes', 'array'],
            'schedule_days.*' => ['integer', 'between:1,7', 'distinct'],
        ]);

        $data['name'] = trim($data['name']);
        if ($data['name'] === '') {
            throw ValidationException::withMessages(['name' => 'Укажи название привычки.']);
        }

        $data['schedule_type'] = $data['schedule_type'] ?? 'daily';

        $days = $data['schedule_type'] === 'weekly'
            ? array_map('intval', $data['schedule_days'] ?? [])
            : [];

        if ($data['schedule_type'] === 'weekly' && count($days) === 0) {
            throw ValidationException::withMessages(['schedule_days' => 'Выбери хотя бы один день недели.']);
        }

        sort($days);
        $data['schedule_days'] = $days;

        return $data;
    }

    private function assertOwnership(Request $request, Habit $habit): void
    {
        abort_unless($habit->user_id === $request->user()->id, 404);
    }

    private function setTimezone(Request $request): string
    {
        $data = $request->validate(['timezone' => ['sometimes', 'timezone']]);
        $user = $request->user();
        $timezone = $data['timezone'] ?? ($user->timezone ?: 'UTC');

        if ($user->timezone !== $timezone) {
            $user->forceFill(['timezone' => $timezone])->save();
        }

        return $timezone;
    }

    private function todayForUser(Request $request): string
    {
        return Carbon::now($this->setTimezone($request))->toDateString();
    }
}
