<?php

namespace App\Mail;

use App\Models\EmailTemplate;
use App\Models\InvitationContact;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvitationEmail extends Mailable
{
    use Queueable, SerializesModels;

    /** Drop a PDF here (it is committed with the code) and every invitation carries it. */
    public const ATTACHMENT_PATH = 'attachments/invitation.pdf';

    public function __construct(
        public InvitationContact $contact,
    ) {}

    public function envelope(): Envelope
    {
        $invitation = $this->contact->invitation;
        $vipEmail = $invitation->vip_email;
        $copyContact = $vipEmail && strcasecmp($vipEmail, $this->contact->email) !== 0;

        return new Envelope(
            to: [$vipEmail ?: $this->contact->email],
            cc: $copyContact ? [$this->contact->email] : [],
            replyTo: array_filter([config('mail.reply_to_address')]),
            subject: ($template = $this->template())
                ? $this->fill($template->subject, escape: false)
                : $this->defaultSubject(),
        );
    }

    public function content(): Content
    {
        $invitation = $this->contact->invitation;
        $meeting = $invitation->meeting;

        if ($template = $this->template()) {
            $usesLink = str_contains($template->body, '{rsvp_link}') || str_contains($template->body, '%7Brsvp_link%7D');

            return new Content(
                markdown: 'emails.custom-invitation',
                with: [
                    'bodyHtml' => $this->fill($template->body, escape: true),
                    'rsvpUrl' => $this->contact->rsvpUrl(),
                    'showButton' => ! $usesLink,
                ],
            );
        }

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

    public function attachments(): array
    {
        $path = resource_path(self::ATTACHMENT_PATH);

        return is_file($path)
            ? [Attachment::fromPath($path)->as('Invitation.pdf')->withMime('application/pdf')]
            : [];
    }

    /** A saved template (subject and body both filled in) replaces the built-in wording. */
    private function template(): ?EmailTemplate
    {
        return EmailTemplate::query()
            ->where('subject', '!=', '')
            ->where('body', '!=', '')
            ->first();
    }

    private function defaultSubject(): string
    {
        $invitation = $this->contact->invitation;

        return $invitation->meeting
            ? 'Invitation: '.$invitation->meeting->title.' — please respond'
            : 'Independence Day Attendance Confirmation — '.$invitation->vip_name;
    }

    /** Replace {placeholders}; values are HTML-escaped when filling the body. */
    private function fill(string $text, bool $escape): string
    {
        $invitation = $this->contact->invitation;
        $meeting = $invitation->meeting;
        $url = $this->contact->rsvpUrl();

        $values = [
            'vip_name' => $invitation->vip_name,
            'contact_name' => $this->contact->name,
            'organization' => $invitation->organization,
            'meeting_title' => $meeting?->title,
            'meeting_time' => $meeting?->starts_at->format('D, j M Y · g:i A'),
            'location' => $meeting?->location,
            'rsvp_link' => $url,
        ];

        $replace = [];
        foreach ($values as $key => $value) {
            $value = (string) $value;

            if ($escape && $key === 'rsvp_link') {
                // A bare placeholder becomes a clickable link; one already inside an href stays a URL.
                $replace['href="{rsvp_link}"'] = 'href="'.e($url).'"';
                $replace['href="%7Brsvp_link%7D"'] = 'href="'.e($url).'"';
                $value = '<a href="'.e($url).'">'.e($url).'</a>';
            } elseif ($escape) {
                $value = e($value);
            }

            $replace['{'.$key.'}'] = $value;
        }

        return strtr($text, $replace);
    }
}
