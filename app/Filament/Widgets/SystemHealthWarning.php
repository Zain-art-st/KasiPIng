<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\CalendarConnection;
use App\Models\GoogleConnection;
use Carbon\Carbon;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Cache;

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

    public static function problems(): array
    {
        $problems = [];

        $lastRun = Cache::get('scheduler:last_run');

        if (! $lastRun || Carbon::parse($lastRun)->lt(now()->subMinutes(10))) {
            $problems[] = [
                'title' => __('Scheduler is not running'),
                'message' => ($lastRun ? __('Last run :time.', ['time' => Carbon::parse($lastRun)->diffForHumans()]).' ' : __('It has never run here.').' ')
                    .__('Reminders and calendar sync are paused. On Laravel Cloud, enable the Scheduler toggle on the App cluster and redeploy.'),
                'url' => null,
            ];
        }

        $connection = GoogleConnection::first();

        if ($connection?->last_error) {
            $problems[] = [
                'title' => __('Google Calendar sync is failing'),
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
