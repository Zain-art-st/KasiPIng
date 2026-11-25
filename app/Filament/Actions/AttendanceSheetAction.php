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
            ->label(__('Attendance sheet'))
            ->icon('heroicon-o-printer')
            ->color('gray')
            ->modalHeading(__('Download attendance sheet'))
            ->modalDescription(__('A sign-in sheet grouped by ministry / company, with No., Name, Phone number and Signature. Guests who are attending (or sending a representative) are filled in; everyone else gets an empty row, so walk-ins can still sign.'))
            ->schema([
                ToggleButtons::make('format')
                    ->label(__('Format'))
                    ->options(['pdf' => __('PDF (print-ready)'), 'docx' => __('Word (editable)')])
                    ->default('pdf')
                    ->inline()
                    ->required(),
                TextInput::make('min_rows')
                    ->label(__('Minimum rows per ministry / company'))
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(20)
                    // Default number of rows per ministry on the sign-in sheet.
                    ->default(2)
                    ->required(),
                Placeholder::make('note')
                    ->hiddenLabel()
                    ->content(__('Every invited person always gets a row; this only adds extra blank rows to smaller groups.')),
            ])
            ->modalSubmitActionLabel(__('Download'))
            ->action(function (array $data) use ($sheet) {
                $built = $sheet();
                $built->minRows = (int) $data['min_rows'];

                return $built->download($data['format']);
            });
    }
}
