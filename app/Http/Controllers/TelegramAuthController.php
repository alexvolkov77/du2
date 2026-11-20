<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserIdentity;
use App\Services\TelegramInitDataVerifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class TelegramAuthController extends Controller
{
    public function index(Request $request): Response
    {
        $linked = $request->user()
            ? UserIdentity::query()->where('provider', 'telegram')->where('user_id', $request->user()->id)->exists()
            : false;

        return Inertia::render('Telegram', [
            'configured' => (string) config('telegram.bot_token') !== '',
            'linked' => $linked,
        ]);
    }

    public function emailLogin(Request $request): RedirectResponse
    {
        $request->session()->put('url.intended', route('telegram.index'));

        return redirect()->route('login');
    }

    public function authenticate(Request $request, TelegramInitDataVerifier $verifier): RedirectResponse
    {
        $validated = $request->validate([
            'init_data' => ['required', 'string', 'max:8192'],
            'create_if_missing' => ['required', 'boolean'],
        ]);

        try {
            $telegramUser = $verifier->verify($validated['init_data'], (string) config('telegram.bot_token'));
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'telegram' => 'Не удалось проверить вход через Telegram. Закрой приложение и открой заново.',
            ]);
        }

        $telegramId = (string) $telegramUser['id'];

        $identity = UserIdentity::query()
            ->where('provider', 'telegram')
            ->where('provider_user_id', $telegramId)
            ->first();

        if ($request->user()) {
            if ($identity && $identity->user_id !== $request->user()->id) {
                throw ValidationException::withMessages([
                    'telegram' => 'Этот Telegram привязан к другому аккаунту.',
                ]);
            }

            $another = UserIdentity::query()
                ->where('provider', 'telegram')
                ->where('user_id', $request->user()->id)
                ->first();

            if ($another && $another->provider_user_id !== $telegramId) {
                throw ValidationException::withMessages([
                    'telegram' => 'У этого аккаунта уже есть другой привязанный Telegram.',
                ]);
            }

            if (! $identity) {
                UserIdentity::query()->create([
                    'user_id' => $request->user()->id,
                    'provider' => 'telegram',
                    'provider_user_id' => $telegramId,
                ]);
            }

            return redirect()->route('dashboard');
        }

        if (! $identity && ! $request->boolean('create_if_missing')) {
            throw ValidationException::withMessages(['telegram' => 'ACCOUNT_NOT_LINKED']);
        }

        $user = $identity?->user;
        if (! $user) {
            $user = DB::transaction(function () use ($telegramUser, $telegramId): User {
                $name = trim(($telegramUser['first_name'] ?? '').' '.($telegramUser['last_name'] ?? ''));
                $user = User::create([
                    'name' => Str::limit($name ?: 'Пользователь Telegram', 255, ''),
                    'email' => 'telegram-'.$telegramId.'@users.lk.du2.invalid',
                    'password' => Str::random(48),
                ]);
                UserIdentity::query()->create([
                    'user_id' => $user->id,
                    'provider' => 'telegram',
                    'provider_user_id' => $telegramId,
                ]);

                return $user;
            });
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }
}
