<?php

namespace App\Mail;

use App\Models\InvitationContact;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvitationEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public InvitationContact $contact,
    ) {}

    public function envelope(): Envelope
{
    $invitation = $this->contact->invitation;

    return new Envelope(
        to: $invitation->vip_email
            ? [$invitation->vip_email]
            : [$this->contact->email],
        cc: $invitation->vip_email
            ? [$this->contact->email]
            : [],
        replyTo: [config('mail.reply_to_address', 'afiflegend2006@gmail.com@gmail.com')],
        subject: $invitation->meeting
        ? 'Invitation: '.$invitation->meeting->title.' — please respond'
        : 'Independence Day Attendance Confirmation — '.$invitation->vip_name,
    );
}

    public function content(): Content
{
    $invitation = $this->contact->invitation;
    $meeting = $invitation->meeting;

    return new Content(
        markdown: $meeting ? 'emails.meeting-invitation' : 'emails.invitation',
        with: [
            'vipName' => $invitation->vip_name,
            'attendeeName' => $invitation->vip_name,
            'contactName' => $this->contact->name,
            'rsvpUrl' => $this->contact->rsvpUrl(),
            'meetingTitle' => $meeting?->title,
            'startsAt' => $meeting?->starts_at->format('D, j M Y · g:i A'),
            'location' => $meeting?->location,
        ],
    );
}
}