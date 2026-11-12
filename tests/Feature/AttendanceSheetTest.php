<?php

namespace Tests\Feature;

use App\Filament\Pages\ReminderSettings;
use App\Filament\Resources\Meetings\Pages\EditMeeting;
use App\Models\AppSetting;
use App\Models\Invitation;
use App\Models\Meeting;
use App\Models\User;
use App\Services\AttendanceSheet;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AttendanceSheetTest extends TestCase
{
    use RefreshDatabase;

    private function guests(Meeting $meeting): void
    {
        $rows = [
            ['Apip', 'Kementerian Dalam Negeri', '0181111111', 'attending'],
            ['Busu', 'Kementerian Pengangkutan', '0182222222', 'attending'],
            ['Chong', 'Kementerian Pengangkutan', '0183333333', 'not_attending'],
            ['Dewi', 'Kementerian Sains dan Budaya', '0184444444', 'pending'],
            ['Eddy', null, '0185555555', 'attending'],
        ];

        foreach ($rows as [$name, $org, $phone, $status]) {
            Invitation::create([
                'meeting_id' => $meeting->id, 'vip_name' => $name, 'organization' => $org,
                'vip_phone' => $phone, 'attendance_status' => $status,
            ]);
        }

        Invitation::create([
            'meeting_id' => $meeting->id, 'vip_name' => 'Tan Sri F', 'organization' => 'Kementerian Dalam Negeri',
            'vip_phone' => '0186666666', 'attendance_status' => 'representative_attending',
            'representative_name' => 'Encik G', 'representative_role' => 'Pegawai',
        ]);
    }

    public function test_groups_by_ministry_with_blank_rows_for_absentees_and_minimum_rows(): void
    {
        $meeting = Meeting::create(['title' => 'Mesyuarat', 'starts_at' => now()->addDay()]);
        $this->guests($meeting);

        $groups = (new AttendanceSheet('Mesyuarat', null, $meeting->invitations()->get(), minRows: 2))->groups();

        $this->assertSame(
            ['Kementerian Dalam Negeri', 'Kementerian Pengangkutan', 'Kementerian Sains dan Budaya', AttendanceSheet::NO_ORGANIZATION],
            array_column($groups, 'organization'),
        );

        $this->assertSame(['Apip', 'Encik G, Pegawai (for Tan Sri F)'], array_column($groups[0]['rows'], 'name'));
        $this->assertSame('0181111111', $groups[0]['rows'][0]['phone']);

        $this->assertSame(['Busu', ''], array_column($groups[1]['rows'], 'name'));

        $this->assertSame(['', ''], array_column($groups[2]['rows'], 'name'));

        $this->assertSame(['Eddy', ''], array_column($groups[3]['rows'], 'name'));

        $groups = (new AttendanceSheet('M', null, $meeting->invitations()->get(), minRows: 4))->groups();
        $this->assertCount(4, $groups[2]['rows']);
    }

    public function test_pdf_and_word_downloads_from_the_meeting_page(): void
    {
        config(['services.google.allowed_emails' => 'admin@example.com']);
        $this->actingAs(User::create(['name' => 'A', 'email' => 'admin@example.com', 'password' => 'x']));
        $meeting = Meeting::create(['title' => 'Mesyuarat Bulanan', 'starts_at' => now()->addDay(), 'location' => 'Bilik 1']);
        $this->guests($meeting);

        $page = Livewire::test(EditMeeting::class, ['record' => $meeting->getRouteKey()]);

        $page->callAction(TestAction::make('attendanceSheet'), ['format' => 'pdf', 'min_rows' => 2])
            ->assertHasNoActionErrors()
            ->assertFileDownloaded('Attendance - Mesyuarat Bulanan.pdf');

        $page->callAction(TestAction::make('attendanceSheet'), ['format' => 'docx', 'min_rows' => 2])
            ->assertFileDownloaded('Attendance - Mesyuarat Bulanan.docx');
    }

    public function test_real_files_are_valid(): void
    {
        $meeting = Meeting::create(['title' => 'Mesyuarat', 'starts_at' => now()->addDay()]);
        $this->guests($meeting);
        $sheet = new AttendanceSheet('Mesyuarat', 'Isnin', $meeting->invitations()->get());

        ob_start();
        $sheet->pdf()->sendContent();
        $pdf = ob_get_clean();
        $this->assertStringStartsWith('%PDF', $pdf);

        ob_start();
        $sheet->word()->sendContent();
        $docx = ob_get_clean();
        $this->assertStringStartsWith('PK', $docx);

        $path = tempnam(sys_get_temp_dir(), 'att').'.docx';
        file_put_contents($path, $docx);
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($path) === true);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        unlink($path);
        $this->assertStringContainsString('Kementerian Sains dan Budaya', $xml);
        $this->assertStringContainsString('Signature', $xml);
    }

    public function test_reminder_settings_page_saves_the_interval(): void
    {
        config(['services.google.allowed_emails' => 'admin@example.com']);
        $this->actingAs(User::create(['name' => 'A', 'email' => 'admin@example.com', 'password' => 'x']));

        $this->get('/admin/reminder-settings')->assertOk()->assertSee('How reminders work');

        Livewire::test(ReminderSettings::class)
            ->assertSet('data.interval', '0')
            ->set('data.interval', '6')
            ->call('save');

        $this->assertSame(6, AppSetting::reminderIntervalHours());
    }
}
