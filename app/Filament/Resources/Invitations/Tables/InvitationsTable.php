<?php

namespace App\Filament\Resources\Invitations\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Actions\BulkAction;
use Illuminate\Database\Eloquent\Collection;

class InvitationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('vip_name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('organization')
                    ->searchable(),
                    TextColumn::make('vip_email')
                     ->label('VIP Email')
                    ->searchable(),
                TextColumn::make('attendance_status')
    ->badge()
    ->colors([
        'warning' => 'pending',
        'success' => 'attending',
        'info' => 'representative_attending',
        'danger' => 'not_attending',
    ]),

TextColumn::make('representative_name')
    ->label('Representative')
    ->placeholder('—')
    ->toggleable(isToggledHiddenByDefault: true),   
                TextColumn::make('submitted_by_email')
                    ->label('Submitted By')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('attendance_status')
    ->options([
        'pending' => 'Pending',
        'attending' => 'Attending',
        'representative_attending' => 'Representative Attending',
        'not_attending' => 'Not Attending',
    ]),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
    BulkActionGroup::make([
        BulkAction::make('sendBulkInvitations')
            ->label('Send Invitations')
            ->icon('heroicon-o-envelope')
            ->requiresConfirmation()
            ->modalDescription('This will send an invitation email to every contact on each selected invitation. This may take a moment for large selections.')
            ->action(function (Collection $records) {
                $sent = 0;
                $failed = 0;

                foreach ($records as $invitation) {
                    foreach ($invitation->contacts as $contact) {
                        try {
                            \Illuminate\Support\Facades\Mail::send(new \App\Mail\InvitationEmail($contact));
                            $sent++;
                        } catch (\Throwable $e) {
                            $failed++;
                        }
                    }
                }

                \Filament\Notifications\Notification::make()
                    ->title("Bulk send complete: {$sent} sent, {$failed} failed")
                    ->success()
                    ->send();
            }),
        DeleteBulkAction::make(),
    ]),
            ]);
    }
}