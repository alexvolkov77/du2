<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HabitCheckin extends Model
{
    public $timestamps = false;

    protected $fillable = ['habit_id', 'local_date', 'completed_at'];

    protected function casts(): array
    {
        return ['local_date' => 'date:Y-m-d', 'completed_at' => 'datetime'];
    }

    public function habit(): BelongsTo
    {
        return $this->belongsTo(Habit::class);
    }
}
