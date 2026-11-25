<?php

namespace App\Filament\Pages;

use App\Models\AppSetting;
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

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('Settings');
    }

    protected static ?int $navigationSort = 2;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQuestionMarkCircle;

    public static function getNavigationLabel(): string
    {
        return __('Extra RSVP Questions');
    }

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return __('Extra RSVP Questions');
    }

    public ?array $data = [];

    public function getSubheading(): ?string
    {
        return __('Questions you add here are asked on every guest\'s RSVP form, below the standard ones.');
    }

    protected function previewCustomFields(): Collection
    {
        return collect($this->data['fields'] ?? [])
            ->filter(fn ($f) => ($f['enabled'] ?? true) && trim((string) ($f['label'] ?? '')) !== '')
            ->values()
            ->map(function ($f, $i) {
                $field = new CustomFieldDefinition([
                    'field_key' => $f['field_key'] ?? 'preview_'.$i,
                    'label' => $f['label'],
                ]);
                $field->previewLabelMs = $f['label_ms'] ?? '';

                return $field;
            });
    }

    public function mount(): void
    {
        $fields = CustomFieldDefinition::orderBy('sort_order')->get();

        $this->form->fill([
            'fields' => $fields->map(fn ($f) => [
                'label' => $f->label,
                'label_ms' => $f->malayLabel(),
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
                    ->label(__('Questions (up to 5)'))
                    ->schema([
                        TextInput::make('label')
                            ->label(__('Question'))
                            ->placeholder(__('e.g. Dietary requirements'))
                            ->required()
                            ->maxLength(255)
                            ->live(debounce: 500),
                        TextInput::make('label_ms')
                            ->label(__('Question in Bahasa Melayu'))
                            ->placeholder('cth. Keperluan pemakanan')
                            ->helperText(__('Shown when the guest views the form in Malay. Leave empty to show the English question.'))
                            ->maxLength(255)
                            ->live(debounce: 500),
                        Toggle::make('enabled')
                            ->label(__('Ask guests this question'))
                            ->helperText(__('Turn off to stop asking without deleting answers already given.'))
                            ->default(true)
                            ->live(),
                    ])
                    ->itemLabel(fn (array $state) => $state['label'] ?? __('New question'))
                    ->maxItems(5)
                    ->addActionLabel(__('Add question'))
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
        $malayLabels = [];

        foreach ($submittedFields as $index => $field) {
            $existingKeys[] = $key = $this->resolveFieldKey($field);

            if (filled($field['label_ms'] ?? null)) {
                $malayLabels[$key] = $field['label_ms'];
            }

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
        AppSetting::putJson(AppSetting::QUESTION_LABELS_MS, $malayLabels);

        Notification::make()
            ->title(__('Custom questions saved'))
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
            Action::make('save')->label(__('Save Questions'))->action('save'),
        ];
    }
}