<?php

use App\Http\Controllers\N8nTelegramController;
use Illuminate\Support\Facades\Route;

Route::post('/integrations/n8n/telegram', N8nTelegramController::class)
    ->middleware('throttle:120,1');
