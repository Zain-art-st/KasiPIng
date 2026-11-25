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
                    ->label(__('VIP Email'))
                    ->searchable(),
                TextColumn::make('attendance_status')
                    ->label(__('Status'))
                    ->badge()
                    ->formatStateUsing(fn ($state, Invitation $record) => $record->statusLabel())
                    ->color(fn (Invitation $record) => match (true) {
                        $record->no_response_at !== null && $record->attendance_status === 'not_attending' => 'gray',
                        $record->attendance_status === 'attending' => 'success',
                        $record->attendance_status === 'representative_attending' => 'info',
                        $record->attendance_status === 'not_attending' => 'danger',
                        default => 'warning',
                    })
                    ->tooltip(fn (Invitation $record) => $record->no_response_at
                        ? __('Never answered; counted as not attending since :time', ['time' => $record->no_response_at->translatedFormat('j M, g:i A')])
                        : null),
                TextColumn::make('invited_at')
                    ->label(__('Invited'))
                    ->since()
                    ->placeholder(__('Not sent'))
                    ->sortable(),
                TextColumn::make('representative_name')
                    ->label(__('Representative'))
                    ->placeholder(__('—'))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('submitted_by_email')
                    ->label(__('Submitted By'))
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
                    ->options(fn () => Invitation::statusOptions()),
                TernaryFilter::make('invited')
                    ->label(__('Invitation sent'))
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
                        ->label(__('Send / resend to selected'))
                        ->icon('heroicon-o-envelope')
                        ->requiresConfirmation()
                        ->modalDescription(__('Sends the invitation email to each selected guest (PA copied), including guests who were already invited.'))
                        ->action(fn ($records) => static::sendMany($records)),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function sendOneAction(): Action
    {
        return Action::make('send')
            ->label(fn (Invitation $record) => $record->invited_at ? __('Resend') : __('Send'))
            ->icon('heroicon-o-paper-airplane')
            ->color(fn (Invitation $record) => $record->invited_at ? 'gray' : 'primary')
            ->requiresConfirmation()
            ->modalHeading(fn (Invitation $record) => $record->invited_at
                ? __('Resend invitation to :name', ['name' => $record->vip_name])
                : __('Send invitation to :name', ['name' => $record->vip_name]))
            ->modalDescription(fn (Invitation $record) => $record->recipientSummary()
                .($record->invited_at ? "\n".__('Last sent :time.', ['time' => $record->invited_at->diffForHumans()]) : ''))
            ->modalSubmitActionLabel(__('Send'))
            ->action(fn (Invitation $record) => static::sendMany([$record]));
    }

    public static function sendNotInvitedAction(Closure $query): Action
    {
        $notInvited = fn () => $query()->whereNull('invited_at');

        return Action::make('sendNotInvited')
            ->label(fn () => __('Send to everyone not yet invited (:count)', ['count' => $notInvited()->count()]))
            ->icon('heroicon-o-paper-airplane')
            ->requiresConfirmation()
            ->modalDescription(__('Emails every guest on this list who has not been sent an invitation yet. Guests already invited are skipped; use their "Resend" button for those.'))
            ->modalSubmitActionLabel(__('Send'))
            ->disabled(fn () => ! $notInvited()->exists())
            ->action(fn () => static::sendMany($notInvited()->with('contacts')->get()));
    }

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

        $notification = Notification::make()->title(__(':count invitation email(s) sent', ['count' => $sent]));
        $problems = [];

        if ($failed) {
            $problems[] = __('Could not send to: :names. Check the mail settings and try again.', ['names' => implode(', ', $failed)]);
        }

        if ($noAddress) {
            $problems[] = __('No email address (no VIP email and no contact): :names', ['names' => implode(', ', $noAddress)]);
        }

        if ($problems) {
            $notification->body(implode("\n", $problems))->{$failed ? 'danger' : 'warning'}()->persistent();
        } else {
            $notification->success();
        }

        $notification->send();
    }

    private static function customFieldColumns(): array
    {
        return CustomFieldDefinition::orderBy('sort_order')->get()
            ->map(fn (CustomFieldDefinition $field) => TextColumn::make('custom_'.$field->field_key)
                ->label($field->localizedLabel())
                ->state(fn (Invitation $record) => $record->customFieldValues->firstWhere('field_key', $field->field_key)?->value)
                ->placeholder(__('—'))
                ->toggleable(isToggledHiddenByDefault: true))
            ->all();
    }
}
