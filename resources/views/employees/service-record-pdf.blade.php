@use('App\Services\Payroll\Money')
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Service Record — {{ $employee->full_name }}</title>
    <style>
        @page { margin: 1.4cm 1.5cm 1.8cm; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 9pt; color: #000; line-height: 1.4; }
        .center { text-align: center; }
        .header p { margin: 0; }
        .header .office { font-weight: bold; margin-top: 4px; }
        h1 { font-size: 13pt; letter-spacing: 3px; margin: 16px 0 12px; text-align: center; }
        table.identity { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        table.identity td { padding: 2px 4px; vertical-align: bottom; }
        table.identity .label { width: 9%; font-weight: bold; }
        table.identity .value { border-bottom: 1px solid #000; text-align: center; font-weight: bold; }
        table.identity .caption td { font-size: 7pt; text-align: center; color: #333; padding-top: 0; }
        .certification { text-align: justify; margin: 8px 0 10px; }
        table.service { width: 100%; border-collapse: collapse; }
        table.service th, table.service td { border: 1px solid #000; padding: 4px 5px; }
        table.service th { background: #f0f0f0; font-size: 7.5pt; text-align: center; }
        table.service td { font-size: 8pt; }
        table.service td.date { white-space: nowrap; text-align: center; }
        table.service td.number { text-align: right; font-family: "DejaVu Sans Mono", monospace; white-space: nowrap; }
        .note { margin-top: 10px; font-size: 8pt; }
        .signature { margin-top: 36px; width: 38%; margin-left: 62%; text-align: center; }
        .signature .label { text-align: left; margin-bottom: 30px; }
        .signature .line { border-top: 1px solid #000; padding-top: 2px; }
        .footer { position: fixed; bottom: -1.1cm; left: 0; right: 0; font-size: 7pt; color: #555; }
    </style>
</head>
<body>
    <div class="header center">
        <p>Republic of the Philippines</p>
        <p>Province of Dinagat Islands</p>
        <p><strong>MUNICIPALITY OF CAGDIANAO</strong></p>
        <p class="office">Office of the Municipal Human Resource Management Officer</p>
    </div>

    <h1>SERVICE RECORD</h1>

    <table class="identity">
        <tr>
            <td class="label">NAME:</td>
            <td class="value">{{ mb_strtoupper($employee->last_name) }}</td>
            <td class="value">{{ mb_strtoupper(trim($employee->first_name.' '.$employee->name_extension)) }}</td>
            <td class="value">{{ mb_strtoupper((string) $employee->middle_name) }}</td>
        </tr>
        <tr class="caption">
            <td></td>
            <td>(Surname)</td>
            <td>(Given Name)</td>
            <td>(Middle Name)</td>
        </tr>
        <tr>
            <td class="label">BIRTH:</td>
            <td class="value">{{ $employee->birth_date->format('F j, Y') }}</td>
            <td class="value" colspan="2">{{ $employee->birth_place }}</td>
        </tr>
        <tr class="caption">
            <td></td>
            <td>(Date)</td>
            <td colspan="2">(Place)</td>
        </tr>
    </table>

    <p class="certification">
        This is to certify that the employee named herein above actually rendered services in this Office as shown
        by the service record below, each line of which is supported by appointment and other papers actually issued
        by this Office and approved by the authorities concerned.
    </p>

    <table class="service">
        <thead>
            <tr>
                <th colspan="2">SERVICE<br>(Inclusive Dates)</th>
                <th colspan="3">RECORD OF APPOINTMENT</th>
                <th colspan="2">OFFICE ENTITY / DIVISION</th>
                <th rowspan="2">Leave of<br>Absence<br>w/o Pay</th>
                <th colspan="2">SEPARATION</th>
                <th rowspan="2">Remarks</th>
            </tr>
            <tr>
                <th>From</th>
                <th>To</th>
                <th>Designation</th>
                <th>Status</th>
                <th>Salary<br>(Annual)</th>
                <th>Station / Place<br>of Assignment</th>
                <th>Branch</th>
                <th>Date</th>
                <th>Cause</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($entries as $entry)
                <tr>
                    <td class="date">{{ $entry->service_from->format('m/d/Y') }}</td>
                    <td class="date">{{ $entry->service_to?->format('m/d/Y') ?? 'Present' }}</td>
                    <td>{{ $entry->designation }}</td>
                    <td>{{ $entry->status }}</td>
                    <td class="number">{{ $entry->annual_salary !== null ? Money::format($entry->annual_salary) : '' }}</td>
                    <td>{{ $entry->station }}</td>
                    <td>{{ $entry->branch }}</td>
                    <td>{{ $entry->leave_without_pay }}</td>
                    <td class="date">{{ $entry->separation_date?->format('m/d/Y') }}</td>
                    <td>{{ $entry->separation_cause }}</td>
                    <td>{{ $entry->remarks }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="11" class="center">No service record entries.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <p class="note">
        Issued in compliance with Executive Order No. 54 dated August 10, 1954, and in accordance with Circular No. 58
        dated August 10, 1954 of the System. Issued this {{ now()->format('jS') }} day of {{ now()->format('F Y') }}
        at Cagdianao, Province of Dinagat Islands.
    </p>

    <div class="signature">
        <div class="label">CERTIFIED CORRECT:</div>
        <div class="line">Municipal Human Resource Management Officer</div>
    </div>

    <div class="footer">
        Employee ID {{ $employee->employee_number }} · Generated by Cagdianao HRIS on {{ now()->format('M j, Y g:i A') }} by {{ $issuedBy->name }}.
        Not valid without the signature of the HRMO.
    </div>
</body>
</html>
