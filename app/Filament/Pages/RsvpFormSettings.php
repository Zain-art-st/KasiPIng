<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HasRsvpPreview;
use App\Models\AppSetting;
use App\Models\FormSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class RsvpFormSettings extends Page
{
    use HasRsvpPreview;

    protected string $view = 'filament.pages.rsvp-form-settings';

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('Settings');
    }

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    public static function getNavigationLabel(): string
    {
        return __('RSVP Form Wording');
    }

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return __('RSVP Form Wording');
    }

    public ?array $data = [];

    public function getSubheading(): ?string
    {
        return __('The wording guests see when they open their RSVP link from the invitation email. It applies to every link (big events and meetings). Fill in both languages; guests can switch between them. The preview on the right updates as you type.');
    }

    public function mount(): void
    {
        $settings = FormSettings::current();

        $this->form->fill($settings->formState() + ['ms' => $settings->malayFormState()]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make(__('Language'))
                    ->tabs([
                        Tab::make('English')->schema($this->wordingSections('', required: true)),
                        Tab::make('Bahasa Melayu')->schema($this->wordingSections('ms.', required: false)),
                    ]),
            ])
            ->statePath('data');
    }

    private function wordingSections(string $prefix, bool $required): array
    {
        $text = fn (string $name) => TextInput::make($prefix.$name)->required($required)->maxLength(255)->live(debounce: 500);

        return [
            Section::make(__('Top of the page'))
                ->schema([
                    $text('page_heading')
                        ->label(__('Page heading'))
                        ->helperText(__('Shown at the very top for big-event invitations. Meeting invitations show the meeting title instead.')),
                    Textarea::make($prefix.'intro_text')
                        ->label(__('Intro text (optional)'))
                        ->rows(2)
                        ->live(debounce: 500)
                        ->helperText(__('A short note under the guest\'s name, e.g. dress code or parking instructions.')),
                ]),

            Section::make(__('Attendance question'))
                ->schema([
                    $text('attendance_question')
                        ->label(__('Question'))
                        ->helperText(__('Above the three answer buttons.')),
                    $text('attending_label')->label(__('"Attending" button')),
                    $text('representative_label')
                        ->label(__('"Representative" button'))
                        ->helperText(__('When chosen, the guest is also asked for the representative\'s name and role.')),
                    $text('not_attending_label')->label(__('"Not attending" button')),
                ]),

            Section::make(__('Other questions'))
                ->schema([
                    $text('vehicle_label')->label(__('Vehicle question')),
                    $text('arrival_label')->label(__('Arrival time question')),
                    $text('departure_label')->label(__('Departure time question')),
                    $text('name_label')
                        ->label(__('"Your name" question'))
                        ->helperText(__('Who is filling in the form. Pre-filled with the PA\'s name; recorded as "Submitted by".')),
                ]),

            Section::make(__('Finishing'))
                ->schema([
                    $text('submit_button_label')->label(__('Submit button')),
                    $text('thank_you_message')
                        ->label(__('Thank-you message'))
                        ->helperText(__('Shown after the guest submits. Use "After submitting" in the preview to see it.')),
                ]),
        ];
    }

    protected function previewSettings(): FormSettings
    {
        $english = array_intersect_key($this->data ?? [], FormSettings::DEFAULTS);
        $settings = (new FormSettings)->forceFill(array_merge(FormSettings::DEFAULTS, array_filter($english, fn ($v) => $v !== null && $v !== '')));

        return $settings->localized($this->previewLocale(), $this->data['ms'] ?? []);
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $malay = $state['ms'] ?? [];
        unset($state['ms']);

        $settings = FormSettings::query()->first();

        if ($settings) {
            $settings->update($state);
        } else {
            FormSettings::create($state);
        }

        AppSetting::putJson(AppSetting::FORM_WORDING_MS, array_intersect_key($malay, FormSettings::DEFAULTS_MS));

        Notification::make()->title(__('Form wording saved'))->success()->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('reset')
                ->label(__('Reset to default wording'))
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription(__('Fills the form with the original wording. Nothing changes for guests until you press Save.'))
                ->action(fn () => $this->form->fill(FormSettings::DEFAULTS + ['ms' => FormSettings::DEFAULTS_MS])),
        ];
    }
}
