@use('App\Services\Payroll\Money')
@php
    $run = $item->run;
    $employee = $item->employee;
    $deductions = collect([
        ['label' => 'GSIS', 'amount' => $item->gsis],
        ['label' => 'PhilHealth', 'amount' => $item->philhealth],
        ['label' => 'Pag-IBIG', 'amount' => $item->pagibig],
        ['label' => 'Withholding tax', 'amount' => $item->withholding_tax],
    ])->filter(fn ($line) => Money::toCentavos($line['amount']) !== 0)
        ->concat($item->deductionLines->map(fn ($line) => ['label' => $line->label, 'amount' => $line->amount]));
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Payslip — {{ $employee->full_name }} — {{ $run->period->label() }}</title>
    <style>
        @page { margin: 1.2cm 1.2cm; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 8.5pt; color: #000; line-height: 1.35; }
        .center { text-align: center; }
        .header p { margin: 0; }
        h1 { font-size: 11pt; letter-spacing: 2px; margin: 10px 0 2px; text-align: center; }
        .period { text-align: center; margin: 0 0 10px; }
        table { width: 100%; border-collapse: collapse; }
        table.info td { padding: 1px 0; vertical-align: top; }
        table.info td.label { width: 28%; color: #333; }
        table.lines { margin-top: 8px; }
        table.lines th { text-align: left; border-bottom: 1px solid #000; padding: 3px 0; font-size: 8pt; }
        table.lines td { padding: 2px 0; }
        table.lines td.amount, table.lines th.amount { text-align: right; font-family: "DejaVu Sans Mono", monospace; }
        table.lines tr.total td { border-top: 1px solid #000; font-weight: bold; }
        .net { margin-top: 10px; border: 1.5px solid #000; padding: 6px 8px; font-size: 10.5pt; font-weight: bold; }
        .net span { float: right; font-family: "DejaVu Sans Mono", monospace; }
        .notes { margin-top: 8px; font-size: 7.5pt; color: #333; }
        .footer { position: fixed; bottom: -0.6cm; left: 0; right: 0; font-size: 6.5pt; color: #555; }
    </style>
</head>
<body>
    <div class="header center">
        <p>Republic of the Philippines</p>
        <p>Province of Dinagat Islands</p>
        <p><strong>MUNICIPALITY OF CAGDIANAO</strong></p>
    </div>

    <h1>PAYSLIP</h1>
    <p class="period">{{ $run->pay_basis->payrollLabel() }} · {{ $run->period->label() }}</p>

    <table class="info">
        <tr><td class="label">Name</td><td><strong>{{ mb_strtoupper($employee->full_name) }}</strong></td></tr>
        <tr><td class="label">Employee No.</td><td>{{ $employee->employee_number }}</td></tr>
        <tr><td class="label">Position</td><td>{{ $item->position_title }}</td></tr>
        <tr><td class="label">Office</td><td>{{ $item->department_name }}</td></tr>
        <tr>
            <td class="label">Rate</td>
            <td>
                @if ($item->daily_rate !== null)
                    {{ Money::format($item->daily_rate) }} per day
                @else
                    SG {{ $item->salary_grade }}, Step {{ $item->salary_step }} · {{ Money::format($item->monthly_rate) }} per month
                @endif
            </td>
        </tr>
    </table>

    <table class="lines">
        <thead>
            <tr><th>Earnings</th><th class="amount">Amount</th></tr>
        </thead>
        <tbody>
            @foreach ($item->earnings as $line)
                <tr><td>{{ $line['label'] }}</td><td class="amount">{{ Money::format($line['amount']) }}</td></tr>
            @endforeach
            <tr class="total"><td>Gross pay</td><td class="amount">{{ Money::format($item->gross_pay) }}</td></tr>
        </tbody>
    </table>

    <table class="lines">
        <thead>
            <tr><th>Deductions</th><th class="amount">Amount</th></tr>
        </thead>
        <tbody>
            @forelse ($deductions as $line)
                <tr><td>{{ $line['label'] }}</td><td class="amount">{{ Money::format($line['amount']) }}</td></tr>
            @empty
                <tr><td colspan="2">None</td></tr>
            @endforelse
            <tr class="total"><td>Total deductions</td><td class="amount">{{ Money::format($item->total_deductions) }}</td></tr>
        </tbody>
    </table>

    <div class="net">NET PAY <span>PHP {{ Money::format($item->net_pay) }}</span></div>

    @if ($item->notes)
        <div class="notes">
            @foreach ($item->notes as $note)
                <p>{{ $note }}</p>
            @endforeach
        </div>
    @endif

    <div class="footer">
        Computer-generated payslip from Kagjanaw HRIS · {{ $run->released_at ? 'Released '.$run->released_at->format('M j, Y') : 'Not yet released — for review only' }} · Generated {{ now()->format('M j, Y g:i A') }}
    </div>
</body>
</html>
