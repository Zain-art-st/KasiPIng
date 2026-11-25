<?php

namespace Tests\Feature;

use App\Models\Invitation;
use App\Models\InvitationContact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RsvpLinkTest extends TestCase
{
    use RefreshDatabase;

    private Invitation $invitation;

    private InvitationContact $vip;

    private InvitationContact $pa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->invitation = Invitation::create([
            'vip_name' => 'Dato Example',
            'vip_email' => 'vip@example.com',
            'attendance_status' => 'pending',
        ]);
        $this->vip = $this->invitation->ensureVipContact();
        $this->pa = InvitationContact::create(['invitation_id' => $this->invitation->id, 'name' => 'PA', 'email' => 'pa@example.com']);
    }

    private function answer(InvitationContact $contact, array $data)
    {
        return $this->post('/rsvp/'.$contact->token, $data + [
            'vehicle_registration' => '',
            'estimated_arrival' => '',
            'estimated_departure' => '',
            'submitted_by_name' => '',
        ]);
    }

    public function test_unknown_or_altered_token_shows_the_invalid_page_and_saves_nothing(): void
    {
        $this->get('/rsvp/'.$this->pa->token.'x')->assertOk()->assertViewIs('rsvp.invalid');
        $this->post('/rsvp/not-a-real-token', ['attendance_status' => 'attending'])->assertNotFound();

        $this->assertSame('pending', $this->invitation->fresh()->attendance_status);
    }

    public function test_expired_link_is_rejected(): void
    {
        $this->invitation->update(['expires_at' => now()->subMinute()]);

        $this->get('/rsvp/'.$this->pa->token)->assertViewIs('rsvp.invalid');
        $this->answer($this->pa, ['attendance_status' => 'attending'])->assertNotFound();
    }

    public function test_guest_answers_without_logging_in_and_the_first_answer_is_locked(): void
    {
        $this->get('/rsvp/'.$this->pa->token)->assertOk()->assertSee('Dato Example');

        $this->answer($this->pa, ['attendance_status' => 'attending'])->assertRedirect('/rsvp/'.$this->pa->token);

        $saved = $this->invitation->fresh();
        $this->assertSame('attending', $saved->attendance_status);
        $this->assertSame('pa@example.com', $saved->submitted_by_email);
        $this->assertTrue($saved->is_locked);

        $this->answer($this->vip, ['attendance_status' => 'not_attending']);

        $this->assertSame('attending', $this->invitation->fresh()->attendance_status);
        $this->get('/rsvp/'.$this->vip->token)->assertSee('telah direkodkan');
        $this->get('/rsvp/'.$this->vip->token.'?lang=en')->assertSee('already been recorded');
    }

    public function test_representative_needs_a_name_and_is_recorded(): void
    {
        $this->answer($this->pa, ['attendance_status' => 'representative_attending'])
            ->assertSessionHasErrors('representative_name');
        $this->assertSame('pending', $this->invitation->fresh()->attendance_status);

        $this->answer($this->pa, [
            'attendance_status' => 'representative_attending',
            'representative_name' => 'Encik Wakil',
            'representative_role' => 'Timbalan Pengarah',
        ]);

        $saved = $this->invitation->fresh();
        $this->assertSame('representative_attending', $saved->attendance_status);
        $this->assertSame('Encik Wakil', $saved->representative_name);
    }

    public function test_submissions_are_rate_limited(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->post('/rsvp/'.$this->pa->token, ['attendance_status' => 'invalid-value']);
        }

        $this->post('/rsvp/'.$this->pa->token, ['attendance_status' => 'attending'])->assertStatus(429);
    }
}
