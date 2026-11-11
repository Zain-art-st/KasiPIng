<?php

namespace App\Filament\Resources\Invitations\Schemas;

use App\Models\CustomFieldDefinition;
use App\Models\FormSettings;
use App\Models\Invitation;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class InvitationForm
{
    public static function configure(Schema $schema): Schema
    {
        $labels = FormSettings::current();

        return $schema
            ->components([
                TextInput::make('vip_name')
                    ->label('VIP name')
                    ->required(),
                TextInput::make('organization'),
                TextInput::make('vip_email')
                    ->email()
                    ->label('VIP Email')
                    ->helperText('The invitation is addressed here. If there is no PA, the VIP gets their own RSVP link.'),
                TextInput::make('vip_phone')
                    ->label('VIP Phone'),
                Select::make('attendance_status')
                    ->options([
                        'pending' => 'Pending',
                        'attending' => 'Attending',
                        'representative_attending' => 'Representative Attending',
                        'not_attending' => 'Not Attending',
                    ])
                    ->required()
                    ->default('pending'),
                TextInput::make('representative_name')
                    ->label('Representative Name'),
                TextInput::make('representative_role')
                    ->label('Representative Role/Title'),
                TextInput::make('vehicle_registration')
                    ->label($labels->vehicle_label),
                TextInput::make('estimated_arrival')
                    ->label($labels->arrival_label),
                TextInput::make('estimated_departure')
                    ->label($labels->departure_label),
                TextInput::make('submitted_by_name')
                    ->disabled(),
                TextInput::make('submitted_by_email')
                    ->email()
                    ->disabled(),
                DateTimePicker::make('submitted_at')
                    ->disabled(),
                DateTimePicker::make('expires_at')
                    ->label('Link expires at')
                    ->helperText('Optional. After this time the RSVP link stops working.'),
                static::customAnswers(),
            ]);
    }

    /** Read-only answers to the extra RSVP questions (edited via Settings → Extra RSVP Questions). */
    private static function customAnswers(): Section
    {
        $fields = CustomFieldDefinition::orderBy('sort_order')->get();

        return Section::make('Answers to extra questions')
            ->description('Filled in by the guest on their RSVP form, or from a CSV import.')
            ->schema($fields->map(fn (CustomFieldDefinition $field) => Placeholder::make('answer_'.$field->field_key)
                ->label($field->label)
                ->content(fn (?Invitation $record) => $record?->customFieldValues()
                    ->where('field_key', $field->field_key)
                    ->value('value') ?: '—'))
                ->all())
            ->columns(2)
            ->columnSpanFull()
            ->collapsible()
            ->visible(fn (?Invitation $record) => $record !== null && $fields->isNotEmpty());
    }
}
