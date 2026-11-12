<?php

namespace App\Filament\Pages;

use App\Models\AppSetting;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class ReminderSettings extends Page
{
    protected string $view = 'filament.pages.reminder-settings';

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBellAlert;

    protected static ?string $navigationLabel = 'Reminders';

    protected static ?string $title = 'Reminder Emails';

    protected static ?int $navigationSort = 4;

    public ?array $data = [];

    public function getSubheading(): ?string
    {
        return 'Keep emailing guests who have not answered, until the meeting starts.';
    }

    public function mount(): void
    {
        $this->form->fill(['interval' => (string) AppSetting::reminderIntervalHours()]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('interval')
                    ->label('Send a reminder every')
                    ->options(collect(AppSetting::REMINDER_INTERVAL_OPTIONS)->mapWithKeys(fn ($h) => [
                        (string) $h => $h === 0 ? 'Off (no reminders)' : $h.' '.($h === 1 ? 'hour' : 'hours'),
                    ])->all())
                    ->required()
                    ->selectablePlaceholder(false)
                    ->native(false),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $hours = (int) $this->form->getState()['interval'];
        AppSetting::put(AppSetting::REMINDER_INTERVAL_HOURS, $hours);

        Notification::make()
            ->title($hours ? "Reminders every {$hours}h saved" : 'Reminders turned off')
            ->success()
            ->send();
    }
}
