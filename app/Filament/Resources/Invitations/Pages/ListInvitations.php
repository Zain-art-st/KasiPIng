<?php

namespace App\Filament\Resources\Invitations\Pages;

use App\Filament\Resources\Invitations\InvitationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListInvitations extends ListRecords
{
    protected static string $resource = InvitationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \App\Filament\Actions\AttendanceSheetAction::make(fn () => new \App\Services\AttendanceSheet(
                title: \App\Models\FormSettings::current()->page_heading,
                subtitle: null,
                invitations: \App\Models\Invitation::whereNull('meeting_id')->get(),
            )),
            CreateAction::make(),
        ];
    }
}
