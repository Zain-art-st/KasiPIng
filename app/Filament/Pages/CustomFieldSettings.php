<?php

namespace App\Filament\Pages;

use App\Models\CustomFieldDefinition;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use App\Filament\Pages\Concerns\HasRsvpPreview;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use UnitEnum;

class CustomFieldSettings extends Page
{
    use HasRsvpPreview;

    protected string $view = 'filament.pages.custom-field-settings';

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 2;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQuestionMarkCircle;

    protected static ?string $navigationLabel = 'Extra RSVP Questions';

    protected static ?string $title = 'Extra RSVP Questions';

    public ?array $data = [];

    public function getSubheading(): ?string
    {
        return 'Questions you add here are asked on every guest\'s RSVP form, below the standard ones.';
    }

    /** The questions as they are on screen (unsaved), for the live preview. */
    protected function previewCustomFields(): Collection
    {
        return collect($this->data['fields'] ?? [])
            ->filter(fn ($f) => ($f['enabled'] ?? true) && trim((string) ($f['label'] ?? '')) !== '')
            ->values()
            ->map(fn ($f, $i) => new CustomFieldDefinition([
                'field_key' => $f['field_key'] ?? 'preview_'.$i,
                'label' => $f['label'],
            ]));
    }

    public function mount(): void
    {
        $fields = CustomFieldDefinition::orderBy('sort_order')->get();

        $this->form->fill([
            'fields' => $fields->map(fn ($f) => [
                'label' => $f->label,
                'field_key' => $f->field_key,
                'enabled' => $f->enabled,
            ])->toArray(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Repeater::make('fields')
                    ->label('Questions (up to 5)')
                    ->schema([
                        TextInput::make('label')
                            ->label('Question')
                            ->placeholder('e.g. Dietary requirements')
                            ->required()
                            ->maxLength(255)
                            ->live(debounce: 500),
                        Toggle::make('enabled')
                            ->label('Ask guests this question')
                            ->helperText('Turn off to stop asking without deleting answers already given.')
                            ->default(true)
                            ->live(),
                    ])
                    ->itemLabel(fn (array $state) => $state['label'] ?? 'New question')
                    ->maxItems(5)
                    ->addActionLabel('Add question')
                    ->reorderable()
                    ->collapsible()
                    ->live(),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $submittedFields = $state['fields'] ?? [];

        $existingKeys = [];

        foreach ($submittedFields as $index => $field) {
            $existingKeys[] = $key = $this->resolveFieldKey($field);

            CustomFieldDefinition::updateOrCreate(
                ['field_key' => $key],
                [
                    'label' => $field['label'],
                    'enabled' => $field['enabled'] ?? true,
                    'sort_order' => $index,
                ],
            );
        }

     
        CustomFieldDefinition::whereNotIn('field_key', $existingKeys)->delete();

        Notification::make()
            ->title('Custom questions saved')
            ->success()
            ->send();

        $this->mount();
    }

    private function resolveFieldKey(array $field): string
    {
        if (! empty($field['field_key'])) {
            return $field['field_key'];
        }

        $existing = CustomFieldDefinition::where('label', $field['label'])->first();

        return $existing?->field_key ?? 'custom_'.Str::random(8);
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')->label('Save Questions')->action('save'),
        ];
    }
}