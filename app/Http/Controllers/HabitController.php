<?php

namespace App\Http\Controllers;

use App\Models\Habit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class HabitController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $timezone = $user->timezone ?: 'UTC';
        $today = Carbon::now($timezone)->toDateString();
        $from = Carbon::now($timezone)->subDays(6)->toDateString();

        $habits = Habit::query()
            ->where('user_id', $user->id)
            ->with(['checkins' => fn ($query) => $query->whereBetween('local_date', [$from, $today])])
            ->latest()
            ->get()
            ->map(fn (Habit $habit) => [
                'id' => $habit->id,
                'name' => $habit->name,
                'completed_today' => $habit->checkins->contains(fn ($checkin) => $checkin->local_date->toDateString() === $today),
                'completed_7_days' => $habit->checkins->count(),
            ]);

        return Inertia::render('Dashboard', [
            'habits' => $habits,
            'today' => $today,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        Habit::create([
            'user_id' => $request->user()->id,
            'name' => trim($validated['name']),
        ]);

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

    private function assertOwnership(Request $request, Habit $habit): void
    {
        abort_unless($habit->user_id === $request->user()->id, 404);
    }

    private function todayForUser(Request $request): string
    {
        $validated = $request->validate([
            'timezone' => ['required', 'timezone'],
        ]);

        $timezone = $validated['timezone'];
        $user = $request->user();

        if ($user->timezone !== $timezone) {
            $user->forceFill(['timezone' => $timezone])->save();
        }

        return Carbon::now($timezone)->toDateString();
    }
}
