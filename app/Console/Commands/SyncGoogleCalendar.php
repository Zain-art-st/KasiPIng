<?php

namespace App\Console\Commands;

use App\Services\GoogleCalendarSync;
use Illuminate\Console\Command;

class SyncGoogleCalendar extends Command
{
    protected $signature = 'calendar:sync';

    protected $description = 'Pull upcoming Google Calendar events into Meetings';

    public function handle(GoogleCalendarSync $sync): int
    {
        try {
            $this->info($sync->run());
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}