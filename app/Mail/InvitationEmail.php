<?php

namespace App\Mail;

use App\Models\AppSetting;
use App\Models\EmailTemplate;
use App\Models\FormSettings;
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

    // Put a PDF at resources/attachments/invitation.pdf to attach it to every invitation; remove the file to stop.
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
                ? implode(' / ', array_map(fn ($t) => $this->fill($t['subject'], escape: false), $this->templateVersions($template)))
                : $this->defaultSubject(),
        );
    }

    public function content(): Content
    {
        $invitation = $this->contact->invitation;
        $meeting = $invitation->meeting;

        if ($template = $this->template()) {
            $versions = $this->templateVersions($template);
            $usesLink = collect($versions)->contains(fn ($t) => str_contains($t['body'], '{rsvp_link}') || str_contains($t['body'], '%7Brsvp_link%7D'));

            return new Content(
                markdown: 'emails.custom-invitation',
                with: [
                    'bodies' => array_map(fn ($t) => $this->fill($t['body'], escape: true), $versions),
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
                'startsAt' => self::inBothLanguages(fn ($locale) => $meeting?->starts_at->copy()->locale($locale)->translatedFormat('D, j M Y · g:i A')),
                'heading' => self::inBothLanguages(fn ($locale) => FormSettings::current()->localized($locale)->page_heading),
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

    // Emails carry both languages, Malay first. Reorder this list to put English first.
    public const LANGUAGES = ['ms', 'en'];

    public static function inBothLanguages(\Closure $value): array
    {
        return collect(self::LANGUAGES)->mapWithKeys(fn ($locale) => [$locale => $value($locale)])->all();
    }

    private function templateVersions(EmailTemplate $template): array
    {
        $malay = AppSetting::getJson(AppSetting::EMAIL_TEMPLATE_MS);
        $versions = ['en' => ['subject' => $template->subject, 'body' => $template->body]];

        if (filled($malay['body'] ?? null)) {
            $versions['ms'] = ['subject' => filled($malay['subject'] ?? null) ? $malay['subject'] : $template->subject, 'body' => $malay['body']];
        }

        return array_values(array_filter(array_map(fn ($locale) => $versions[$locale] ?? null, self::LANGUAGES)));
    }

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

        $subject = fn ($locale) => $invitation->meeting
            ? __('Invitation: :title — please respond', ['title' => $invitation->meeting->title], $locale)
            : FormSettings::current()->localized($locale)->page_heading.' — '.$invitation->vip_name;

        return implode(' / ', array_unique(self::inBothLanguages($subject)));
    }

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
            'meeting_time' => $meeting?->starts_at->translatedFormat('D, j M Y · g:i A'),
            'location' => $meeting?->location,
            'rsvp_link' => $url,
        ];

        $replace = [];
        foreach ($values as $key => $value) {
            $value = (string) $value;

            if ($escape && $key === 'rsvp_link') {
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
