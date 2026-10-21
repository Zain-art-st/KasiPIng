<?php

namespace App\Filament\Pages;

use App\Services\GoogleCalendarSync;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class CalendarConnection extends Page
{
    protected string $view = 'filament.pages.calendar-connection';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('syncNow')
                ->label('Sync now')
                ->action(function () {
                    try {
                        $message = app(GoogleCalendarSync::class)->run();
                        Notification::make()->title($message)->success()->send();
                    } catch (\Throwable $e) {
                        Notification::make()->title('Sync failed')->body($e->getMessage())->danger()->send();
                    }
                }),
        ];
    }
}