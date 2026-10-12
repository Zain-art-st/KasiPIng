<?php

namespace App\Filament\Widgets;

use App\Models\Invitation;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class InvitationStatsOverview extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '5s';
    protected function getStats(): array
{
    $standalone = fn () => Invitation::whereNull('meeting_id');

    return [
        Stat::make('Total Invited', $standalone()->count()),
        Stat::make('Attending', $standalone()->where('attendance_status', 'attending')->count())
            ->color('success'),
        Stat::make('Representative', $standalone()->where('attendance_status', 'representative_attending')->count())
            ->color('info'),
        Stat::make('Not Attending', $standalone()->where('attendance_status', 'not_attending')->count())
            ->color('danger'),
        Stat::make('Pending', $standalone()->where('attendance_status', 'pending')->count())
            ->color('warning'),
    ];
}
}