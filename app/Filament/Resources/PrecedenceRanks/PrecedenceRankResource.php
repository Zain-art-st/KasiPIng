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

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Kekananan List';

    protected static ?string $modelLabel = 'kekananan entry';

    protected static ?string $pluralModelLabel = 'Kekananan List';

    protected static ?int $navigationSort = 6;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('after_id')
                ->label('Place directly after')
                ->helperText('Where this entry sits in the order of precedence.')
                ->searchable()
                ->getSearchResultsUsing(fn (string $search) => PrecedenceRank::options($search))
                ->getOptionLabelUsing(fn ($value) => PrecedenceRank::find($value)?->label())
                ->visibleOn('create'),
            TextInput::make('code')
                ->label('Warta number')
                ->helperText('Only for a category from the Warta (e.g. 38 or 17A). Leave empty for a named position.')
                ->maxLength(10),
            TextInput::make('title')
                ->label('Position / category')
                ->required()
                ->maxLength(255),
            TextInput::make('holder')
                ->label('Current holder')
                ->maxLength(255),
            TextInput::make('aliases')
                ->label('Short forms')
                ->helperText('Comma-separated, used when searching (e.g. KSU, TKSU, SUK Johor).')
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
                    ->label('#')
                    ->state(fn (PrecedenceRank $record) => PrecedenceRank::where('sort', '<=', $record->sort)->count())
                    ->color('gray'),
                TextColumn::make('code')
                    ->label('Warta')
                    ->badge()
                    ->placeholder(''),
                TextColumn::make('title')
                    ->label('Position / category')
                    ->weight(fn (PrecedenceRank $record) => $record->isCategory() ? 'bold' : null)
                    ->wrap()
                    ->searchable(),
                TextColumn::make('holder')
                    ->label('Current holder')
                    ->placeholder('—')
                    ->wrap()
                    ->searchable(),
                TextColumn::make('aliases')
                    ->label('Short forms')
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(),
            ])
            ->filters([
                TernaryFilter::make('categories')
                    ->label('Show')
                    ->placeholder('Everything')
                    ->trueLabel('Warta categories only')
                    ->falseLabel('Named positions only')
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
