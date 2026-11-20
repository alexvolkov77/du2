<?php

use App\Http\Controllers\HabitController;
use App\Http\Controllers\TelegramAuthController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::get('/telegram', [TelegramAuthController::class, 'index'])->name('telegram.index');
Route::get('/telegram/email', [TelegramAuthController::class, 'emailLogin'])->name('telegram.email');
Route::post('/telegram/session', [TelegramAuthController::class, 'authenticate'])
    ->middleware('throttle:12,1')->name('telegram.session');

Route::middleware('auth')->group(function (): void {
    Route::get('/dashboard', [HabitController::class, 'index'])->name('dashboard');
    Route::post('/habits', [HabitController::class, 'store'])->name('habits.store');
    Route::delete('/habits/{habit}', [HabitController::class, 'destroy'])->name('habits.destroy');
    Route::post('/habits/{habit}/complete', [HabitController::class, 'complete'])->name('habits.complete');
    Route::delete('/habits/{habit}/complete', [HabitController::class, 'uncomplete'])->name('habits.uncomplete');
});

require __DIR__.'/settings.php';
