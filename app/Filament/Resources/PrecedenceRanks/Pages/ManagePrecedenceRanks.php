<?php

namespace App\Filament\Resources\PrecedenceRanks\Pages;

use App\Filament\Resources\PrecedenceRanks\PrecedenceRankResource;
use App\Models\PrecedenceRank;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Facades\Storage;

class ManagePrecedenceRanks extends ManageRecords
{
    protected static string $resource = PrecedenceRankResource::class;

    public function getSubheading(): ?string
    {
        return 'The order of precedence used to rank guests and fill seating plans. Top = most senior. '
            .'Drag rows to reorder, or export to Excel/CSV, edit, and import it back.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Add entry')
                ->mutateDataUsing(function (array $data) {
                    $after = PrecedenceRank::find($data['after_id'] ?? null);
                    unset($data['after_id']);
                    $data['sort'] = $after ? $after->sort + 5 : (int) PrecedenceRank::max('sort') + 10;

                    return $data;
                })
                ->after(fn () => PrecedenceRank::renumber()),

            Action::make('export')
                ->label('Export CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(fn () => response()->streamDownload(
                    fn () => print (PrecedenceRank::toCsv()),
                    'Kekananan '.now()->format('Y-m-d').'.csv',
                    ['Content-Type' => 'text/csv; charset=UTF-8'],
                )),

            Action::make('import')
                ->label('Import CSV')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->modalDescription('Replaces the whole list with the file, in the file\'s order (columns: code, title, holder, aliases). '
                    .'Tip: export first, edit in Excel, then import. Guests keep their ranks when an entry keeps the same Warta number, or the same title and holder.')
                ->schema([
                    FileUpload::make('file')
                        ->label('CSV file')
                        ->acceptedFileTypes(['text/csv', 'text/plain', 'application/vnd.ms-excel'])
                        ->disk('local')
                        ->directory('precedence-imports')
                        ->required(),
                ])
                ->action(function (array $data) {
                    $path = Storage::disk('local')->path(is_array($data['file']) ? array_values($data['file'])[0] : $data['file']);

                    try {
                        $count = PrecedenceRank::importRows(PrecedenceRank::rowsFromCsv($path));
                        Notification::make()->title("Imported {$count} entries")->success()->send();
                    } catch (\Throwable $e) {
                        Notification::make()->title('Import failed')->body($e->getMessage())->danger()->send();
                    } finally {
                        @unlink($path);
                    }
                }),

            ActionGroup::make(
                collect(PrecedenceRank::sourceFiles())->map(fn ($path, $name) => Action::make('source_'.md5($name))
                    ->label($name)
                    ->icon('heroicon-o-document')
                    ->action(fn () => response()->download($path, $name)))
                    ->values()
                    ->all()
            )
                ->label('Source documents')
                ->icon('heroicon-o-folder-open')
                ->color('gray')
                ->button(),
        ];
    }
}
