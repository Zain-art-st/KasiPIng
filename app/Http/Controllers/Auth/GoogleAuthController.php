<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    public function redirect()
    {
        return Socialite::driver('google')
            ->scopes(['https://www.googleapis.com/auth/calendar.readonly'])
            ->with(['prompt' => 'select_account'])
            ->redirect();
    }

    public function callback()
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Throwable $e) {
            abort(403, 'Google sign-in was cancelled or failed. Please try again.');
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
}