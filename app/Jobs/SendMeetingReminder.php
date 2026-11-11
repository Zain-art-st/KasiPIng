<?php

namespace App\Jobs;

use App\Mail\ReminderEmail;
use App\Models\Invitation;
use App\Models\InvitationContact;
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

        if ($invitation?->ensureVipContact()) {
            $invitation->load('contacts');
        }

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

        $sent = 0;

        foreach ($this->recipients($invitation, $log->interval_minutes) as [$email, $contact]) {
            Mail::send(new ReminderEmail($invitation, $meeting, $log->interval_minutes, $contact, $email));
            $sent++;
        }

        $log->update(['status' => 'sent', 'detail' => $sent > 1 ? "{$sent} recipients" : null]);
    }

    /**
     * The VIP always gets the reminder (with their own link when they have a
     * contact of their own, otherwise the first contact's link, as in the
     * original invitation). From the escalation point, every other contact
     * gets a separate copy carrying their own link.
     *
     * @return array<int, array{0: string, 1: InvitationContact}>
     */
    private function recipients(Invitation $invitation, int $interval): array
    {
        $contacts = $invitation->contacts->sortBy('id')->values();
        $vipEmail = $invitation->vip_email;
        $recipients = [];

        if ($vipEmail) {
            $own = $contacts->first(fn ($c) => strcasecmp($c->email, $vipEmail) === 0);
            $recipients[] = [$vipEmail, $own ?? $contacts->first()];
        }

        $escalated = $interval <= config('reminders.escalate_to_pa_from');

        if ($escalated || ! $vipEmail) {
            foreach ($contacts as $contact) {
                if ($vipEmail && strcasecmp($contact->email, $vipEmail) === 0) {
                    continue;
                }
                $recipients[] = [$contact->email, $contact];
            }
        }

        return $recipients;
    }

    public function failed(\Throwable $exception): void
    {
        ReminderLog::whereKey($this->logId)->update([
            'status' => 'failed',
            'detail' => mb_substr($exception->getMessage(), 0, 500),
        ]);
    }
}