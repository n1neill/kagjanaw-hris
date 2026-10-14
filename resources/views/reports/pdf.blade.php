@use('App\Services\Payroll\Money')
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $report->title }}</title>
    <style>
        @page { margin: 1cm 1cm 1.4cm; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 7pt; color: #000; }
        .header { text-align: center; }
        .header p { margin: 0; }
        h1 { font-size: 10pt; margin: 6px 0 2px; text-align: center; text-transform: uppercase; }
        h2 { font-size: 8pt; margin: 12px 0 4px; }
        .subtitle { text-align: center; margin: 0 0 8px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 0.5px solid #555; padding: 2px 3px; vertical-align: top; }
        th { background: #eee; font-size: 6.5pt; }
        td.number { text-align: right; font-family: "DejaVu Sans Mono", monospace; white-space: nowrap; }
        tr.total td { font-weight: bold; background: #f5f5f5; }
        .footer { position: fixed; bottom: -0.9cm; left: 0; right: 0; font-size: 6pt; color: #555; }
    </style>
</head>
<body>
    <div class="header">
        <p>Republic of the Philippines · Province of Dinagat Islands</p>
        <p><strong>MUNICIPALITY OF CAGDIANAO</strong></p>
    </div>
    <h1>{{ $report->title }}</h1>
    <p class="subtitle">{{ $report->subtitle }}</p>

    @foreach ($report->sections as $section)
        @if ($section->heading)
            <h2>{{ $section->heading }}</h2>
        @endif
        <table>
            <thead>
                <tr>
                    @foreach ($section->columns as $column)
                        <th>{{ $column }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($section->rows as $row)
                    <tr>
                        @foreach ($row as $index => $value)
                            <td @class(['number' => $section->isNumeric($index)])>{{ $section->isMoney($index) && is_numeric($value) ? Money::format($value) : $value }}</td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="{{ count($section->columns) }}">No records.</td></tr>
                @endforelse
                @if ($section->totals !== null)
                    <tr class="total">
                        @foreach ($section->totals as $index => $value)
                            <td @class(['number' => $section->isNumeric($index)])>{{ $section->isMoney($index) && is_numeric($value) ? Money::format($value) : $value }}</td>
                        @endforeach
                    </tr>
                @endif
            </tbody>
        </table>
    @endforeach

    <div class="footer">Generated {{ now()->format('F j, Y g:i A') }} · Kagjanaw HRIS</div>
</body>
</html>
