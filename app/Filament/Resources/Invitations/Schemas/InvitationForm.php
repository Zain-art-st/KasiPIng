<?php

namespace App\Filament\Resources\Invitations\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use App\Models\CustomFieldDefinition;
use Filament\Forms\Components\Repeater;


class InvitationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('vip_name')
                    ->required(),
                TextInput::make('organization'),
                TextInput::make('vip_email')
                    ->email()
                    ->label('VIP Email'),
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
                TextInput::make('vehicle_registration'),
                TextInput::make('estimated_arrival')->label('Waktu Tiba'),
                TextInput::make('estimated_depature')->label('Waktu pulang'),
                TextInput::make('submitted_by_name')
                    ->disabled(),
                TextInput::make('submitted_by_email')
                    ->email()
                    ->disabled(),
                DateTimePicker::make('submitted_at')
                    ->disabled(),
                DateTimePicker::make('expires_at'),
                Repeater::make('customFieldValues')
                    ->label('Additional Details')
                    ->relationship()
                    ->schema([
                        \Filament\Forms\Components\Select::make('field_key')
                    ->label('Selection')
                    ->options(fn () => CustomFieldDefinition::where('enabled', true)->pluck('label', 'field_key'))
                    ->required(),
                TextInput::make('value'),
            ])
                    ->columns(2)
                    ->addActionLabel('Add Detail')
                    ->defaultItems(0),
            ]);
    }
}