<?php

namespace App\Filament\Auth;

use App\Http\Middleware\SetLocale;
use Filament\Actions\Action;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Facades\Filament;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;

class GoogleLogin extends BaseLogin
{
    public ?string $loginError = null;

    public function mount(): void
    {
        if (Filament::auth()->check()) {
            $this->redirect(Filament::getUrl());

            return;
        }

        $this->loginError = session('login_error');
    }

    public function getTitle(): string|Htmlable
    {
        return __('Sign in');
    }

    public function getHeading(): string|Htmlable|null
    {
        // Login heading: the app name from APP_NAME in .env.
        return config('app.name');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('RSVP and seating administration for the event secretariat');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Callout::make(__('Sign-in did not work'))
                ->description(fn () => $this->loginError)
                ->danger()
                ->visible(fn () => filled($this->loginError)),
            Text::make(__('Only authorised secretariat Google accounts can sign in. If you need access, ask the system administrator to add your Google email.')),
            Actions::make([
                Action::make('google')
                    ->label(__('Sign in with Google'))
                    ->icon('heroicon-o-arrow-right-end-on-rectangle')
                    ->url(route('auth.google.redirect')),
            ])->fullWidth(),
            Text::make(__('Guests do not need to sign in. They reply using the personal link in their invitation email.'))
                ->size('xs')
                ->color('gray'),
            Actions::make([
                Action::make('switchLanguage')
                    ->label(fn () => SetLocale::LOCALES[SetLocale::other()])
                    ->icon('heroicon-o-language')
                    ->link()
                    ->url(fn () => route('language.switch', SetLocale::other())),
            ])->alignCenter(),
        ]);
    }
}
