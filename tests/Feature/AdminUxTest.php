<?php

namespace Tests\Feature;

use App\Filament\Pages\CustomFieldSettings;
use App\Filament\Pages\RsvpFormSettings;
use App\Filament\Resources\Invitations\Pages\ListInvitations;
use App\Filament\Resources\Meetings\Pages\EditMeeting;
use App\Filament\Resources\Meetings\RelationManagers\InvitationsRelationManager;
use App\Mail\InvitationEmail;
use App\Models\CustomFieldDefinition;
use App\Models\CustomFieldValue;
use App\Models\FormSettings;
use App\Models\Invitation;
use App\Models\InvitationContact;
use App\Models\Meeting;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class AdminUxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.google.allowed_emails' => 'admin@example.com']);
        $this->actingAs(User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'x']));
        Mail::fake();
    }

    public function test_form_wording_page_starts_with_defaults_and_previews_live(): void
    {
        $this->get('/admin/rsvp-form-settings')->assertOk()
            ->assertSee('Live preview')
            ->assertSee('Will you be attending?');

        Livewire::test(RsvpFormSettings::class)
            ->assertSet('data.attending_label', 'Yes, attending')
            ->set('data.attending_label', 'Hadir')
            ->assertSee('Hadir')
            ->call('setPreviewSubmitted', true)
            ->assertSee('Thank you')
            ->call('save');

        $this->assertSame('Hadir', FormSettings::current()->attending_label);
    }

    public function test_guest_page_uses_the_saved_labels_and_defaults_on_a_fresh_database(): void
    {
        $invitation = Invitation::create(['vip_name' => 'Tan Sri A', 'vip_email' => 'a@example.com', 'attendance_status' => 'pending']);
        $contact = $invitation->ensureVipContact();

        $this->get('/rsvp/'.$contact->token)->assertOk()
            ->assertSee('Estimated arrival time')
            ->assertSee('Sending a representative');

        FormSettings::create(['arrival_label' => 'Waktu tiba', 'representative_label' => 'Wakil hadir']);

        $this->get('/rsvp/'.$contact->token)->assertOk()
            ->assertSee('Waktu tiba')
            ->assertSee('Wakil hadir')
            ->assertSee('Will you be attending?');
    }

    public function test_extra_questions_preview_shows_unsaved_questions(): void
    {
        $this->get('/admin/custom-field-settings')->assertOk()->assertSee('How extra questions work');

        Livewire::test(CustomFieldSettings::class)
            ->set('data.fields', ['a' => ['label' => 'Dietary needs', 'enabled' => true, 'field_key' => null]])
            ->assertSee('Dietary needs');
    }

    public function test_sidebar_has_the_three_folders(): void
    {
        $this->get('/admin')->assertOk()
            ->assertSeeInOrder(['Meetings', 'Big Event RSVP', 'Guest List', 'Settings', 'RSVP Form Wording', 'Google Calendar']);
    }

    public function test_send_one_guest_from_a_meeting_and_mark_invited(): void
    {
        $meeting = Meeting::create(['title' => 'Board', 'starts_at' => now()->addDay()]);
        $one = Invitation::create(['meeting_id' => $meeting->id, 'vip_name' => 'One', 'vip_email' => 'one@example.com', 'attendance_status' => 'pending']);
        $two = Invitation::create(['meeting_id' => $meeting->id, 'vip_name' => 'Two', 'vip_email' => 'two@example.com', 'attendance_status' => 'pending']);

        Livewire::test(InvitationsRelationManager::class, ['ownerRecord' => $meeting, 'pageClass' => EditMeeting::class])
            ->callAction(TestAction::make('send')->table($one));

        Mail::assertSent(InvitationEmail::class, fn ($m) => $m->hasTo('one@example.com'));
        Mail::assertSentCount(1);
        $this->assertNotNull($one->fresh()->invited_at);
        $this->assertNull($two->fresh()->invited_at);

        Livewire::test(InvitationsRelationManager::class, ['ownerRecord' => $meeting, 'pageClass' => EditMeeting::class])
            ->callAction(TestAction::make('sendNotInvited')->table());

        Mail::assertSent(InvitationEmail::class, fn ($m) => $m->hasTo('two@example.com'));
        Mail::assertSentCount(2);
    }

    public function test_big_event_list_only_sends_to_standalone_guests(): void
    {
        $meeting = Meeting::create(['title' => 'Board', 'starts_at' => now()->addDay()]);
        Invitation::create(['meeting_id' => $meeting->id, 'vip_name' => 'Meeting guest', 'vip_email' => 'm@example.com', 'attendance_status' => 'pending']);
        $guest = Invitation::create(['vip_name' => 'Event guest', 'vip_email' => 'e@example.com', 'attendance_status' => 'pending']);
        InvitationContact::create(['invitation_id' => $guest->id, 'name' => 'PA', 'email' => 'pa@example.com']);

        Livewire::test(ListInvitations::class)
            ->assertSee('Send to everyone not yet invited (1)')
            ->callAction(TestAction::make('sendNotInvited')->table());

        Mail::assertSentCount(1);
        Mail::assertSent(InvitationEmail::class, fn ($m) => $m->hasTo('e@example.com') && $m->hasCc('pa@example.com'));
    }

    public function test_edit_form_shows_answers_instead_of_additional_details(): void
    {
        CustomFieldDefinition::create(['field_key' => 'diet', 'label' => 'Dietary needs', 'enabled' => true, 'sort_order' => 0]);
        $invitation = Invitation::create(['vip_name' => 'Guest', 'attendance_status' => 'pending']);
        CustomFieldValue::create(['invitation_id' => $invitation->id, 'field_key' => 'diet', 'value' => 'Vegetarian']);

        $this->get('/admin/invitations/'.$invitation->id.'/edit')->assertOk()
            ->assertSee('Answers to extra questions')
            ->assertSee('Vegetarian')
            ->assertDontSee('Add Detail');
    }
}
