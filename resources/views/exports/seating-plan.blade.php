@php
    use App\Services\Seating\SeatingLayout;
    $people = $state['people'];
    $s = min(1, 1030 / max(1, $layout['width']), 600 / max(1, $layout['height']));
    $px = fn ($v) => round($v * $s, 1).'px';
    $seatsSorted = $layout['seats'];
    usort($seatsSorted, fn ($a, $b) => $a['seq'] <=> $b['seq']);
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 12mm; }
        body { font-family: DejaVu Sans, sans-serif; color: #000; font-size: 9pt; }
        h1 { font-size: 13pt; text-align: center; margin: 0 0 1mm; }
        .sub { text-align: center; color: #444; margin: 0 0 4mm; font-size: 8.5pt; }
        .canvas { position: relative; margin: 0 auto; }
        .rect { position: absolute; background: #e5e7eb; }
        .circle { position: absolute; border-radius: 50%; background: #e5e7eb; text-align: center; color: #555; font-weight: bold; }
        .label { position: absolute; color: #666; text-transform: uppercase; letter-spacing: 1px; }
        .seat { position: absolute; box-sizing: border-box; border: 1px solid #555; background: #fff; overflow: hidden; }
        .seat.chair { border: 2px solid #b45309; }
        .seat.host { border: 2px solid #1d4ed8; }
        .seat .no { position: absolute; left: 2px; top: 1px; font-weight: bold; color: #555; }
        .seat .nm { font-weight: bold; }
        .seat .t { color: #444; }
        table.list { width: 100%; border-collapse: collapse; margin-top: 3mm; }
        table.list th, table.list td { border: 1px solid #000; padding: 1.5mm 2mm; text-align: left; vertical-align: top; }
        table.list th { background: #e5e7eb; }
        .page-break { page-break-before: always; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <p class="sub">Susunan tempat duduk · {{ SeatingLayout::LABELS[$state['layout']] ?? '' }} · {{ now()->format('j M Y, g:i A') }}</p>

    <div class="canvas" style="width: {{ $px($layout['width']) }}; height: {{ $px($layout['height']) }};">
        @foreach ($layout['shapes'] as $shape)
            @if ($shape['type'] === 'rect')
                <div class="rect" style="left:{{ $px($shape['x']) }}; top:{{ $px($shape['y']) }}; width:{{ $px($shape['w']) }}; height:{{ $px($shape['h']) }};"></div>
            @elseif ($shape['type'] === 'circle')
                <div class="circle" style="left:{{ $px($shape['cx'] - $shape['r']) }}; top:{{ $px($shape['cy'] - $shape['r']) }}; width:{{ $px(2 * $shape['r']) }}; height:{{ $px(2 * $shape['r']) }}; line-height:{{ $px(2 * $shape['r']) }}; font-size:{{ max(6, round(9 * $s, 1)) }}pt;">{{ $shape['text'] }}</div>
            @else
                <div class="label" style="left:{{ $px($shape['x'] - 80) }}; width:{{ $px(160) }}; text-align:center; top:{{ $px($shape['y']) }}; font-size:{{ max(6, round(8 * $s, 1)) }}pt;">{{ $shape['text'] }}</div>
            @endif
        @endforeach

        @foreach ($layout['seats'] as $seat)
            @php
                $key = $state['seats'][(string) $seat['seq']] ?? null;
                $p = $key ? $people[$key] : null;
                $cls = $key && $key === $state['chair'] ? 'chair' : ($key && $key === $state['host'] ? 'host' : '');
                $border = $cls ? 2 : 1;
                $w = ($seat['w'] ?? SeatingLayout::SEAT_W) - 24 - 2 * $border;
                $h = ($seat['h'] ?? SeatingLayout::SEAT_H) - 3 - 2 * $border;
            @endphp
            <div class="seat {{ $cls }}" style="left:{{ $px($seat['x']) }}; top:{{ $px($seat['y']) }}; width:{{ $px($w) }}; height:{{ $px($h) }}; font-size:{{ max(4.5, round(7 * $s, 1)) }}pt; padding:{{ $px(3) }} {{ $px(4) }} 0 {{ $px(20) }};">
                <span class="no">{{ $seat['seq'] }}</span>
                @if ($p)
                    <div class="nm">{{ $p['name'] }}</div>
                    <div class="t">{{ $p['note'] ?: ($p['title'] ?: $p['org']) }}</div>
                @endif
            </div>
        @endforeach
    </div>

    <div class="page-break"></div>
    <h1>{{ $title }}</h1>
    <p class="sub">Senarai tempat duduk mengikut nombor kerusi</p>
    <table class="list">
        <thead>
            <tr><th style="width:8%">Kerusi</th><th style="width:8%">Meja</th><th>Nama</th><th>Jawatan</th><th>Kementerian / Organisasi</th></tr>
        </thead>
        <tbody>
            @foreach ($seatsSorted as $seat)
                @php $key = $state['seats'][(string) $seat['seq']] ?? null; $p = $key ? $people[$key] : null; @endphp
                <tr>
                    <td>{{ $seat['seq'] }}</td>
                    <td>{{ $seat['table'] === null ? '—' : ($seat['table'] === 0 ? 'Utama' : $seat['table']) }}</td>
                    <td>
                        {{ $p['name'] ?? '' }}
                        @if ($key && $key === $state['chair']) (Pengerusi) @endif
                        @if ($key && $key === $state['host']) (Tuan rumah) @endif
                    </td>
                    <td>{{ $p ? ($p['note'] ?: $p['title']) : '' }}</td>
                    <td>{{ $p['org'] ?? '' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
