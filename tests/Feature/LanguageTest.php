<?php

namespace Tests\Feature;

use App\Filament\Pages\CustomFieldSettings;
use App\Filament\Pages\EmailTemplateSettings;
use App\Filament\Pages\RsvpFormSettings;
use App\Mail\InvitationEmail;
use App\Mail\ReminderEmail;
use App\Models\AppSetting;
use App\Models\CustomFieldDefinition;
use App\Models\EmailTemplate;
use App\Models\FormSettings;
use App\Models\Invitation;
use App\Models\InvitationContact;
use App\Models\Meeting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LanguageTest extends TestCase
{
    use RefreshDatabase;

    private function guest(): InvitationContact
    {
        $invitation = Invitation::create(['vip_name' => 'Dato A', 'vip_email' => 'a@example.com', 'attendance_status' => 'pending']);

        return $invitation->ensureVipContact();
    }

    private function signIn(): void
    {
        config(['services.google.allowed_emails' => 'admin@example.com']);
        $this->actingAs(User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'x']));
    }

    public function test_rsvp_page_opens_in_malay_and_guest_can_switch_to_english(): void
    {
        $contact = $this->guest();
        $url = '/rsvp/'.$contact->token;

        $this->get($url)->assertOk()->assertSee('Adakah anda akan hadir?')->assertSee('lang="ms"', false);

        $this->from($url)->get(route('language.switch', 'en'))->assertRedirect($url);
        $this->get($url)->assertSee('Will you be attending?')->assertDontSee('Adakah anda akan hadir?');

        $this->from($url)->get(route('language.switch', 'ms'));
        $this->get($url)->assertSee('Adakah anda akan hadir?');
    }

    public function test_malay_answers_are_validated_in_malay(): void
    {
        $contact = $this->guest();

        $this->from('/rsvp/'.$contact->token)
            ->post('/rsvp/'.$contact->token, ['attendance_status' => 'representative_attending'])
            ->assertSessionHasErrors(['representative_name' => 'Sila masukkan nama wakil anda.']);
    }

    public function test_saved_malay_wording_and_questions_are_shown_to_malay_guests(): void
    {
        FormSettings::create(FormSettings::DEFAULTS);
        AppSetting::putJson(AppSetting::FORM_WORDING_MS, ['attendance_question' => 'Sudikah hadir?']);
        CustomFieldDefinition::create(['field_key' => 'diet', 'label' => 'Dietary needs', 'sort_order' => 0, 'enabled' => true]);
        AppSetting::putJson(AppSetting::QUESTION_LABELS_MS, ['diet' => 'Keperluan pemakanan']);
        $contact = $this->guest();

        $this->get('/rsvp/'.$contact->token)->assertSee('Sudikah hadir?')->assertSee('Keperluan pemakanan');
        $this->get('/rsvp/'.$contact->token.'?lang=en')->assertSee('Will you be attending?')->assertSee('Dietary needs');
    }

    public function test_custom_english_wording_without_malay_version_is_kept(): void
    {
        FormSettings::create(['page_heading' => 'National Day 2026'] + FormSettings::DEFAULTS);

        $malay = FormSettings::current()->localized('ms');

        $this->assertSame('National Day 2026', $malay->page_heading);
        $this->assertSame('Adakah anda akan hadir?', $malay->attendance_question);
    }

    public function test_form_wording_page_saves_both_languages(): void
    {
        $this->signIn();

        Livewire::test(RsvpFormSettings::class)
            ->assertSet('data.ms.attendance_question', 'Adakah anda akan hadir?')
            ->set('data.ms.attendance_question', 'Sudikah tuan/puan hadir?')
            ->call('setPreviewLanguage', 'ms')
            ->assertSee('Sudikah tuan/puan hadir?')
            ->call('save');

        $this->assertSame('Sudikah tuan/puan hadir?', FormSettings::malayWording()['attendance_question']);
        $this->assertSame('Will you be attending?', FormSettings::current()->attendance_question);
    }

    public function test_extra_question_malay_label_is_saved(): void
    {
        $this->signIn();

        Livewire::test(CustomFieldSettings::class)
            ->set('data.fields', [['label' => 'Dietary needs', 'label_ms' => 'Keperluan pemakanan', 'enabled' => true]])
            ->call('save');

        $this->assertSame('Keperluan pemakanan', CustomFieldDefinition::first()->localizedLabel('ms'));
        $this->assertSame('Dietary needs', CustomFieldDefinition::first()->localizedLabel('en'));
    }

    public function test_built_in_meeting_invitation_and_reminder_contain_both_languages(): void
    {
        $meeting = Meeting::create(['title' => 'Budget Review', 'starts_at' => now()->addDay()->setTime(10, 0)]);
        $invitation = Invitation::create(['meeting_id' => $meeting->id, 'vip_name' => 'Dato A', 'vip_email' => 'a@example.com', 'attendance_status' => 'pending']);
        $contact = $invitation->ensureVipContact();

        $invite = new InvitationEmail($contact);
        $invite->assertHasSubject('Jemputan: Budget Review — sila beri maklum balas / Invitation: Budget Review — please respond');
        $invite->assertSeeInHtml('Yang Berbahagia Dato A,');
        $invite->assertSeeInHtml('Dear Dato A,');
        $invite->assertSeeInHtml('Balas sekarang / Respond now');

        $html = (new ReminderEmail($invitation, $contact, 120))->render();
        $this->assertStringContainsString('2 jam', $html);
        $this->assertStringContainsString('2 hours', $html);
        $this->assertLessThan(strpos($html, 'Dear Dato A'), strpos($html, 'Yang Berbahagia Dato A'));
    }

    public function test_custom_template_sends_malay_version_first(): void
    {
        $this->signIn();

        Livewire::test(EmailTemplateSettings::class)
            ->set('data.subject', 'Invitation for {vip_name}')
            ->set('data.body', '<p>Hello {vip_name}</p>')
            ->set('data.subject_ms', 'Jemputan untuk {vip_name}')
            ->set('data.body_ms', '<p>Salam {vip_name}</p>')
            ->call('save');

        $this->assertSame(1, EmailTemplate::count());
        $mail = new InvitationEmail($this->guest());
        $mail->assertHasSubject('Jemputan untuk Dato A / Invitation for Dato A');
        $mail->assertSeeInOrderInHtml(['Salam Dato A', 'Hello Dato A']);
    }

    public function test_admin_panel_is_english_by_default_with_a_malay_switch(): void
    {
        $this->signIn();

        $this->get('/admin')->assertOk()->assertSee('Settings')->assertSee('Bahasa Melayu');
    }

    public function test_admin_panel_switches_to_malay(): void
    {
        $this->signIn();

        $this->from('/admin')->get(route('language.switch', 'ms'))->assertRedirect('/admin');
        $this->get('/admin')->assertOk()->assertSee('Tetapan')->assertSee('Mesyuarat')->assertSee('English');
    }

    public function test_every_admin_page_opens_in_malay(): void
    {
        $this->signIn();
        $meeting = Meeting::create(['title' => 'Budget Review', 'starts_at' => now()->addDay()->setTime(10, 0)]);
        $guest = Invitation::create(['meeting_id' => $meeting->id, 'vip_name' => 'Dato A', 'vip_email' => 'a@example.com', 'attendance_status' => 'pending']);
        $bigEvent = Invitation::create(['vip_name' => 'Tan Sri B', 'attendance_status' => 'pending']);
        CustomFieldDefinition::create(['field_key' => 'diet', 'label' => 'Dietary needs', 'sort_order' => 0, 'enabled' => true]);
        $this->from('/admin')->get(route('language.switch', 'ms'));

        foreach ([
            '/admin', '/admin/meetings', '/admin/meetings/create', '/admin/meetings/'.$meeting->id.'/edit',
            '/admin/invitations', '/admin/invitations/create', '/admin/invitations/'.$bigEvent->id.'/edit',
            '/admin/rsvp-form-settings', '/admin/custom-field-settings', '/admin/email-template-settings',
            '/admin/reminder-settings', '/admin/calendar-connection', '/admin/import-invitations',
            '/admin/seating-plan', '/admin/seating-plan?meeting='.$meeting->id, '/admin/precedence-ranks',
        ] as $url) {
            $this->get($url)->assertOk()->assertSee('lang="ms"', false);
        }

        $this->assertNotNull($guest);
    }

    public function test_every_translation_key_in_the_code_has_a_malay_translation(): void
    {
        $malay = json_decode(file_get_contents(lang_path('ms.json')), true);
        $missing = [];

        foreach (['app', 'resources/views'] as $dir) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(base_path($dir)));
            foreach ($files as $file) {
                if (! str_ends_with($file->getFilename(), '.php')) {
                    continue;
                }
                preg_match_all("/(?:__|trans_choice)\(\s*'((?:[^'\\\\]|\\\\.)*)'/", file_get_contents($file->getPathname()), $m);
                foreach ($m[1] as $key) {
                    $key = str_replace("\\'", "'", $key);
                    if (! isset($malay[$key])) {
                        $missing[] = $key;
                    }
                }
            }
        }

        $this->assertSame([], array_values(array_unique($missing)));
    }
}
