<?php

namespace Tests\Feature;

use App\Filament\Pages\SeatingPlanPage;
use App\Filament\Resources\PrecedenceRanks\Pages\ManagePrecedenceRanks;
use App\Models\Invitation;
use App\Models\Meeting;
use App\Models\PrecedenceRank;
use App\Models\SeatingPlan;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

class SeatingPlanTest extends TestCase
{
    use RefreshDatabase;

    private Meeting $meeting;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.google.allowed_emails' => 'admin@example.com']);
        $this->actingAs(User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'x']));
        $this->meeting = Meeting::create(['title' => 'Mesyuarat Pengurusan', 'starts_at' => now()->addDay()]);
    }

    private function rank(string $term): PrecedenceRank
    {
        return PrecedenceRank::bestMatch($term);
    }

    private function guest(string $name, string $rankTerm, string $status = 'attending', array $extra = []): Invitation
    {
        return Invitation::create($extra + [
            'meeting_id' => $this->meeting->id,
            'vip_name' => $name,
            'organization' => 'Kementerian X',
            'attendance_status' => $status,
            'precedence_rank_id' => $this->rank($rankTerm)->id,
        ]);
    }

    public function test_the_ladder_is_seeded_in_warta_order(): void
    {
        $this->assertGreaterThan(240, PrecedenceRank::count());
        $first = PrecedenceRank::orderBy('sort')->first();
        $this->assertSame('1', $first->code);
        $this->assertSame('Yang di-Pertuan Agong', $first->title);

        $ksn = $this->rank('KSN');
        $ksuKdn = PrecedenceRank::where('title', 'Ketua Setiausaha, Kementerian Dalam Negeri')->first();
        $jusaA = $this->rank('JUSA A');
        $this->assertTrue($ksn->sort < $ksuKdn->sort && $ksuKdn->sort < $jusaA->sort);
        $this->assertSame('38', PrecedenceRank::where('sort', '<', $ksuKdn->sort)->whereNotNull('code')->orderByDesc('sort')->first()->code);
    }

    public function test_kekananan_page_exports_and_imports(): void
    {
        $this->get('/admin/precedence-ranks')->assertOk()->assertSee('Kekananan List')->assertSee('Source documents');

        $csv = PrecedenceRank::toCsv();
        $this->assertStringContainsString('code,title,holder,aliases', $csv);

        $edited = str_replace('Setiausaha Politik', 'Setiausaha Politik Menteri', $csv);
        $path = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($path, $edited);
        $before = PrecedenceRank::where('code', '65')->value('id');

        PrecedenceRank::importRows(PrecedenceRank::rowsFromCsv($path));

        $this->assertSame('Setiausaha Politik Menteri', PrecedenceRank::where('code', '65')->value('title'));
        $this->assertSame($before, PrecedenceRank::where('code', '65')->value('id'));
        unlink($path);

        Livewire::test(ManagePrecedenceRanks::class)
            ->callAction(TestAction::make('export'))
            ->assertFileDownloaded();
    }

    public function test_page_starts_empty_then_fills_by_rank_with_chair_and_host(): void
    {
        $tm = $this->guest('Timbalan', 'Timbalan Menteri Dalam Negeri');
        $ksu = $this->guest('KSU', 'Ketua Setiausaha, Kementerian Dalam Negeri');
        $jusa = $this->guest('Pengarah', 'JUSA A');
        $this->guest('Belum jawab', 'JUSA B', 'pending');

        $this->get('/admin/seating-plan?meeting='.$this->meeting->id)->assertOk()
            ->assertSee('Choose a seating type to start')
            ->assertSee('3 attending');

        $page = Livewire::test(SeatingPlanPage::class, ['meeting' => $this->meeting->id])
            ->call('chooseLayout', 'u');

        $plan = $page->get('plan');
        $this->assertSame(['1' => 'inv:'.$tm->id, '2' => 'inv:'.$ksu->id, '3' => 'inv:'.$jusa->id], $plan['seats']);

        $page->set('plan.chair', 'inv:'.$jusa->id)->set('plan.host', 'inv:'.$ksu->id);
        $plan = $page->get('plan');
        $this->assertSame('inv:'.$jusa->id, $plan['seats']['1']);
        $this->assertSame('inv:'.$ksu->id, $plan['seats']['2']);
        $this->assertSame('inv:'.$tm->id, $plan['seats']['3']);

        $this->assertSame($plan, SeatingPlan::where('context', 'meeting:'.$this->meeting->id)->first()->data);
        $page->assertSee('PENGERUSI')->assertSee('TUAN RUMAH');
    }

    public function test_drag_swap_replace_and_add_someone_not_on_the_list(): void
    {
        $a = $this->guest('Menteri A', 'Menteri Dalam Negeri');
        $b = $this->guest('KSU B', 'Ketua Setiausaha, Kementerian Dalam Negeri');
        $c = $this->guest('Pengarah C', 'JUSA C');

        $page = Livewire::test(SeatingPlanPage::class, ['meeting' => $this->meeting->id])
            ->call('chooseLayout', 'line')
            ->call('dropOnSeat', 1, 'seat', '3');

        $this->assertSame('inv:'.$c->id, $page->get('plan')['seats']['1']);
        $page->assertSee('Someone more senior sits further from the chair');

        $page->callAction(TestAction::make('addPerson')->arguments(['role' => 'chair']), [
            'name' => 'Datuk Pengerusi Luar',
            'org' => 'Agensi Luar',
            'title' => 'Pengerusi Lembaga',
            'rank_id' => $this->rank('KSN')->id,
        ])->assertHasNoActionErrors();

        $plan = $page->get('plan');
        $chair = $plan['chair'];
        $this->assertSame('Datuk Pengerusi Luar', $plan['people'][$chair]['name']);
        $this->assertSame($chair, $plan['seats']['1']);

        $page->callAction(TestAction::make('replace')->arguments(['seq' => 2]), [
            'is_new' => true,
            'name' => 'Pengganti Menteri',
            'rank_id' => $this->rank('Menteri Dalam Negeri')->id,
        ])->assertHasNoActionErrors();

        $plan = $page->get('plan');
        $this->assertSame('Pengganti Menteri', $plan['people'][$plan['seats']['2']]['name']);
        $this->assertFalse($plan['people']['inv:'.$a->id]['present']);
    }

    public function test_round_tables_absent_people_and_pdf(): void
    {
        foreach (range(1, 12) as $i) {
            $this->guest('Tetamu '.$i, $i <= 4 ? 'Menteri Kesihatan' : 'JUSA B');
        }

        $page = Livewire::test(SeatingPlanPage::class, ['meeting' => $this->meeting->id])
            ->set('plan.config.per_table', 8)
            ->call('chooseLayout', 'round');

        $this->assertSame(2, $page->get('plan')['config']['tables']);
        $this->assertCount(12, $page->get('plan')['seats']);
        $page->assertSee('Meja Utama')->assertSee('Meja 1');

        $first = $page->get('plan')['seats']['1'];
        $page->call('setPresent', $first, false);
        $this->assertCount(11, $page->get('plan')['seats']);
        $this->assertNotSame($first, $page->get('plan')['seats']['1']);

        $page->callAction(TestAction::make('downloadPdf'))
            ->assertFileDownloaded('Seating plan - Mesyuarat Pengurusan.pdf');
    }

    public function test_invitation_form_and_csv_import_use_the_ladder(): void
    {
        $this->get('/admin/invitations/create')->assertOk()->assertSee('Kekananan (rank)');
        $this->assertSame('38', $this->rank('KSU')->code);
        $this->assertSame('Ketua Setiausaha Kementerian', $this->rank('ketua setiausaha kementerian')->title);
    }

    public function test_big_event_seating_page_and_entry_buttons(): void
    {
        Invitation::create(['vip_name' => 'Big guest', 'attendance_status' => 'attending']);

        $this->get('/admin/invitations')->assertOk()->assertSee('Seating plan');
        $this->get('/admin/meetings/'.$this->meeting->id.'/edit')->assertOk()->assertSee('Seating plan');
        $this->get('/admin/seating-plan')->assertOk()->assertSee('1 attending');
    }
}
