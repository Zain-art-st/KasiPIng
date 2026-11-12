<?php

namespace App\Mail;

use App\Models\FormSettings;
use App\Models\Invitation;
use App\Models\InvitationContact;
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
        public InvitationContact $contact,
        public int $minutesLeft,
    ) {}

    public function envelope(): Envelope
    {
        $vipEmail = $this->invitation->vip_email;
        $copyContact = $vipEmail && strcasecmp($vipEmail, $this->contact->email) !== 0;
        $prefix = $this->urgent() ? 'URGENT: ' : 'Reminder: ';

        return new Envelope(
            to: [$vipEmail ?: $this->contact->email],
            cc: $copyContact ? [$this->contact->email] : [],
            replyTo: array_filter([config('mail.reply_to_address')]),
            subject: $prefix.$this->title().' — please respond',
        );
    }

    public function content(): Content
    {
        $meeting = $this->invitation->meeting;
        $deadline = $this->invitation->responseDeadline();

        return new Content(
            markdown: 'emails.reminder',
            with: [
                'attendeeName' => $this->invitation->vip_name,
                'title' => $this->title(),
                'isMeeting' => (bool) $meeting,
                'when' => $deadline?->format('D, j M Y · g:i A'),
                'location' => $meeting?->location,
                'leadLabel' => $this->leadLabel(),
                'urgent' => $this->urgent(),
                'rsvpUrl' => $this->contact->rsvpUrl(),
            ],
        );
    }

    private function title(): string
    {
        return $this->invitation->meeting?->title ?? FormSettings::current()->page_heading;
    }

    private function urgent(): bool
    {
        return $this->minutesLeft <= 60;
    }

    public function leadLabel(): string
    {
        $minutes = $this->minutesLeft;

        if ($minutes >= 1440) {
            $days = intdiv($minutes, 1440);

            return $days.' '.($days === 1 ? 'day' : 'days');
        }

        if ($minutes >= 60) {
            $hours = intdiv($minutes, 60);

            return $hours.' '.($hours === 1 ? 'hour' : 'hours');
        }

        return $minutes.' '.($minutes === 1 ? 'minute' : 'minutes');
    }
}
