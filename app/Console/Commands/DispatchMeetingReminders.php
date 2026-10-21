<?php

namespace App\Console\Commands;

use App\Jobs\SendMeetingReminder;
use App\Models\Meeting;
use App\Models\ReminderLog;
use Illuminate\Console\Command;

class DispatchMeetingReminders extends Command
{
    protected $signature = 'reminders:dispatch';

    protected $description = 'Queue escalating reminders for pending meeting attendees';

    public function handle(): int
    {
        $intervals = config('reminders.intervals');
        $grace = config('reminders.grace_minutes');
        $now = now();

        $meetings = Meeting::query()
            ->whereNull('cancelled_at')
            ->where('starts_at', '>', $now)
            ->where('starts_at', '<=', $now->copy()->addMinutes(max($intervals)))
            ->get();

        $dispatched = 0;

        foreach ($meetings as $meeting) {
            $minutesToStart = ($meeting->starts_at->getTimestamp() - $now->getTimestamp()) / 60;

            foreach ($intervals as $interval) {
                if ($minutesToStart > $interval || $minutesToStart <= $interval - $grace) {
                    continue;
                }

                $pending = $meeting->invitations()->where('attendance_status', 'pending')->get();

                foreach ($pending as $invitation) {
                    $log = ReminderLog::firstOrCreate(
                        ['invitation_id' => $invitation->id, 'interval_minutes' => $interval],
                        ['status' => 'queued'],
                    );

                    if ($log->wasRecentlyCreated) {
                        SendMeetingReminder::dispatch($log->id);
                        $dispatched++;
                    }
                }
            }
        }

        $this->info("Dispatched {$dispatched} reminder(s).");

        return self::SUCCESS;
    }
}