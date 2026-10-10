<?php

namespace App\Filament\Pages;

use App\Models\FormSettings;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;

class RsvpFormSettings extends Page
{
    protected string $view = 'filament.pages.rsvp-form-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $settings = FormSettings::first();

        $this->form->fill($settings?->toArray() ?? []);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('page_heading')->required(),
                Textarea::make('intro_text')->rows(2),
                TextInput::make('attendance_question')->required(),
                TextInput::make('attending_label')->required(),
                TextInput::make('not_attending_label')->required(),
                TextInput::make('vehicle_label')->required(),
                TextInput::make('arrival_label')->required(),
                TextInput::make('departure_label')->required(),
                TextInput::make('name_label')->required(),
                TextInput::make('submit_button_label')->required(),
                TextInput::make('thank_you_message')->required(),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $settings = FormSettings::first();

        if ($settings) {
            $settings->update($state);
        } else {
            FormSettings::create($state);
        }

        Notification::make()->title('Form text saved')->success()->send();
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')->label('Save').action('save'),
        ];
    }
}