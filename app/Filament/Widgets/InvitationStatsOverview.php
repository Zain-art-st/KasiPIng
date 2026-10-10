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
        return [
            Stat::make('Total Invited', Invitation::count()),

            Stat::make('Attending', Invitation::where('attendance_status', 'attending')->count())
                ->color('success'),

            Stat::make('Not Attending', Invitation::where('attendance_status', 'not_attending')->count())
                ->color('danger'),

            Stat::make('Pending', Invitation::where('attendance_status', 'pending')->count())
                ->color('warning'),
        ];
    }
}