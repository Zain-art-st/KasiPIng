<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\CalendarConnection;
use App\Models\GoogleConnection;
use Carbon\Carbon;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Cache;

/**
 * Dashboard banner shown only when something background-related is broken:
 * the scheduler has stopped (so no reminders or calendar sync), or the
 * Google Calendar sync is failing.
 */
class SystemHealthWarning extends Widget
{
    protected string $view = 'filament.widgets.system-health-warning';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = -10;

    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        return static::problems() !== [];
    }

    /** @return array<int, array{title: string, message: string, url: ?string}> */
    public static function problems(): array
    {
        $problems = [];

        $lastRun = Cache::get('scheduler:last_run');

        if (! $lastRun || Carbon::parse($lastRun)->lt(now()->subMinutes(10))) {
            $problems[] = [
                'title' => 'Scheduler is not running',
                'message' => ($lastRun ? 'Last run '.Carbon::parse($lastRun)->diffForHumans().'. ' : 'It has never run here. ')
                    .'Reminders and calendar sync are paused. On Laravel Cloud, enable the Scheduler toggle on the App cluster and redeploy.',
                'url' => null,
            ];
        }

        $connection = GoogleConnection::first();

        if ($connection?->last_error) {
            $problems[] = [
                'title' => 'Google Calendar sync is failing',
                'message' => $connection->last_error,
                'url' => CalendarConnection::getUrl(),
            ];
        }

        return $problems;
    }

    protected function getViewData(): array
    {
        return ['problems' => static::problems()];
    }
}
