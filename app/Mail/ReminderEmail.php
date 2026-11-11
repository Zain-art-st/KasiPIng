<?php

namespace App\Mail;

use App\Models\Invitation;
use App\Models\InvitationContact;
use App\Models\Meeting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * One reminder to one recipient. The RSVP link is always $contact's own link,
 * so "submitted by" names the person whose link was used.
 */
class ReminderEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Invitation $invitation,
        public Meeting $meeting,
        public int $intervalMinutes,
        public InvitationContact $contact,
        public string $recipient,
    ) {}

    public function envelope(): Envelope
    {
        $prefix = $this->urgent() ? 'URGENT: ' : 'Reminder: ';

        return new Envelope(
            to: [$this->recipient],
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
                'rsvpUrl' => $this->contact->rsvpUrl(),
            ],
        );
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
