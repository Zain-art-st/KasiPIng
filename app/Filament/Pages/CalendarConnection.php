<?php

namespace App\Filament\Pages;

use App\Services\GoogleCalendarSync;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class CalendarConnection extends Page
{
    protected string $view = 'filament.pages.calendar-connection';

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static string|\BackedEnum|null $navigationIcon = \Filament\Support\Icons\Heroicon::OutlinedArrowPath;

    protected static ?string $navigationLabel = 'Google Calendar';

    protected static ?string $title = 'Google Calendar Connection';

    protected static ?int $navigationSort = 5;

    public function getSubheading(): ?string
    {
        return 'Events from the connected Google Calendar (the next 60 days) are copied into Meetings every 2 minutes. '
            .'Changes and cancellations in Google are picked up automatically.';
    }

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