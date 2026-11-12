<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 18mm 15mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10pt; color: #000; }
        h1 { font-size: 14pt; text-align: center; margin: 0 0 2mm; }
        .subtitle { text-align: center; color: #444; margin: 0 0 6mm; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 6mm; page-break-inside: avoid; }
        th, td { border: 1px solid #000; padding: 2mm 2.5mm; text-align: left; vertical-align: middle; }
        .org { background: #e5e7eb; font-weight: bold; font-size: 10.5pt; }
        thead th { font-weight: bold; }
        td { height: 9mm; }
        .no { width: 8%; text-align: center; }
        .name { width: 44%; }
        .phone { width: 22%; }
        .sign { width: 26%; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    @if ($subtitle)
        <p class="subtitle">{{ $subtitle }}</p>
    @endif

    @foreach ($groups as $group)
        <table>
            <thead>
                <tr><th colspan="4" class="org">{{ $group['organization'] }}</th></tr>
                <tr>
                    <th class="no">No.</th>
                    <th class="name">Name</th>
                    <th class="phone">Phone number</th>
                    <th class="sign">Signature</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($group['rows'] as $n => $row)
                    <tr>
                        <td class="no">{{ $n + 1 }}</td>
                        <td class="name">{{ $row['name'] }}</td>
                        <td class="phone">{{ $row['phone'] }}</td>
                        <td class="sign"></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endforeach
</body>
</html>
