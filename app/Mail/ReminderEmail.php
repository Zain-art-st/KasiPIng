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
        $subject = fn ($locale) => __($this->urgent() ? 'URGENT: :title — please respond' : 'Reminder: :title — please respond',
            ['title' => $this->title($locale)], $locale);

        return new Envelope(
            to: [$vipEmail ?: $this->contact->email],
            cc: $copyContact ? [$this->contact->email] : [],
            replyTo: array_filter([config('mail.reply_to_address')]),
            subject: implode(' / ', InvitationEmail::inBothLanguages($subject)),
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
                'title' => InvitationEmail::inBothLanguages(fn ($locale) => $this->title($locale)),
                'isMeeting' => (bool) $meeting,
                'when' => InvitationEmail::inBothLanguages(fn ($locale) => $deadline?->copy()->locale($locale)->translatedFormat('D, j M Y · g:i A')),
                'location' => $meeting?->location,
                'leadLabel' => InvitationEmail::inBothLanguages(fn ($locale) => $this->leadLabel($locale)),
                'urgent' => $this->urgent(),
                'rsvpUrl' => $this->contact->rsvpUrl(),
            ],
        );
    }

    private function title(string $locale): string
    {
        return $this->invitation->meeting?->title ?? FormSettings::current()->localized($locale)->page_heading;
    }

    // Reminders sent within this many minutes of the start are marked URGENT.
    private function urgent(): bool
    {
        return $this->minutesLeft <= 60;
    }

    public function leadLabel(string $locale = 'en'): string
    {
        $minutes = $this->minutesLeft;

        if ($minutes >= 1440) {
            return trans_choice(':count day|:count days', intdiv($minutes, 1440), [], $locale);
        }

        if ($minutes >= 60) {
            return trans_choice(':count hour|:count hours', intdiv($minutes, 60), [], $locale);
        }

        return trans_choice(':count minute|:count minutes', $minutes, [], $locale);
    }
}
