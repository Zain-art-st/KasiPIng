<?php

namespace App\Filament\Resources\Meetings\RelationManagers;

use App\Filament\Resources\Invitations\Schemas\InvitationForm;
use App\Filament\Resources\Invitations\Tables\InvitationsTable;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use App\Filament\Pages\ImportInvitations;
use Filament\Actions\Action;
use Filament\Tables\Table;

class InvitationsRelationManager extends RelationManager
{
    protected static string $relationship = 'invitations';

    protected static ?string $title = 'Attendees';

    public function form(Schema $schema): Schema
    {
        return InvitationForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return InvitationsTable::configure($table)
            ->headerActions([
            Action::make('importCsv')
            ->label('Import CSV')
            ->icon('heroicon-o-arrow-up-tray')
            ->url(fn () => ImportInvitations::getUrl(['meeting' => $this->getOwnerRecord()->getKey()])),
            CreateAction::make(),
        ]);
    }
}