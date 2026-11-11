<?php

namespace Tests\Feature;

use App\Mail\InvitationEmail;
use App\Models\EmailTemplate;
use App\Models\GoogleConnection;
use App\Models\Invitation;
use App\Models\InvitationContact;
use App\Models\Meeting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvitationEmailTest extends TestCase
{
    use RefreshDatabase;

    private function invitation(array $attrs = []): Invitation
    {
        return Invitation::create($attrs + [
            'vip_name' => 'Dato <Example>',
            'vip_email' => 'vip@example.com',
            'attendance_status' => 'pending',
        ]);
    }

    public function test_vip_without_pa_gets_their_own_contact_once(): void
    {
        $invitation = $this->invitation();

        $contact = $invitation->ensureVipContact();
        $this->assertSame('vip@example.com', $contact->email);
        $this->assertNotEmpty($contact->token);
        $this->assertNull($invitation->ensureVipContact());
        $this->assertSame(1, $invitation->contacts()->count());
    }

    public function test_no_vip_contact_when_a_pa_exists_or_no_vip_email(): void
    {
        $withPa = $this->invitation();
        InvitationContact::create(['invitation_id' => $withPa->id, 'name' => 'PA', 'email' => 'pa@example.com']);
        $this->assertNull($withPa->ensureVipContact());

        $this->assertNull($this->invitation(['vip_email' => null])->ensureVipContact());
    }

    public function test_vip_is_not_mailed_twice_when_they_also_have_a_pa(): void
    {
        $invitation = $this->invitation();
        $invitation->ensureVipContact();
        InvitationContact::create(['invitation_id' => $invitation->id, 'name' => 'PA', 'email' => 'pa@example.com']);

        $this->assertSame(['pa@example.com'], $invitation->fresh()->mailableContacts()->pluck('email')->all());
    }

    public function test_vip_own_contact_is_not_copied_to_themselves(): void
    {
        $contact = $this->invitation()->ensureVipContact();

        $mail = new InvitationEmail($contact);

        $mail->assertTo('vip@example.com');
        $this->assertFalse($mail->hasCc('vip@example.com'));
    }

    public function test_built_in_email_is_used_without_a_template(): void
    {
        $contact = $this->invitation()->ensureVipContact();

        $mail = new InvitationEmail($contact);

        $mail->assertHasSubject('Independence Day Attendance Confirmation — Dato <Example>');
        $mail->assertSeeInHtml($contact->rsvpUrl());
    }

    public function test_saved_template_replaces_the_built_in_email(): void
    {
        $meeting = Meeting::create(['title' => 'Budget Talk', 'starts_at' => now()->addDay()]);
        $contact = $this->invitation(['meeting_id' => $meeting->id])->ensureVipContact();
        EmailTemplate::create([
            'subject' => 'Join us for {meeting_title}, {vip_name}',
            'body' => '<p>Dear {vip_name},</p><p><a href="{rsvp_link}">Reply here</a></p>',
        ]);

        $mail = new InvitationEmail($contact);

        $mail->assertHasSubject('Join us for Budget Talk, Dato <Example>');
        $mail->assertSeeInHtml('Dear Dato &lt;Example&gt;', false);
        $mail->assertSeeInHtml('href="'.$contact->rsvpUrl().'"', false);
        $mail->assertDontSeeInHtml('Respond now');
    }

    public function test_template_without_link_placeholder_gets_a_button(): void
    {
        $contact = $this->invitation()->ensureVipContact();
        EmailTemplate::create(['subject' => 'Hello', 'body' => '<p>Please reply.</p>']);

        $mail = new InvitationEmail($contact);

        $mail->assertSeeInHtml('Respond now');
        $mail->assertSeeInHtml($contact->rsvpUrl());
    }

    public function test_pdf_is_attached_only_when_present(): void
    {
        $contact = $this->invitation()->ensureVipContact();
        $path = resource_path(InvitationEmail::ATTACHMENT_PATH);
        $existed = is_file($path);

        if (! $existed) {
            $this->assertSame([], (new InvitationEmail($contact))->attachments());
            file_put_contents($path, '%PDF-1.4 test');
        }

        try {
            (new InvitationEmail($contact))->assertHasAttachment(
                \Illuminate\Mail\Mailables\Attachment::fromPath($path)->as('Invitation.pdf')->withMime('application/pdf')
            );
        } finally {
            if (! $existed) {
                unlink($path);
            }
        }
    }

    public function test_admin_pages_render(): void
    {
        config(['services.google.allowed_emails' => 'admin@example.com']);
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'x']);
        $meeting = Meeting::create(['title' => 'Gone', 'starts_at' => now()->addDay(), 'cancelled_at' => now()]);
        $this->invitation(['meeting_id' => $meeting->id])->ensureVipContact();
        GoogleConnection::create([
            'email' => 'cal@example.com', 'access_token' => 'a', 'refresh_token' => 'r',
            'last_error' => 'Google rejected the saved connection.',
        ]);

        $this->actingAs($admin);

        $this->get('/admin')->assertOk()
            ->assertSee('Google Calendar sync is failing')
            ->assertSee('Scheduler is not running');

        \Illuminate\Support\Facades\Http::fake(['*' => \Illuminate\Support\Facades\Http::response(['error' => 'invalid_grant'], 400)]);
        $this->artisan('schedule:run')->assertSuccessful();
        $this->get('/admin')->assertOk()->assertDontSee('Scheduler is not running');
        $this->get('/admin/meetings')->assertOk()->assertSee('Cancelled');
        $this->get('/admin/meetings/'.$meeting->id.'/edit')->assertOk();
        $this->get('/admin/invitations')->assertOk();
        $this->get('/admin/email-template-settings')->assertOk()->assertDontSee('Banner');
    }
}
