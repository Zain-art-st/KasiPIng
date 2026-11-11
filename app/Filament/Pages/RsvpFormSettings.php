<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HasRsvpPreview;
use App\Models\FormSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class RsvpFormSettings extends Page
{
    use HasRsvpPreview;

    protected string $view = 'filament.pages.rsvp-form-settings';

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $navigationLabel = 'RSVP Form Wording';

    protected static ?string $title = 'RSVP Form Wording';

    public ?array $data = [];

    public function getSubheading(): ?string
    {
        return 'The wording guests see when they open their RSVP link from the invitation email. '
            .'It applies to every link (big events and meetings). The preview on the right updates as you type.';
    }

    public function mount(): void
    {
        $this->form->fill(FormSettings::current()->formState());
    }

    public function form(Schema $schema): Schema
    {
        $text = fn (string $name) => TextInput::make($name)->required()->maxLength(255)->live(debounce: 500);

        return $schema
            ->components([
                Section::make('Top of the page')
                    ->schema([
                        $text('page_heading')
                            ->label('Page heading')
                            ->helperText('Shown at the very top for big-event invitations. Meeting invitations show the meeting title instead.'),
                        Textarea::make('intro_text')
                            ->label('Intro text (optional)')
                            ->rows(2)
                            ->live(debounce: 500)
                            ->helperText('A short note under the guest\'s name, e.g. dress code or parking instructions.'),
                    ]),

                Section::make('Attendance question')
                    ->schema([
                        $text('attendance_question')
                            ->label('Question')
                            ->helperText('Above the three answer buttons.'),
                        $text('attending_label')->label('"Attending" button'),
                        $text('representative_label')
                            ->label('"Representative" button')
                            ->helperText('When chosen, the guest is also asked for the representative\'s name and role.'),
                        $text('not_attending_label')->label('"Not attending" button'),
                    ]),

                Section::make('Other questions')
                    ->schema([
                        $text('vehicle_label')->label('Vehicle question'),
                        $text('arrival_label')->label('Arrival time question'),
                        $text('departure_label')->label('Departure time question'),
                        $text('name_label')
                            ->label('"Your name" question')
                            ->helperText('Who is filling in the form. Pre-filled with the PA\'s name; recorded as "Submitted by".'),
                    ]),

                Section::make('Finishing')
                    ->schema([
                        $text('submit_button_label')->label('Submit button'),
                        $text('thank_you_message')
                            ->label('Thank-you message')
                            ->helperText('Shown after the guest submits. Use "After submitting" in the preview to see it.'),
                    ]),
            ])
            ->statePath('data');
    }

    protected function previewSettings(): FormSettings
    {
        $settings = new FormSettings;
        $settings->fill(array_merge(FormSettings::DEFAULTS, array_filter($this->data ?? [], fn ($v) => $v !== null && $v !== '')));

        return $settings;
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $settings = FormSettings::query()->first();

        if ($settings) {
            $settings->update($state);
        } else {
            FormSettings::create($state);
        }

        Notification::make()->title('Form wording saved')->success()->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('reset')
                ->label('Reset to default wording')
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('Fills the form with the original wording. Nothing changes for guests until you press Save.')
                ->action(fn () => $this->form->fill(FormSettings::DEFAULTS)),
        ];
    }
}
