<?php

namespace App\Filament\Resources\Invitations\RelationManagers;

use App\Filament\Resources\Invitations\InvitationResource;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ContactsRelationManager extends RelationManager
{
    protected static string $relationship = 'contacts';

    protected static ?string $relatedResource = InvitationResource::class;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->email()
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('email')
                    ->searchable(),
                TextColumn::make('last_used_at')
                    ->dateTime()
                    ->label('Last Opened')
                    ->placeholder('Never'),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                \Filament\Actions\Action::make('sendInvitation')
                    ->label('Send Invitation')
                    ->icon('heroicon-o-envelope')
                    ->action(function (\App\Models\InvitationContact $record) {
                    \Illuminate\Support\Facades\Mail::send(new \App\Mail\InvitationEmail($record));

                     \Filament\Notifications\Notification::make()
                    ->title('Invitation sent for '.$record->invitation->vip_name)
                    ->success()
                    ->send();
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}