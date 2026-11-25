<?php

namespace App\Filament\Pages;

use App\Models\AppSetting;
use App\Models\EmailTemplate;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
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

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('Settings');
    }

    protected static string|\BackedEnum|null $navigationIcon = \Filament\Support\Icons\Heroicon::OutlinedEnvelope;

    public static function getNavigationLabel(): string
    {
        return __('Invitation Email');
    }

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return __('Invitation Email');
    }

    protected static ?int $navigationSort = 3;

    public ?array $data = [];

    public function mount(): void
    {
        $template = EmailTemplate::first();
        $malay = AppSetting::getJson(AppSetting::EMAIL_TEMPLATE_MS);

        $this->form->fill([
            'subject' => $template?->subject,
            'body' => $template?->body,
            'subject_ms' => $malay['subject'] ?? null,
            'body_ms' => $malay['body'] ?? null,
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('reset')
                ->label(__('Reset to built-in email'))
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription(__('Deletes the saved template. Invitations go back to the built-in wording.'))
                ->visible(fn () => EmailTemplate::exists())
                ->action(function () {
                    EmailTemplate::query()->delete();
                    AppSetting::putJson(AppSetting::EMAIL_TEMPLATE_MS, []);
                    $this->form->fill();

                    Notification::make()->title(__('Template removed; built-in email restored'))->success()->send();
                }),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Placeholder::make('help')
                    ->label('')
                    ->content(__('Once saved, this template replaces the built-in invitation email for every invitation (standalone and meeting). Use "Reset to built-in email" to go back. Placeholders, replaced for each recipient: {vip_name}, {contact_name}, {organization}, {meeting_title}, {meeting_time}, {location}, {rsvp_link}. If the body has no {rsvp_link}, a "Respond now" button is added automatically.')),

                Placeholder::make('languages')
                    ->label('')
                    ->content(__('Each email contains both languages: the Bahasa Melayu version first, then the English version. Leave the Bahasa Melayu tab empty to send the English version only.')),

                Tabs::make(__('Language'))
                    ->tabs([
                        Tab::make('English')->schema([
                            TextInput::make('subject')
                                ->label(__('Subject'))
                                ->required()
                                ->maxLength(255),
                            $this->bodyEditor('body')->label(__('Body'))->required(),
                        ]),
                        Tab::make('Bahasa Melayu')->schema([
                            TextInput::make('subject_ms')
                                ->label(__('Subject'))
                                ->maxLength(255),
                            $this->bodyEditor('body_ms')->label(__('Body')),
                        ]),
                    ]),
            ])
            ->statePath('data');
    }

    private function bodyEditor(string $name): RichEditor
    {
        return RichEditor::make($name)
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
            ]);
    }

    public function save(): void
    {
        $state = $this->form->getState();
        AppSetting::putJson(AppSetting::EMAIL_TEMPLATE_MS, [
            'subject' => $state['subject_ms'] ?? null,
            'body' => $state['body_ms'] ?? null,
        ]);
        $state = array_intersect_key($state, array_flip(['subject', 'body']));

        $template = EmailTemplate::first();

        if ($template) {
            $template->update($state);
        } else {
            EmailTemplate::create($state);
        }

        Notification::make()
            ->title(__('Email template saved'))
            ->success()
            ->send();
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label(__('Save Template'))
                ->action('save'),
        ];
    }
}