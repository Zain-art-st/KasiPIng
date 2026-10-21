<?php

namespace App\Jobs;

use App\Mail\ReminderEmail;
use App\Models\ReminderLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendMeetingReminder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(public int $logId) {}

    public function handle(): void
    {
        $log = ReminderLog::with('invitation.meeting', 'invitation.contacts')->find($this->logId);

        if (! $log || $log->status === 'sent') {
            return;
        }

        $invitation = $log->invitation;
        $meeting = $invitation?->meeting;

        $skipReason = match (true) {
            ! $invitation || ! $meeting => 'Invitation or meeting no longer exists',
            $meeting->cancelled_at !== null => 'Meeting cancelled',
            $meeting->starts_at->isPast() => 'Meeting already started',
            $invitation->attendance_status !== 'pending' => 'Already responded',
            $invitation->contacts->isEmpty() => 'No contact to send to',
            default => null,
        };

        if ($skipReason) {
            $log->update(['status' => 'skipped', 'detail' => $skipReason]);

            return;
        }

        Mail::send(new ReminderEmail($invitation, $meeting, $log->interval_minutes));

        $log->update(['status' => 'sent', 'detail' => null]);
    }

    public function failed(\Throwable $exception): void
    {
        ReminderLog::whereKey($this->logId)->update([
            'status' => 'failed',
            'detail' => mb_substr($exception->getMessage(), 0, 500),
        ]);
    }
}