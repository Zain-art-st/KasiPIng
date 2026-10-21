<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\GoogleAuthController;

Route::redirect('/', '/admin');
    
use App\Http\Controllers\RsvpController;

Route::get('/rsvp/{token}', [RsvpController::class, 'show'])
    ->middleware('throttle:30,1')
    ->name('rsvp.show');
Route::post('/rsvp/{token}', [RsvpController::class, 'submit'])
    ->middleware('throttle:10,1')
    ->name('rsvp.submit');
Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect'])->name('auth.google.redirect');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');
Route::get('/auth/google/calendar', [GoogleAuthController::class, 'connectCalendar'])->name('auth.google.calendar');