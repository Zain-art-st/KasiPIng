<?php

namespace App\Filament\Resources\Meetings\Tables;

use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MeetingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount([
                'invitations',
                'invitations as responded_count' => fn (Builder $q) => $q->where('attendance_status', '!=', 'pending'),
            ]))
            ->columns([
                Stack::make([
                    TextColumn::make('title')
                        ->weight(FontWeight::Bold)
                        ->searchable(),
                    TextColumn::make('starts_at')
                        ->dateTime('D, j M Y · g:i A')
                        ->sortable()
                        ->color('gray'),
                    TextColumn::make('location')
                        ->placeholder('No location set')
                        ->color('gray'),
                    TextColumn::make('responded_count')
                        ->badge()
                        ->formatStateUsing(fn ($state, $record) => "{$state} of {$record->invitations_count} responded"),
                ]),
            ])
            ->contentGrid([
                'md' => 2,
                'xl' => 3,
            ])
            ->defaultSort('starts_at')
            ->filters([
                Filter::make('upcoming')
                    ->label('Upcoming only')
                    ->query(fn (Builder $query) => $query->where('starts_at', '>=', now()->startOfDay())),
            ]);
    }
}