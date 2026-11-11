<?php

namespace Tests\Feature;

use App\Mail\ReminderEmail;
use App\Models\Invitation;
use App\Models\InvitationContact;
use App\Models\Meeting;
use App\Models\ReminderLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MeetingRemindersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->travelTo(now()->startOfMinute());
    }

    private function meetingIn(int $minutes, array $attrs = []): Meeting
    {
        return Meeting::create($attrs + [
            'title' => 'Board Meeting',
            'starts_at' => now()->addMinutes($minutes),
        ]);
    }

    private function invite(Meeting $meeting, array $attrs = [], array $contacts = [['PA One', 'pa1@example.com']]): Invitation
    {
        $invitation = Invitation::create($attrs + [
            'meeting_id' => $meeting->id,
            'vip_name' => 'Dato Example',
            'vip_email' => 'vip@example.com',
            'attendance_status' => 'pending',
        ]);

        foreach ($contacts as [$name, $email]) {
            InvitationContact::create(['invitation_id' => $invitation->id, 'name' => $name, 'email' => $email]);
        }

        return $invitation->fresh();
    }

    public function test_pending_invitation_gets_reminder_in_its_window(): void
    {
        $meeting = $this->meetingIn(1435);
        $pending = $this->invite($meeting);
        $this->invite($meeting, ['vip_name' => 'Responded', 'attendance_status' => 'attending']);

        $this->artisan('reminders:dispatch')->expectsOutput('Dispatched 1 reminder(s).')->assertSuccessful();

        $this->assertSame('sent', ReminderLog::where('invitation_id', $pending->id)->value('status'));
        $this->assertSame(1440, ReminderLog::first()->interval_minutes);
        Mail::assertSent(ReminderEmail::class, fn (ReminderEmail $m) => $m->hasTo('vip@example.com'));
        Mail::assertSentCount(1);
    }

    public function test_running_twice_does_not_send_twice(): void
    {
        $this->invite($this->meetingIn(1435));

        $this->artisan('reminders:dispatch');
        $this->artisan('reminders:dispatch')->expectsOutput('Dispatched 0 reminder(s).');

        Mail::assertSentCount(1);
        $this->assertSame(1, ReminderLog::count());
    }

    public function test_nothing_outside_a_window_or_for_cancelled_meetings(): void
    {
        $this->invite($this->meetingIn(100));   // between the 120 and 60 windows
        $this->invite($this->meetingIn(1435, ['cancelled_at' => now()]));
        $this->invite($this->meetingIn(-5));     // already started

        $this->artisan('reminders:dispatch')->expectsOutput('Dispatched 0 reminder(s).');

        Mail::assertNothingSent();
    }

    public function test_before_escalation_only_the_vip_is_emailed(): void
    {
        $this->invite($this->meetingIn(355)); // 360 window, above the 180 escalation point

        $this->artisan('reminders:dispatch');

        Mail::assertSent(ReminderEmail::class, fn (ReminderEmail $m) => $m->hasTo('vip@example.com') && ! $m->hasCc('pa1@example.com'));
        Mail::assertSentCount(1);
    }

    public function test_from_escalation_each_pa_gets_their_own_link(): void
    {
        $invitation = $this->invite($this->meetingIn(175), [], [
            ['PA One', 'pa1@example.com'],
            ['PA Two', 'pa2@example.com'],
        ]);
        [$pa1, $pa2] = $invitation->contacts->sortBy('id')->values()->all();

        $this->artisan('reminders:dispatch');

        Mail::assertSent(ReminderEmail::class, fn (ReminderEmail $m) => $m->hasTo('vip@example.com'));
        Mail::assertSent(ReminderEmail::class, fn (ReminderEmail $m) => $m->hasTo('pa1@example.com') && $m->contact->is($pa1));
        Mail::assertSent(ReminderEmail::class, fn (ReminderEmail $m) => $m->hasTo('pa2@example.com') && $m->contact->is($pa2));
        Mail::assertSentCount(3);
        $this->assertSame('sent', ReminderLog::first()->status);
    }

    public function test_reminder_email_renders_with_the_contacts_link(): void
    {
        $invitation = $this->invite($this->meetingIn(25));
        $contact = $invitation->contacts->first();

        $html = (new ReminderEmail($invitation, $invitation->meeting, 30, $contact, $contact->email))->render();

        $this->assertStringContainsString($contact->rsvpUrl(), $html);
        $this->assertStringContainsString('30 minutes', $html);
    }

    public function test_database_queue_is_drained_by_the_scheduled_worker_command(): void
    {
        config(['queue.default' => 'database']);
        $this->invite($this->meetingIn(1435));

        $this->artisan('reminders:dispatch');
        Mail::assertNothingSent();
        $this->assertSame('queued', ReminderLog::first()->status);

        $this->artisan('queue:work database --stop-when-empty --max-time=50 --tries=3')->assertSuccessful();

        Mail::assertSentCount(1);
        $this->assertSame('sent', ReminderLog::first()->status);
    }

    public function test_vip_without_pa_gets_a_contact_and_a_reminder(): void
    {
        $invitation = $this->invite($this->meetingIn(1435), [], []);

        $this->artisan('reminders:dispatch');

        Mail::assertSent(ReminderEmail::class, fn (ReminderEmail $m) => $m->hasTo('vip@example.com')
            && $m->contact->email === 'vip@example.com');
        $this->assertSame(1, $invitation->contacts()->count());
    }

    public function test_invitation_without_contacts_is_logged_as_skipped(): void
    {
        $this->invite($this->meetingIn(1435), ['vip_email' => null], []);

        $this->artisan('reminders:dispatch');

        Mail::assertNothingSent();
        $this->assertSame('skipped', ReminderLog::first()->status);
    }
}
