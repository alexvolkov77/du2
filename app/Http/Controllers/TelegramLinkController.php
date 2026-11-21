<?php

namespace App\Http\Controllers;

use App\Models\TelegramLinkToken;
use App\Models\UserIdentity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TelegramLinkController extends Controller
{
    public function show(Request $request): Response
    {
        $linked = UserIdentity::query()
            ->where('provider', 'telegram')
            ->where('user_id', $request->user()->id)
            ->exists();

        return Inertia::render('TelegramLink', [
            'linked' => $linked,
            'link_url' => $linked ? null : $request->session()->get('telegram_link_url'),
        ]);
    }

    public function create(Request $request): RedirectResponse
    {
        if ((string) config('telegram.bot_token') === '') {
            throw ValidationException::withMessages(['telegram' => 'Бот Telegram пока не настроен.']);
        }

        $userId = $request->user()->id;

        if (UserIdentity::query()->where('provider', 'telegram')->where('user_id', $userId)->exists()) {
            return redirect()->route('telegram.link.show');
        }

        $token = bin2hex(random_bytes(24));

        TelegramLinkToken::query()->where('user_id', $userId)->whereNull('used_at')->delete();
        TelegramLinkToken::create([
            'user_id' => $userId,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addMinutes(10),
        ]);

        return redirect()->route('telegram.link.show')
            ->with('telegram_link_url', 'https://t.me/'.config('telegram.bot_username').'?start=link_'.$token);
    }
}
