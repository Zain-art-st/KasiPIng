<?php

namespace App\Mail;

use App\Models\Invitation;
use App\Models\Meeting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReminderEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Invitation $invitation,
        public Meeting $meeting,
        public int $intervalMinutes,
    ) {}

    public function envelope(): Envelope
    {
        $contacts = $this->contacts();
        $primary = $this->invitation->vip_email ?: $contacts->first()->email;

        $cc = [];

        if ($this->intervalMinutes <= config('reminders.escalate_to_pa_from')) {
            $cc = $contacts->pluck('email')
                ->reject(fn ($email) => strcasecmp($email, $primary) === 0)
                ->values()
                ->all();
        }

        $prefix = $this->urgent() ? 'URGENT: ' : 'Reminder: ';

        return new Envelope(
            to: [$primary],
            cc: $cc,
            replyTo: array_filter([config('mail.reply_to_address')]),
            subject: $prefix.$this->meeting->title.' starts in '.$this->leadLabel().' — please respond',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.reminder',
            with: [
                'attendeeName' => $this->invitation->vip_name,
                'meetingTitle' => $this->meeting->title,
                'startsAt' => $this->meeting->starts_at->format('D, j M Y · g:i A'),
                'location' => $this->meeting->location,
                'leadLabel' => $this->leadLabel(),
                'urgent' => $this->urgent(),
                'rsvpUrl' => $this->contacts()->first()->rsvpUrl(),
            ],
        );
    }

    private function contacts()
    {
        return $this->invitation->contacts->sortBy('id')->values();
    }

    private function urgent(): bool
    {
        return $this->intervalMinutes <= 60;
    }

    private function leadLabel(): string
    {
        $minutes = $this->intervalMinutes;

        if ($minutes >= 60) {
            $hours = intdiv($minutes, 60);

            return $hours.' '.($hours === 1 ? 'hour' : 'hours');
        }

        return $minutes.' minutes';
    }
}