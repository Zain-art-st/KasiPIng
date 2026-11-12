<?php

namespace Tests\Feature;

use App\Mail\ReminderEmail;
use App\Models\AppSetting;
use App\Models\Invitation;
use App\Models\InvitationContact;
use App\Models\Meeting;
use App\Models\ReminderLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MeetingRemindersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->travelTo(Carbon::parse('2026-11-20 14:00:00'));
    }

    private function meetingAt(string $time, array $attrs = []): Meeting
    {
        return Meeting::create($attrs + ['title' => 'Board Meeting', 'starts_at' => Carbon::parse('2026-11-20 '.$time)]);
    }

    private function invite(Meeting $meeting, array $attrs = [], array $contacts = [['PA One', 'pa1@example.com']]): Invitation
    {
        $invitation = Invitation::create($attrs + [
            'meeting_id' => $meeting->id,
            'vip_name' => 'Dato Example',
            'vip_email' => 'vip@example.com',
            'attendance_status' => 'pending',
            'invited_at' => now(),
        ]);

        foreach ($contacts as [$name, $email]) {
            InvitationContact::create(['invitation_id' => $invitation->id, 'name' => $name, 'email' => $email]);
        }

        return $invitation->fresh();
    }

    private function runAt(array $times): int
    {
        foreach ($times as $time) {
            $this->travelTo(Carbon::parse('2026-11-20 '.$time));
            $this->artisan('reminders:dispatch')->assertSuccessful();
        }

        return Mail::sent(ReminderEmail::class)->count();
    }

    private function everyMinuteUntil(string $end): array
    {
        $times = [];
        for ($t = Carbon::parse('2026-11-20 14:00'); $t->lt(Carbon::parse('2026-11-20 '.$end)); $t->addMinutes(5)) {
            $times[] = $t->format('H:i:s');
        }

        return $times;
    }

    public function test_reminders_are_off_by_default(): void
    {
        $this->invite($this->meetingAt('18:00'));

        $this->assertSame(0, $this->runAt($this->everyMinuteUntil('18:00')));
        $this->assertSame(0, ReminderLog::count());
    }

    public function test_every_two_hours_from_2pm_for_a_6pm_meeting_sends_once_at_4pm(): void
    {
        AppSetting::put(AppSetting::REMINDER_INTERVAL_HOURS, 2);
        $this->invite($this->meetingAt('18:00'));

        $this->assertSame(1, $this->runAt($this->everyMinuteUntil('18:00')));
        $this->assertEquals(Carbon::parse('2026-11-20 16:00'), ReminderLog::sole()->due_at);
        $this->assertSame('sent', ReminderLog::sole()->status);
    }

    public function test_every_hour_sends_at_3_4_and_5pm(): void
    {
        AppSetting::put(AppSetting::REMINDER_INTERVAL_HOURS, 1);
        $this->invite($this->meetingAt('18:00'));

        $this->assertSame(3, $this->runAt($this->everyMinuteUntil('18:00')));
        $this->assertEquals(
            ['15:00', '16:00', '17:00'],
            ReminderLog::orderBy('due_at')->get()->map(fn ($l) => $l->due_at->format('H:i'))->all(),
        );
    }

    public function test_reminders_stop_once_the_guest_answers(): void
    {
        AppSetting::put(AppSetting::REMINDER_INTERVAL_HOURS, 1);
        $invitation = $this->invite($this->meetingAt('18:00'));

        $this->assertSame(1, $this->runAt(['15:00:30']));
        $invitation->update(['attendance_status' => 'attending']);

        $this->assertSame(1, $this->runAt(['16:00:30', '17:00:30']));
    }

    public function test_never_invited_cancelled_and_responded_guests_get_no_reminders(): void
    {
        AppSetting::put(AppSetting::REMINDER_INTERVAL_HOURS, 1);
        $meeting = $this->meetingAt('18:00');
        $this->invite($meeting, ['invited_at' => null, 'vip_name' => 'Never invited']);
        $this->invite($meeting, ['attendance_status' => 'attending', 'vip_name' => 'Answered']);
        $this->invite($this->meetingAt('18:00', ['cancelled_at' => now()]), ['vip_name' => 'Cancelled']);

        $this->assertSame(0, $this->runAt(['15:00:30', '16:00:30']));
    }

    public function test_reminder_goes_to_vip_with_pa_copied_and_pa_link(): void
    {
        AppSetting::put(AppSetting::REMINDER_INTERVAL_HOURS, 2);
        $invitation = $this->invite($this->meetingAt('18:00'));

        $this->runAt(['16:00:30']);

        Mail::assertSent(ReminderEmail::class, fn (ReminderEmail $m) => $m->hasTo('vip@example.com')
            && $m->hasCc('pa1@example.com')
            && $m->contact->is($invitation->contacts->first()));

        $html = (new ReminderEmail($invitation, $invitation->contacts->first(), 120))->render();
        $this->assertStringContainsString($invitation->contacts->first()->rsvpUrl(), $html);
        $this->assertStringContainsString('2 hours', $html);
    }

    public function test_big_event_guests_are_reminded_only_until_their_link_expires(): void
    {
        AppSetting::put(AppSetting::REMINDER_INTERVAL_HOURS, 4);
        $guest = Invitation::create([
            'vip_name' => 'Event guest', 'vip_email' => 'g@example.com', 'attendance_status' => 'pending',
            'invited_at' => now(), 'expires_at' => Carbon::parse('2026-11-21 08:00'),
        ]);
        Invitation::create([
            'vip_name' => 'No deadline', 'vip_email' => 'n@example.com', 'attendance_status' => 'pending', 'invited_at' => now(),
        ]);

        foreach (['18:00:30', '22:00:30', '23:59:00'] as $t) {
            $this->runAt([$t]);
        }
        $this->travelTo(Carbon::parse('2026-11-21 02:00:30'));
        $this->artisan('reminders:dispatch');
        $this->travelTo(Carbon::parse('2026-11-21 06:00:30'));
        $this->artisan('reminders:dispatch');
        $this->travelTo(Carbon::parse('2026-11-21 10:00:30'));
        $this->artisan('reminders:dispatch');

        $this->assertSame(4, ReminderLog::where('invitation_id', $guest->id)->count());
        Mail::assertNotSent(ReminderEmail::class, fn ($m) => $m->hasTo('n@example.com'));
    }

    public function test_unanswered_guests_count_as_not_attending_once_the_meeting_starts(): void
    {
        $meeting = $this->meetingAt('18:00');
        $pending = $this->invite($meeting, ['vip_name' => 'Silent']);
        $answered = $this->invite($meeting, ['vip_name' => 'Yes', 'attendance_status' => 'attending']);
        $other = $this->invite($this->meetingAt('20:00'), ['vip_name' => 'Later meeting']);

        $this->travelTo(Carbon::parse('2026-11-20 17:59'));
        $this->artisan('invitations:close-unanswered');
        $this->assertSame('pending', $pending->fresh()->attendance_status);

        $this->travelTo(Carbon::parse('2026-11-20 18:00:30'));
        $this->artisan('invitations:close-unanswered')->expectsOutput('Marked 1 unanswered invitation(s) as not attending.');

        $this->assertSame('not_attending', $pending->fresh()->attendance_status);
        $this->assertSame('No response', $pending->fresh()->statusLabel());
        $this->assertSame('attending', $answered->fresh()->attendance_status);
        $this->assertSame('pending', $other->fresh()->attendance_status);

        $pending->fresh()->update(['attendance_status' => 'attending']);
        $this->assertNull($pending->fresh()->no_response_at);
        $this->assertSame('Attending', $pending->fresh()->statusLabel());
    }

    public function test_database_queue_is_drained_by_the_scheduled_worker_command(): void
    {
        config(['queue.default' => 'database']);
        AppSetting::put(AppSetting::REMINDER_INTERVAL_HOURS, 2);
        $this->invite($this->meetingAt('18:00'));

        $this->runAt(['16:00:30']);
        Mail::assertNothingSent();
        $this->assertSame('queued', ReminderLog::sole()->status);

        $this->artisan('queue:work database --stop-when-empty --max-time=50 --tries=3')->assertSuccessful();

        Mail::assertSentCount(1);
        $this->assertSame('sent', ReminderLog::sole()->status);
    }
}
