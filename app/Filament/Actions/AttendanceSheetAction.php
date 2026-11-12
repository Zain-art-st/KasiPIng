<?php

namespace App\Filament\Actions;

use App\Services\AttendanceSheet;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;

class AttendanceSheetAction
{
    public static function make(Closure $sheet): Action
    {
        return Action::make('attendanceSheet')
            ->label('Attendance sheet')
            ->icon('heroicon-o-printer')
            ->color('gray')
            ->modalHeading('Download attendance sheet')
            ->modalDescription('A sign-in sheet grouped by ministry / company, with No., Name, Phone number and Signature. '
                .'Guests who are attending (or sending a representative) are filled in; everyone else gets an empty row, so walk-ins can still sign.')
            ->schema([
                ToggleButtons::make('format')
                    ->label('Format')
                    ->options(['pdf' => 'PDF (print-ready)', 'docx' => 'Word (editable)'])
                    ->default('pdf')
                    ->inline()
                    ->required(),
                TextInput::make('min_rows')
                    ->label('Minimum rows per ministry / company')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(20)
                    ->default(2)
                    ->required(),
                Placeholder::make('note')
                    ->hiddenLabel()
                    ->content('Every invited person always gets a row; this only adds extra blank rows to smaller groups.'),
            ])
            ->modalSubmitActionLabel('Download')
            ->action(function (array $data) use ($sheet) {
                $built = $sheet();
                $built->minRows = (int) $data['min_rows'];

                return $built->download($data['format']);
            });
    }
}
