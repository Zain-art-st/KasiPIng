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
            if (session()->pull('google_calendar_connect')) {
                Notification::make()->title(__('Google did not finish connecting. Please try again.'))->danger()->send();

                return redirect(CalendarConnection::getUrl());
            }

            return $this->backToLogin(__('Google sign-in was cancelled or did not finish. Please try again.'));
        }

        if (session()->pull('google_calendar_connect')) {
            return $this->storeCalendarConnection($googleUser);
        }

        $allowed = collect(explode(',', (string) config('services.google.allowed_emails')))
            ->map(fn ($email) => strtolower(trim($email)))
            ->filter()
            ->all();

        if (! in_array(strtolower($googleUser->email), $allowed, true)) {
            return $this->backToLogin(__('The Google account :email is not authorised to use this system. Sign in with your secretariat account, or ask the administrator to add this one.', ['email' => $googleUser->email]));
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

    private function backToLogin(string $message)
    {
        return redirect(Filament::getPanel('admin')->getLoginUrl())->with('login_error', $message);
    }

    private function storeCalendarConnection($googleUser)
    {
        abort_unless(Auth::check(), 403, __('Sign in to the admin panel first.'));

        if (! $googleUser->refreshToken) {
            Notification::make()
                ->title(__('Google did not return a refresh token. Please click Connect again.'))
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

        Notification::make()->title(__('Google Calendar connected'))->success()->send();

        return redirect(CalendarConnection::getUrl());
    }
}