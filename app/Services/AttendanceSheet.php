<?php

namespace App\Services;

use App\Models\Invitation;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\SimpleType\JcTable;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceSheet
{
    public const NO_ORGANIZATION = 'Other (no ministry / company)';

    public function __construct(
        public string $title,
        public ?string $subtitle,
        public Collection $invitations,
        public int $minRows = 2,
    ) {}

    public function groups(): array
    {
        return $this->invitations
            ->groupBy(fn (Invitation $i) => trim((string) $i->organization) ?: self::NO_ORGANIZATION)
            ->sortKeysUsing(fn ($a, $b) => ($a === self::NO_ORGANIZATION) <=> ($b === self::NO_ORGANIZATION) ?: strcasecmp($a, $b))
            ->map(function (Collection $people, string $organization) {
                $filled = $people
                    ->filter(fn (Invitation $i) => in_array($i->attendance_status, ['attending', 'representative_attending'], true))
                    ->sortBy('vip_name', SORT_NATURAL | SORT_FLAG_CASE)
                    ->map(fn (Invitation $i) => $i->attendance_status === 'representative_attending'
                        ? [
                            'name' => trim(($i->representative_name ?: 'Representative')
                                .($i->representative_role ? ', '.$i->representative_role : '')
                                .' (for '.$i->vip_name.')'),
                            'phone' => '',
                        ]
                        : ['name' => $i->vip_name, 'phone' => (string) $i->vip_phone])
                    ->values()
                    ->all();

                $total = max($this->minRows, $people->count(), count($filled));
                $blank = array_fill(0, $total - count($filled), ['name' => '', 'phone' => '']);

                return ['organization' => $organization, 'rows' => array_merge($filled, $blank)];
            })
            ->values()
            ->all();
    }

    public function filename(string $extension): string
    {
        return 'Attendance - '.Str::of($this->title)->replaceMatches('/[^\pL\pN\s\-]+/u', '')->squish()->limit(60, '').'.'.$extension;
    }

    public function pdf(): StreamedResponse
    {
        $pdf = Pdf::loadView('exports.attendance-sheet', [
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'groups' => $this->groups(),
        ])->setPaper('a4');

        return response()->streamDownload(fn () => print ($pdf->output()), $this->filename('pdf'), [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function word(): StreamedResponse
    {
        $word = new PhpWord;
        $word->setDefaultFontName('Arial');
        $word->setDefaultFontSize(10);

        $section = $word->addSection(['marginTop' => 900, 'marginBottom' => 900, 'marginLeft' => 900, 'marginRight' => 900]);
        $section->addText($this->title, ['bold' => true, 'size' => 14], ['alignment' => Jc::CENTER]);

        if ($this->subtitle) {
            $section->addText($this->subtitle, ['size' => 10, 'color' => '555555'], ['alignment' => Jc::CENTER]);
        }

        $widths = [700, 4300, 2300, 2700];
        $border = ['borderSize' => 6, 'borderColor' => '000000', 'cellMargin' => 80, 'alignment' => JcTable::CENTER];

        foreach ($this->groups() as $group) {
            $section->addTextBreak(1);
            $table = $section->addTable($border);

            $table->addRow(400, ['cantSplit' => true, 'tblHeader' => true]);
            $table->addCell(array_sum($widths), ['gridSpan' => 4, 'bgColor' => 'E5E7EB'])
                ->addText($group['organization'], ['bold' => true]);

            $table->addRow(360, ['cantSplit' => true, 'tblHeader' => true]);
            foreach (['No.', 'Name', 'Phone number', 'Signature'] as $i => $heading) {
                $table->addCell($widths[$i])->addText($heading, ['bold' => true]);
            }

            foreach ($group['rows'] as $n => $row) {
                $table->addRow(560, ['cantSplit' => true]);
                $table->addCell($widths[0])->addText((string) ($n + 1));
                $table->addCell($widths[1])->addText($row['name']);
                $table->addCell($widths[2])->addText($row['phone']);
                $table->addCell($widths[3]);
            }
        }

        return response()->streamDownload(
            fn () => IOFactory::createWriter($word, 'Word2007')->save('php://output'),
            $this->filename('docx'),
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        );
    }

    public function download(string $format): StreamedResponse
    {
        return $format === 'docx' ? $this->word() : $this->pdf();
    }
}
