<?php

namespace App\Filament\Resources\PrecedenceRanks;

use App\Filament\Resources\PrecedenceRanks\Pages\ManagePrecedenceRanks;
use App\Models\PrecedenceRank;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class PrecedenceRankResource extends Resource
{
    protected static ?string $model = PrecedenceRank::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedListBullet;

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('Settings');
    }

    public static function getNavigationLabel(): string
    {
        return __('Kekananan List');
    }

    public static function getModelLabel(): string
    {
        return __('kekananan entry');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Kekananan List');
    }

    protected static ?int $navigationSort = 6;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('after_id')
                ->label(__('Place directly after'))
                ->helperText(__('Where this entry sits in the order of precedence.'))
                ->searchable()
                ->getSearchResultsUsing(fn (string $search) => PrecedenceRank::options($search))
                ->getOptionLabelUsing(fn ($value) => PrecedenceRank::find($value)?->label())
                ->visibleOn('create'),
            TextInput::make('code')
                ->label(__('Warta number'))
                ->helperText(__('Only for a category from the Warta (e.g. 38 or 17A). Leave empty for a named position.'))
                ->maxLength(10),
            TextInput::make('title')
                ->label(__('Position / category'))
                ->required()
                ->maxLength(255),
            TextInput::make('holder')
                ->label(__('Current holder'))
                ->maxLength(255),
            TextInput::make('aliases')
                ->label(__('Short forms'))
                ->helperText(__('Comma-separated, used when searching (e.g. KSU, TKSU, SUK Johor).'))
                ->maxLength(255),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->reorderable('sort')
            ->paginated([50, 100, 'all'])
            ->defaultPaginationPageOption(100)
            ->columns([
                TextColumn::make('rank_no')
                    ->label(__('#'))
                    ->state(fn (PrecedenceRank $record) => PrecedenceRank::where('sort', '<=', $record->sort)->count())
                    ->color('gray'),
                TextColumn::make('code')
                    ->label(__('Warta'))
                    ->badge()
                    ->placeholder(''),
                TextColumn::make('title')
                    ->label(__('Position / category'))
                    ->weight(fn (PrecedenceRank $record) => $record->isCategory() ? 'bold' : null)
                    ->wrap()
                    ->searchable(),
                TextColumn::make('holder')
                    ->label(__('Current holder'))
                    ->placeholder(__('—'))
                    ->wrap()
                    ->searchable(),
                TextColumn::make('aliases')
                    ->label(__('Short forms'))
                    ->placeholder(__('—'))
                    ->searchable()
                    ->toggleable(),
            ])
            ->filters([
                TernaryFilter::make('categories')
                    ->label(__('Show'))
                    ->placeholder(__('Everything'))
                    ->trueLabel(__('Warta categories only'))
                    ->falseLabel(__('Named positions only'))
                    ->queries(
                        true: fn (Builder $q) => $q->whereNotNull('code'),
                        false: fn (Builder $q) => $q->whereNull('code'),
                    ),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePrecedenceRanks::route('/'),
        ];
    }
}
