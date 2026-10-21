<?php

namespace App\Http\Controllers\Auth;

use App\Filament\Pages\CalendarConnection;
use App\Http\Controllers\Controller;
use App\Models\GoogleConnection;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    public function redirect()
    {
        session()->forget('google_calendar_connect');

        return Socialite::driver('google')
            ->with(['prompt' => 'select_account'])
            ->redirect();
    }

    public function connectCalendar()
    {
        abort_unless(
            Auth::check() && Auth::user()->canAccessPanel(Filament::getPanel('admin')),
            403
        );

        session(['google_calendar_connect' => true]);

        return Socialite::driver('google')
            ->scopes(['https://www.googleapis.com/auth/calendar.readonly'])
            ->with(['access_type' => 'offline', 'prompt' => 'consent select_account'])
            ->redirect();
    }

    public function callback()
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Throwable $e) {
            abort(403, 'Google sign-in was cancelled or failed. Please try again.');
        }

        if (session()->pull('google_calendar_connect')) {
            return $this->storeCalendarConnection($googleUser);
        }

        $allowed = collect(explode(',', (string) config('services.google.allowed_emails')))
            ->map(fn ($email) => strtolower(trim($email)))
            ->filter()
            ->all();

        if (! in_array(strtolower($googleUser->email), $allowed, true)) {
            abort(403, 'This Google account is not authorized to access this system.');
        }

        $user = User::firstOrNew(['email' => $googleUser->email]);
        $user->name = $googleUser->name;
        $user->google_id = $googleUser->id;

        if (! $user->exists) {
            $user->password = Str::random(40);
        }

        $user->save();

        Auth::login($user);
        request()->session()->regenerate();

        return redirect()->intended('/admin');
    }

    private function storeCalendarConnection($googleUser)
    {
        abort_unless(Auth::check(), 403, 'Sign in to the admin panel first.');

        if (! $googleUser->refreshToken) {
            Notification::make()
                ->title('Google did not return a refresh token. Please click Connect again.')
                ->danger()
                ->send();

            return redirect(CalendarConnection::getUrl());
        }

        GoogleConnection::query()->delete();

        GoogleConnection::create([
            'email' => $googleUser->email,
            'access_token' => $googleUser->token,
            'refresh_token' => $googleUser->refreshToken,
            'expires_at' => now()->addSeconds((int) ($googleUser->expiresIn ?? 3600)),
        ]);

        Notification::make()->title('Google Calendar connected')->success()->send();

        return redirect(CalendarConnection::getUrl());
    }
}