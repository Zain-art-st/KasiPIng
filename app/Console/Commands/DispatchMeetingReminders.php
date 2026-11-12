<?php

namespace App\Console\Commands;

use App\Jobs\SendMeetingReminder;
use App\Models\AppSetting;
use App\Models\Invitation;
use App\Models\ReminderLog;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class DispatchMeetingReminders extends Command
{
    protected $signature = 'reminders:dispatch';

    protected $description = 'Queue repeating reminders for guests who have not answered yet';

    public function handle(): int
    {
        $hours = AppSetting::reminderIntervalHours();

        if ($hours === 0) {
            $this->info('Reminders are off.');

            return self::SUCCESS;
        }

        // Reminders repeat every $hours (Settings → Reminders) counted from invited_at, until the meeting starts.
        $step = $hours * 3600;
        $now = now();
        $dispatched = 0;

        $invitations = Invitation::query()
            ->where('attendance_status', 'pending')
            ->whereNotNull('invited_at')
            ->where(fn (Builder $q) => $q
                ->whereHas('meeting', fn (Builder $m) => $m->whereNull('cancelled_at')->where('starts_at', '>', $now))
                ->orWhere(fn (Builder $s) => $s->whereNull('meeting_id')->where('expires_at', '>', $now)))
            ->with('meeting')
            ->get();

        foreach ($invitations as $invitation) {
            $deadline = $invitation->responseDeadline();
            $slot = intdiv($now->getTimestamp() - $invitation->invited_at->getTimestamp(), $step);

            if ($slot < 1) {
                continue;
            }

            $dueAt = $invitation->invited_at->copy()->addSeconds($slot * $step);

            if (! $deadline || $dueAt->gte($deadline)) {
                continue;
            }

            $log = ReminderLog::firstOrCreate(
                ['invitation_id' => $invitation->id, 'due_at' => $dueAt],
                [
                    'status' => 'queued',
                    'interval_minutes' => min(65535, (int) ceil(($deadline->getTimestamp() - $now->getTimestamp()) / 60)),
                ],
            );

            if ($log->wasRecentlyCreated) {
                SendMeetingReminder::dispatch($log->id);
                $dispatched++;
            }
        }

        $this->info("Dispatched {$dispatched} reminder(s).");

        return self::SUCCESS;
    }
}
