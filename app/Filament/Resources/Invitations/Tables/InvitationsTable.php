<?php

namespace App\Filament\Resources\Invitations\Tables;

use App\Models\CustomFieldDefinition;
use App\Models\Invitation;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

class InvitationsTable
{
    public static function configure(Table $table, bool $standaloneOnly = false): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with(['contacts', 'customFieldValues'])
                ->when($standaloneOnly, fn ($q) => $q->whereNull('meeting_id')))
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
                TextColumn::make('invited_at')
                    ->label('Invited')
                    ->since()
                    ->placeholder('Not sent')
                    ->sortable(),
                TextColumn::make('representative_name')
                    ->label('Representative')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('submitted_by_email')
                    ->label('Submitted By')
                    ->searchable()
                    ->toggleable(),
                ...static::customFieldColumns(),
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
                TernaryFilter::make('invited')
                    ->label('Invitation sent')
                    ->nullable()
                    ->attribute('invited_at'),
            ])
            ->recordActions([
                static::sendOneAction(),
                EditAction::make(),
            ])
            ->headerActions($standaloneOnly
                ? [static::sendNotInvitedAction(fn () => Invitation::query()->whereNull('meeting_id'))]
                : [])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('sendBulkInvitations')
                        ->label('Send / resend to selected')
                        ->icon('heroicon-o-envelope')
                        ->requiresConfirmation()
                        ->modalDescription('Sends the invitation email to each selected guest (PA copied), including guests who were already invited.')
                        ->action(fn ($records) => static::sendMany($records)),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /** Row button: send to this one guest. */
    public static function sendOneAction(): Action
    {
        return Action::make('send')
            ->label(fn (Invitation $record) => $record->invited_at ? 'Resend' : 'Send')
            ->icon('heroicon-o-paper-airplane')
            ->color(fn (Invitation $record) => $record->invited_at ? 'gray' : 'primary')
            ->requiresConfirmation()
            ->modalHeading(fn (Invitation $record) => ($record->invited_at ? 'Resend invitation to ' : 'Send invitation to ').$record->vip_name)
            ->modalDescription(fn (Invitation $record) => $record->recipientSummary()
                .($record->invited_at ? "\nLast sent ".$record->invited_at->diffForHumans().'.' : ''))
            ->modalSubmitActionLabel('Send')
            ->action(fn (Invitation $record) => static::sendMany([$record]));
    }

    /** Header button: send to everyone in $query who has not been invited yet. */
    public static function sendNotInvitedAction(Closure $query): Action
    {
        $notInvited = fn () => $query()->whereNull('invited_at');

        return Action::make('sendNotInvited')
            ->label(fn () => 'Send to everyone not yet invited ('.$notInvited()->count().')')
            ->icon('heroicon-o-paper-airplane')
            ->requiresConfirmation()
            ->modalDescription('Emails every guest on this list who has not been sent an invitation yet. Guests already invited are skipped; use their "Resend" button for those.')
            ->modalSubmitActionLabel('Send')
            ->disabled(fn () => ! $notInvited()->exists())
            ->action(fn () => static::sendMany($notInvited()->with('contacts')->get()));
    }

    /** Send to each invitation and show one summary notification. */
    public static function sendMany(iterable $invitations): void
    {
        $sent = 0;
        $failed = [];
        $noAddress = [];

        foreach ($invitations as $invitation) {
            try {
                $count = $invitation->sendInvitation();

                if ($count === 0) {
                    $noAddress[] = $invitation->vip_name;
                }

                $sent += $count;
            } catch (\Throwable $e) {
                report($e);
                $failed[] = $invitation->vip_name;
            }
        }

        $notification = Notification::make()->title("{$sent} invitation email(s) sent");
        $problems = [];

        if ($failed) {
            $problems[] = 'Could not send to: '.implode(', ', $failed).'. Check the mail settings and try again.';
        }

        if ($noAddress) {
            $problems[] = 'No email address (no VIP email and no contact): '.implode(', ', $noAddress);
        }

        if ($problems) {
            $notification->body(implode("\n", $problems))->{$failed ? 'danger' : 'warning'}()->persistent();
        } else {
            $notification->success();
        }

        $notification->send();
    }

    /** Hidden-by-default columns with the answers to each extra RSVP question. */
    private static function customFieldColumns(): array
    {
        return CustomFieldDefinition::orderBy('sort_order')->get()
            ->map(fn (CustomFieldDefinition $field) => TextColumn::make('custom_'.$field->field_key)
                ->label($field->label)
                ->state(fn (Invitation $record) => $record->customFieldValues->firstWhere('field_key', $field->field_key)?->value)
                ->placeholder('—')
                ->toggleable(isToggledHiddenByDefault: true))
            ->all();
    }
}
