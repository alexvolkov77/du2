<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Habit extends Model
{
    protected $fillable = ['name', 'user_id', 'schedule_type', 'schedule_days', 'reminder_enabled', 'reminder_time', 'last_reminded_on'];

    protected function casts(): array
    {
        return [
            'schedule_days' => 'array',
            'reminder_enabled' => 'boolean',
            'last_reminded_on' => 'date:Y-m-d',
        ];
    }

    public function isScheduledOn(Carbon $date): bool
    {
        if ($this->schedule_type !== 'weekly') {
            return true;
        }

        return in_array((int) $date->dayOfWeekIso, $this->schedule_days ?? [], true);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function checkins(): HasMany
    {
        return $this->hasMany(HabitCheckin::class);
    }
}
