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
        subject: 'Independence Day Attendance Confirmation — '.$invitation->vip_name,
    );
}

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.invitation',
            with: [
                'vipName' => $this->contact->invitation->vip_name,
                'contactName' => $this->contact->name,
                'rsvpUrl' => route('rsvp.show', $this->contact->token),
            ],
        );
    }
}