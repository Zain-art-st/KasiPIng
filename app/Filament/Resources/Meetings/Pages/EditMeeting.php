<?php

namespace App\Filament\Resources\Meetings\Pages;

use App\Filament\Resources\Meetings\MeetingResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMeeting extends EditRecord
{
    protected static string $resource = MeetingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('seatingPlan')
                ->label('Seating plan')
                ->icon('heroicon-o-squares-2x2')
                ->url(fn () => \App\Filament\Pages\SeatingPlanPage::getUrl(['meeting' => $this->record->getKey()])),
            \App\Filament\Actions\AttendanceSheetAction::make(fn () => new \App\Services\AttendanceSheet(
                title: $this->record->title,
                subtitle: $this->record->starts_at->format('l, j F Y · g:i A')
                    .($this->record->location ? ' · '.$this->record->location : ''),
                invitations: $this->record->invitations()->get(),
            )),
            DeleteAction::make(),
        ];
    }
}
