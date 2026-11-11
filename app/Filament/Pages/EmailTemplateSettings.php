<?php

namespace App\Filament\Pages;

use App\Models\EmailTemplate;
use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Placeholder;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;

class EmailTemplateSettings extends Page
{
    protected string $view = 'filament.pages.email-template-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $template = EmailTemplate::first();

        $this->form->fill([
            'subject' => $template?->subject,
            'body' => $template?->body,
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('reset')
                ->label('Reset to built-in email')
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('Deletes the saved template. Invitations go back to the built-in wording.')
                ->visible(fn () => EmailTemplate::exists())
                ->action(function () {
                    EmailTemplate::query()->delete();
                    $this->form->fill();

                    Notification::make()->title('Template removed; built-in email restored')->success()->send();
                }),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Placeholder::make('help')
                    ->label('')
                    ->content('Once saved, this template replaces the built-in invitation email for every invitation (standalone and meeting). Use "Reset to built-in email" to go back. Placeholders, replaced for each recipient: {vip_name}, {contact_name}, {organization}, {meeting_title}, {meeting_time}, {location}, {rsvp_link}. If the body has no {rsvp_link}, a "Respond now" button is added automatically.'),

                TextInput::make('subject')
                    ->required()
                    ->maxLength(255),

                RichEditor::make('body')
                    ->required()
                    ->toolbarButtons([
                        'bold',
                        'italic',
                        'underline',
                        'bulletList',
                        'orderedList',
                        'h2',
                        'h3',
                        'link',
                        'undo',
                        'redo',
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $state = $this->form->getState();

        $template = EmailTemplate::first();

        if ($template) {
            $template->update($state);
        } else {
            EmailTemplate::create($state);
        }

        Notification::make()
            ->title('Email template saved')
            ->success()
            ->send();
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Save Template')
                ->action('save'),
        ];
    }
}