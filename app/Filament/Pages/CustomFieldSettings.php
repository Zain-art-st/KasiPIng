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
use Illuminate\Support\Str;

class CustomFieldSettings extends Page
{
    protected string $view = 'filament.pages.custom-field-settings';

    public ?array $data = [];

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
                    ->label('Custom Questions')
                    ->schema([
                        TextInput::make('label')
                            ->label('Question Label')
                            ->required()
                            ->maxLength(255),
                        Toggle::make('enabled')
                            ->label('Show on forms')
                            ->default(true),
                    ])
                    ->maxItems(5)
                    ->addActionLabel('Add Question')
                    ->reorderable()
                    ->collapsible(),
            ])
            ->statePath('data');
    }

    public function save(): void
    {   
        logger('Save method called');
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