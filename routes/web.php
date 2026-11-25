<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\GoogleAuthController;

Route::redirect('/', '/admin');
    
use App\Http\Controllers\RsvpController;

// Guest RSVP pages open in Malay ('locale:ms'); change to 'locale:en' to open them in English.
Route::get('/rsvp/{token}', [RsvpController::class, 'show'])
    ->middleware(['throttle:30,1', 'locale:ms'])
    ->name('rsvp.show');
Route::post('/rsvp/{token}', [RsvpController::class, 'submit'])
    ->middleware(['throttle:10,1', 'locale:ms'])
    ->name('rsvp.submit');
Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect'])->name('auth.google.redirect');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');
Route::get('/auth/google/calendar', [GoogleAuthController::class, 'connectCalendar'])->name('auth.google.calendar');
Route::get('/language/{locale}', function (string $locale) {
    if (isset(\App\Http\Middleware\SetLocale::LOCALES[$locale])) {
        session(['locale' => $locale]);
    }

    return redirect()->back();
})->name('language.switch');
