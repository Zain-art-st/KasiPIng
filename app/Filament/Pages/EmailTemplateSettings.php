<?php

namespace App\Filament\Pages;

use App\Models\EmailTemplate;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
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
            'banner_path' => $template?->banner_path,
            'body' => $template?->body,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Placeholder::make('help')
                    ->label('')
                    ->content('You can use these placeholders anywhere in the subject or body they will be automatically replaced for each recipient: {vip_name}, {contact_name}, {rsvp_link}'),

                TextInput::make('subject')
                    ->required()
                    ->maxLength(255),

                FileUpload::make('banner_path')
                    ->label('Email Banner Image')
                    ->image()
                    ->disk('public')
                    ->directory('email-banners')
                    ->imageEditor()
                    ->helperText('Recommended width: 600px.'),

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