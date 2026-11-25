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

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('Settings');
    }

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBellAlert;

    public static function getNavigationLabel(): string
    {
        return __('Reminders');
    }

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return __('Reminder Emails');
    }

    protected static ?int $navigationSort = 4;

    public ?array $data = [];

    public function getSubheading(): ?string
    {
        return __('Keep emailing guests who have not answered, until the meeting starts.');
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
                    ->label(__('Send a reminder every'))
                    ->options(collect(AppSetting::REMINDER_INTERVAL_OPTIONS)->mapWithKeys(fn ($h) => [
                        (string) $h => $h === 0 ? __('Off (no reminders)') : trans_choice(':count hour|:count hours', $h),
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
            ->title($hours ? __('Reminders every :hours h saved', ['hours' => $hours]) : __('Reminders turned off'))
            ->success()
            ->send();
    }
}
