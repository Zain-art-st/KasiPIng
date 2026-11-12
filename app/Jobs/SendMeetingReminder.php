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
        $deadline = $invitation?->responseDeadline();

        $skipReason = match (true) {
            ! $invitation => 'Invitation no longer exists',
            $invitation->meeting?->cancelled_at !== null => 'Meeting cancelled',
            ! $deadline || $deadline->isPast() => 'Deadline already passed',
            $invitation->attendance_status !== 'pending' => 'Already responded',
            default => null,
        };

        if (! $skipReason) {
            $invitation->ensureVipContact();
            $contacts = $invitation->mailableContacts();
            $skipReason = $contacts->isEmpty() ? 'No email address' : null;
        }

        if ($skipReason) {
            $log->update(['status' => 'skipped', 'detail' => $skipReason]);

            return;
        }

        $minutesLeft = max(1, (int) ceil(now()->diffInSeconds($deadline) / 60));

        foreach ($contacts as $contact) {
            Mail::send(new ReminderEmail($invitation, $contact, $minutesLeft));
        }

        $log->update(['status' => 'sent', 'detail' => $contacts->count() > 1 ? $contacts->count().' emails' : null]);
    }

    public function failed(\Throwable $exception): void
    {
        ReminderLog::whereKey($this->logId)->update([
            'status' => 'failed',
            'detail' => mb_substr($exception->getMessage(), 0, 500),
        ]);
    }
}
