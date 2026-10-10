<?php

namespace App\Filament\Pages;

use App\Models\CustomFieldDefinition;
use App\Models\CustomFieldValue;
use App\Models\Invitation;
use App\Models\InvitationContact;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ImportInvitations extends Page
{
    protected string $view = 'filament.pages.import-invitations';

    public ?array $data = [];
    public array $csvHeaders = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    private function targetFieldOptions(): array
{
    $fixed = [
        'vip_name' => 'VIP Name',
        'organization' => 'Organization',
        'vip_email' => 'VIP Email',
        'vip_phone' => 'VIP Phone',
        'pa_name' => 'Contact (PA) Name',
        'pa_email' => 'Contact (PA) Email',
    ];

    $custom = CustomFieldDefinition::where('enabled', true)
        ->pluck('label', 'field_key')
        ->mapWithKeys(fn ($label, $key) => ["custom:{$key}" => "Custom: {$label}"])
        ->toArray();

    $options = ['' => '— Skip this column —'] + $fixed + $custom;

    if (CustomFieldDefinition::count() < 5) {
        $options['new_custom'] = '+ Create new custom field from this column';
    }

    return $options;
}
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Wizard::make([
                    Wizard\Step::make('Upload')
                        ->schema([
                            FileUpload::make('csv_file')
                                ->label('CSV File')
                                ->acceptedFileTypes(['text/csv', 'text/plain'])
                                ->required()
                                ->disk('local')
                                ->directory('csv-imports')
                                ->live()
                                ->afterStateUpdated(function ($state) {
                                    if (! $state) {
                                        $this->csvHeaders = [];
                                        return;
                                    }

                                    $path = is_array($state) ? array_values($state)[0] : $state;
                                    $fullPath = Storage::disk('local')->path($path);

                                    if (file_exists($fullPath)) {
                                        $file = fopen($fullPath, 'r');
                                        $headers = fgetcsv($file);
                                        fclose($file);

                                        if ($headers) {
                                            $headers[0] = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $headers[0]);
                                            $this->csvHeaders = array_map(function($h) {
                                                return str_replace([' ', '.'], '_', trim($h));
                                            }, $headers);
                                        }
                                    }
                                }),
                        ]),
                    Wizard\Step::make('Map Columns')
                        ->schema(function () {
                            if (empty($this->csvHeaders)) {
                                return [
                                    Placeholder::make('none')
                                        ->label('')
                                        ->content('Upload a valid CSV on the previous step first.'),
                                ];
                            }
                            return collect($this->csvHeaders)->map(
                                fn ($header) => Select::make("mapping.{$header}")
                                    ->label("Column: \"{$header}\"")
                                    ->options($this->targetFieldOptions())
                                    ->default('')
                            )->toArray();
                        }),
                ])->submitAction(
                    Action::make('import')
                        ->label('Import')
                        ->action('import')
                ),
            ])
            ->statePath('data');
    }

    public function import(): void
{
    $state = $this->form->getState();
    $mapping = $state['mapping'] ?? [];
    $fileState = $state['csv_file'] ?? null;

    if (! $fileState || empty($mapping)) {
        Notification::make()->title('Missing file or mapping data.')->danger()->send();
        return;
    }

    $path = is_array($fileState) ? array_values($fileState)[0] : $fileState;
    $fullPath = Storage::disk('local')->path($path);

    if (! file_exists($fullPath)) {
        Notification::make()->title('Could not find the uploaded file.')->danger()->send();
        return;
    }

    // Resolve any "create new custom field" choices into real definitions first
    $slotsAvailable = 5 - CustomFieldDefinition::count();
    $skippedNewFields = [];

    foreach ($mapping as $header => $target) {
        if ($target !== 'new_custom') {
            continue;
        }

        if ($slotsAvailable <= 0) {
            $mapping[$header] = '';
            $skippedNewFields[] = $header;
            continue;
        }

        $definition = CustomFieldDefinition::create([
            'field_key' => 'custom_'.Str::random(8),
            'label' => $header,
            'enabled' => true,
            'sort_order' => CustomFieldDefinition::max('sort_order') + 1,
        ]);

        $mapping[$header] = 'custom:'.$definition->field_key;
        $slotsAvailable--;
    }

    $created = 0;
    $skipped = 0;

    $file = fopen($fullPath, 'r');
    $rawHeaders = fgetcsv($file);

    $rawHeaders[0] = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $rawHeaders[0]);
    $headers = array_map(function($h) {
        return str_replace([' ', '.'], '_', trim($h));
    }, $rawHeaders);

    while (($row = fgetcsv($file)) !== false) {
        if (count($row) !== count($headers)) {
            Log::warning("Skipped row: Column count mismatch.");
            $skipped++;
            continue;
        }

        $record = array_combine($headers, $row);

        $getMappedValue = fn (string $target) => collect($mapping)
            ->filter(fn ($t) => $t === $target)
            ->keys()
            ->map(fn ($header) => trim($record[$header] ?? ''))
            ->first(fn ($v) => $v !== '');

        $vipName = $getMappedValue('vip_name');
        $paEmail = $getMappedValue('pa_email');
        $paName = $getMappedValue('pa_name');

        if (! $vipName) {
            Log::warning("Skipped row: Missing VIP Name after mapping.", [
                'record_data' => $record,
                'mapping_state' => $mapping
            ]);
            $skipped++;
            continue;
        }

        $invitation = Invitation::firstOrCreate(
            [
                'vip_name' => $vipName,
                'organization' => $getMappedValue('organization') ?: null,
            ],
            [
                'vip_email' => $getMappedValue('vip_email') ?: null,
                'vip_phone' => $getMappedValue('vip_phone') ?: null,
            ],
        );

        if ($paEmail) {
            InvitationContact::firstOrCreate(
                [
                    'invitation_id' => $invitation->id,
                    'email' => $paEmail,
                ],
                [
                    'name' => $paName ?: $paEmail,
                    'token' => Str::random(40),
                ],
            );
        }

        foreach ($mapping as $header => $target) {
            if (str_starts_with((string) $target, 'custom:')) {
                $fieldKey = substr($target, 7);
                $value = trim($record[$header] ?? '');

                if ($value !== '') {
                    CustomFieldValue::updateOrCreate(
                        ['invitation_id' => $invitation->id, 'field_key' => $fieldKey],
                        ['value' => $value],
                    );
                }
            }
        }

        $created++;
    }

    fclose($file);

    $message = "Import complete: {$created} rows processed, {$skipped} skipped";
    if (! empty($skippedNewFields)) {
        $message .= '. Could not create new field(s) for: '.implode(', ', $skippedNewFields).' (5-field limit reached)';
    }

    Notification::make()
        ->title($message)
        ->success()
        ->send();

    $this->csvHeaders = [];
    $this->form->fill();
}}