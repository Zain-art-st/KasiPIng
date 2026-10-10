<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    public function redirect()
    {
        return Socialite::driver('google')
            ->scopes(['https://www.googleapis.com/auth/calendar.readonly'])
            ->redirect();
    }

    public function callback()
    {
        $googleUser = Socialite::driver('google')->user();

        $allowedEmails = array_map('trim', explode(',', config('services.google.allowed_emails', '')));

        if (! in_array($googleUser->email, $allowedEmails, true)) {
            abort(403, 'This Google account is not authorized to access this system.');
        }

        $user = User::updateOrCreate(
            ['google_id' => $googleUser->id],
            [
                'name' => $googleUser->name,
                'email' => $googleUser->email,
                'password' => bcrypt(str()->random(32)),
            ],
        );

        Auth::login($user);

        return redirect('/admin');
    }
}