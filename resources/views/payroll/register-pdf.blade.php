@use('App\Services\Payroll\Money')
@use('App\Services\Payroll\PayrollRegister')
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Payroll register — {{ $run->title() }}</title>
    <style>
        @page { margin: 1cm 1cm 1.4cm; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 6.5pt; color: #000; }
        .header { text-align: center; }
        .header p { margin: 0; }
        h1 { font-size: 10pt; margin: 6px 0 2px; text-align: center; }
        .subtitle { text-align: center; margin: 0 0 8px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 0.5px solid #555; padding: 2px 3px; }
        th { background: #eee; font-size: 6pt; }
        td.amount { text-align: right; font-family: "DejaVu Sans Mono", monospace; white-space: nowrap; }
        tr.total td { font-weight: bold; background: #f5f5f5; }
        .signatures { margin-top: 24px; width: 100%; }
        .signatures td { border: none; width: 33%; padding-top: 28px; text-align: center; }
        .signatures .line { border-top: 0.5px solid #000; margin: 0 16px; padding-top: 2px; }
        .footer { position: fixed; bottom: -0.9cm; left: 0; right: 0; font-size: 6pt; color: #555; }
    </style>
</head>
<body>
    <div class="header">
        <p>Republic of the Philippines · Province of Dinagat Islands</p>
        <p><strong>MUNICIPALITY OF CAGDIANAO</strong></p>
    </div>
    <h1>PAYROLL REGISTER</h1>
    <p class="subtitle">{{ $run->title() }} · {{ $run->status->label() }}</p>

    <table>
        <thead>
            <tr>
                <th>No.</th>
                <th>Employee</th>
                <th>Position / Office</th>
                <th>Rate</th>
                @foreach (PayrollRegister::MONEY_COLUMNS as $heading)
                    <th>{{ $heading }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($items as $index => $item)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $item->employee->sortable_name }}<br>{{ $item->employee->employee_number }}</td>
                    <td>{{ $item->position_title }}<br>{{ $item->department_name }}</td>
                    <td>
                        @if ($item->daily_rate !== null)
                            {{ Money::format($item->daily_rate) }}/day
                        @else
                            SG {{ $item->salary_grade }}-{{ $item->salary_step }}<br>{{ Money::format($item->monthly_rate) }}/mo
                        @endif
                    </td>
                    @foreach (array_keys(PayrollRegister::MONEY_COLUMNS) as $column)
                        <td class="amount">{{ Money::format($item->{$column}) }}</td>
                    @endforeach
                </tr>
            @endforeach
            <tr class="total">
                <td colspan="4">Total ({{ $items->count() }} employees)</td>
                @foreach ($totals as $total)
                    <td class="amount">{{ Money::format($total) }}</td>
                @endforeach
            </tr>
        </tbody>
    </table>

    <table class="signatures">
        <tr>
            <td><div class="line">Prepared by (HRMO)</div></td>
            <td><div class="line">Certified correct (Municipal Accountant)</div></td>
            <td><div class="line">Approved (Municipal Mayor)</div></td>
        </tr>
    </table>

    <div class="footer">Kagjanaw HRIS · Generated {{ now()->format('M j, Y g:i A') }}</div>
</body>
</html>
