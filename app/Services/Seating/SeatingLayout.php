<?php

namespace App\Services\Seating;

class SeatingLayout
{
    public const LINE = 'line';

    public const U_SHAPE = 'u';

    public const ROUND = 'round';

    public const LABELS = [
        self::LINE => 'Straight line (sebaris)',
        self::U_SHAPE => 'U-shape',
        self::ROUND => 'Round tables (meja bulat)',
    ];

    public const SEAT_W = 132;

    public const SEAT_H = 50;

    private const GAP = 10;

    public const DEFAULT_CONFIG = [
        'rows' => 1,
        'per_row' => 7,
        'head' => 3,
        'arm' => 4,
        'tables' => 1,
        'per_table' => 10,
    ];

    /** Seat numbers from the audience's left to right for one row (INTAN: 4 2 1 3 5 / 3 1 2 4). */
    public static function lineOrder(int $count): array
    {
        $left = [];
        $right = [];

        if ($count % 2 === 1) {
            for ($n = $count - 1; $n >= 2; $n -= 2) {
                $left[] = $n;
            }
            $left[] = 1;
            for ($n = 3; $n <= $count; $n += 2) {
                $right[] = $n;
            }
        } else {
            for ($n = $count - 1; $n >= 1; $n -= 2) {
                $left[] = $n;
            }
            for ($n = 2; $n <= $count; $n += 2) {
                $right[] = $n;
            }
        }

        return [...$left, ...$right];
    }

    public static function seatCount(string $layout, array $config): int
    {
        $c = array_merge(self::DEFAULT_CONFIG, $config);

        return match ($layout) {
            self::LINE => $c['rows'] * $c['per_row'],
            self::U_SHAPE => $c['head'] + 2 * $c['arm'],
            self::ROUND => $c['tables'] * $c['per_table'],
            default => 0,
        };
    }

    public static function fitConfig(string $layout, int $people, array $config = []): array
    {
        $c = array_merge(self::DEFAULT_CONFIG, $config);
        $people = max(1, $people);

        switch ($layout) {
            case self::LINE:
                $c['per_row'] = min(15, max(1, $people));
                $c['rows'] = (int) ceil($people / $c['per_row']);
                break;
            case self::U_SHAPE:
                $c['head'] = $people >= 15 ? 5 : min(3, $people);
                $c['arm'] = (int) max(0, ceil(($people - $c['head']) / 2));
                break;
            case self::ROUND:
                $c['per_table'] = max(2, (int) $c['per_table']);
                $c['tables'] = (int) ceil($people / $c['per_table']);
                break;
        }

        return self::clamp($c);
    }

    public static function clamp(array $config): array
    {
        $c = array_merge(self::DEFAULT_CONFIG, $config);

        return [
            'rows' => min(20, max(1, (int) $c['rows'])),
            'per_row' => min(30, max(1, (int) $c['per_row'])),
            'head' => min(15, max(1, (int) $c['head'])),
            'arm' => min(30, max(0, (int) $c['arm'])),
            'tables' => min(40, max(1, (int) $c['tables'])),
            'per_table' => min(16, max(2, (int) $c['per_table'])),
        ];
    }

    public static function build(string $layout, array $config): array
    {
        $c = self::clamp($config);

        return match ($layout) {
            self::LINE => self::line($c),
            self::U_SHAPE => self::uShape($c),
            self::ROUND => self::round($c),
            default => ['width' => 600, 'height' => 200, 'seats' => [], 'shapes' => []],
        };
    }

    private static function line(array $c): array
    {
        $step = self::SEAT_W + self::GAP;
        $rowWidth = $c['per_row'] * $step - self::GAP;
        $width = max(600, $rowWidth + 40);
        $startX = (int) (($width - $rowWidth) / 2);
        $order = self::lineOrder($c['per_row']);
        $seats = [];

        for ($r = 0; $r < $c['rows']; $r++) {
            $y = 60 + $r * (self::SEAT_H + 34);

            foreach ($order as $i => $local) {
                $seats[] = ['seq' => $r * $c['per_row'] + $local, 'x' => $startX + $i * $step, 'y' => $y, 'table' => null];
            }
        }

        $shapes = [
            ['type' => 'label', 'x' => $width / 2, 'y' => 24, 'text' => 'Hadapan / Front'],
            ['type' => 'rect', 'x' => $startX - 10, 'y' => 60 + self::SEAT_H + 6, 'w' => $rowWidth + 20, 'h' => 14],
        ];

        return ['width' => $width, 'height' => 60 + $c['rows'] * (self::SEAT_H + 34) + 10, 'seats' => $seats, 'shapes' => $shapes];
    }

    private static function uShape(array $c): array
    {
        $step = self::SEAT_W + self::GAP;
        $headWidth = $c['head'] * $step - self::GAP;
        $side = self::SEAT_W + 60;
        $width = max(600, $headWidth + 2 * $side + 40);
        $headX = (int) (($width - $headWidth) / 2);
        $seats = [];

        foreach (self::lineOrder($c['head']) as $i => $local) {
            $seats[] = ['seq' => $local, 'x' => $headX + $i * $step, 'y' => 60, 'table' => null];
        }

        $leftX = $headX - $side;
        $rightX = $headX + $headWidth + 60;
        $armTop = 60 + self::SEAT_H + 40;

        for ($k = 0; $k < $c['arm']; $k++) {
            $y = $armTop + $k * (self::SEAT_H + self::GAP);
            $seats[] = ['seq' => $c['head'] + 2 * $k + 1, 'x' => $leftX, 'y' => $y, 'table' => null];
            $seats[] = ['seq' => $c['head'] + 2 * $k + 2, 'x' => $rightX, 'y' => $y, 'table' => null];
        }

        $armBottom = $armTop + max(1, $c['arm']) * (self::SEAT_H + self::GAP);
        $tableTop = 60 + self::SEAT_H + 8;
        $shapes = [
            ['type' => 'label', 'x' => $width / 2, 'y' => 24, 'text' => 'Meja utama / Head table'],
            ['type' => 'rect', 'x' => $leftX + self::SEAT_W + 8, 'y' => $tableTop, 'w' => $rightX - $leftX - self::SEAT_W - 16, 'h' => 16],
            ['type' => 'rect', 'x' => $leftX + self::SEAT_W + 8, 'y' => $tableTop, 'w' => 16, 'h' => $armBottom - $tableTop],
            ['type' => 'rect', 'x' => $rightX - 24, 'y' => $tableTop, 'w' => 16, 'h' => $armBottom - $tableTop],
        ];

        return ['width' => $width, 'height' => $armBottom + 20, 'seats' => $seats, 'shapes' => $shapes];
    }

    private static function round(array $c): array
    {
        $sw = 112;
        $sh = 46;
        $perRow = $c['tables'] === 1 ? 1 : 3;
        $radius = (int) ceil(max(130, ($sw + 12) / (2 * sin(M_PI / $c['per_table']))));
        $tableRadius = max(40, $radius - 72);
        $cellW = 2 * $radius + $sw + 30;
        $cellH = 2 * $radius + $sh + 44;

        $others = $c['tables'] - 1;
        $rows = 1 + (int) ceil($others / $perRow);
        $width = max(600, ($c['tables'] === 1 ? 1 : min($perRow, $others)) * $cellW);
        $seats = [];
        $shapes = [];

        $centres = [[$width / 2, $cellH / 2]];
        $tableNo = 1;

        for ($row = 1; $row < $rows; $row++) {
            $inRow = min($perRow, $c['tables'] - $tableNo);
            $rowStart = ($width - $inRow * $cellW) / 2;

            foreach (self::lineOrder($inRow) as $pos => $local) {
                $centres[$tableNo + $local - 1] = [$rowStart + $pos * $cellW + $cellW / 2, $row * $cellH + $cellH / 2];
            }

            $tableNo += $inRow;
        }

        ksort($centres);

        foreach ($centres as $t => [$cx, $cy]) {
            $flip = $t === 0 ? -1 : 1;
            $shapes[] = ['type' => 'circle', 'cx' => $cx, 'cy' => $cy, 'r' => $tableRadius, 'text' => $t === 0 ? 'Meja Utama' : 'Meja '.$t];

            for ($local = 1; $local <= $c['per_table']; $local++) {
                if ($c['per_table'] % 2 === 1 && $local === $c['per_table']) {
                    $angle = M_PI;
                    $side = 0;
                } else {
                    $pair = intdiv($local - 1, 2);
                    $angle = ($pair + 0.5) * 2 * M_PI / $c['per_table'];
                    $side = $local % 2 === 1 ? -1 : 1;
                }

                $x = $cx + $side * $radius * sin($angle);
                $y = $cy + $flip * $radius * cos($angle);

                $seats[] = [
                    'seq' => $t * $c['per_table'] + $local,
                    'x' => (int) round($x - $sw / 2),
                    'y' => (int) round($y - $sh / 2),
                    'table' => $t,
                    'w' => $sw,
                    'h' => $sh,
                ];
            }
        }

        return ['width' => (int) $width, 'height' => $rows * $cellH, 'seats' => $seats, 'shapes' => $shapes];
    }
}
