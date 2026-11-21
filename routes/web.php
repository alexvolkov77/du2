<?php

use App\Http\Controllers\HabitController;
use App\Http\Controllers\TelegramAuthController;
use App\Http\Controllers\TelegramLinkController;
use App\Http\Controllers\TelegramWebhookController;
use Illuminate\Support\Facades\Route;

// Fallback for hosts serving assets from a document root outside Laravel/public.
// When the static file is present, the web server serves it directly.
Route::get('/images/du2-logo.webp', function () {
    $path = public_path('images/du2-logo.webp');
    abort_unless(is_file($path), 404);

    return response()->file($path, [
        'Content-Type' => 'image/webp',
        'Cache-Control' => 'public, max-age=3600',
    ]);
});

Route::inertia('/', 'Welcome')->name('home');

Route::get('/telegram', [TelegramAuthController::class, 'index'])->name('telegram.index');
Route::post('/telegram/webhook', [TelegramWebhookController::class, 'handle'])->name('telegram.webhook');
Route::get('/telegram/email', [TelegramAuthController::class, 'emailLogin'])->name('telegram.email');
Route::post('/telegram/session', [TelegramAuthController::class, 'authenticate'])
    ->middleware('throttle:12,1')->name('telegram.session');

Route::middleware('auth')->group(function (): void {
    Route::get('/dashboard', [HabitController::class, 'index'])->name('dashboard');
    Route::get('/settings/telegram', [TelegramLinkController::class, 'show'])->name('telegram.link.show');
    Route::post('/settings/telegram/link', [TelegramLinkController::class, 'create'])->middleware('throttle:5,1')->name('telegram.link.create');
    Route::post('/habits', [HabitController::class, 'store'])->name('habits.store');
    Route::patch('/habits/{habit}', [HabitController::class, 'update'])->name('habits.update');
    Route::patch('/habits/{habit}/reminder', [HabitController::class, 'configureReminder'])->name('habits.reminder');
    Route::delete('/habits/{habit}', [HabitController::class, 'destroy'])->name('habits.destroy');
    Route::post('/habits/{habit}/complete', [HabitController::class, 'complete'])->name('habits.complete');
    Route::delete('/habits/{habit}/complete', [HabitController::class, 'uncomplete'])->name('habits.uncomplete');
});

require __DIR__.'/settings.php';
