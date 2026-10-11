{{--
    Civil Service Form No. 48 (Daily Time Record), two copies side by side
    on one landscape page. Rendered by DomPDF, so layout uses tables only.
    Expects: $employee, $month, $schedule, $days, $totals
--}}
@use('App\Models\DailyTimeRecord')
@php
    $daysByNumber = $days->keyBy(fn (array $day): int => (int) $day['date']->format('j'));
    $totalTardiness = $totals['late_minutes'] + $totals['undertime_minutes'];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>DTR {{ $employee->employee_number }} {{ $month->format('F Y') }}</title>
    <style>
        @page { margin: 14px 20px; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 7.5px; color: #000; margin: 0; }
        .copies { width: 100%; border-collapse: collapse; }
        .copies td.copy { width: 50%; vertical-align: top; padding: 0 12px; }
        .copies td.copy + td.copy { border-left: 1px dashed #999; }
        .form-no { font-size: 6.5px; font-style: italic; }
        .title { text-align: center; font-size: 11px; font-weight: bold; letter-spacing: 1px; }
        .center { text-align: center; line-height: 1; }
        .name { text-align: center; font-size: 9px; font-weight: bold; text-transform: uppercase; border-bottom: 1px solid #000; margin: 4px 20px 0; }
        .caption { text-align: center; font-size: 6px; }
        table.meta { width: 100%; border-collapse: collapse; margin-top: 3px; }
        table.meta td { padding: 0.5px 0; }
        .underline { border-bottom: 1px solid #000; font-weight: bold; padding: 0 6px; }
        table.dtr { width: 100%; border-collapse: collapse; margin-top: 3px; }
        table.dtr th, table.dtr td { border: 0.75px solid #000; text-align: center; padding: 0 2px; height: 10.5px; line-height: 10px; }
        table.dtr th { font-size: 6.5px; font-weight: bold; }
        table.dtr td.note { font-style: italic; font-size: 6.5px; }
        .certify { margin: 4px 0 0; text-indent: 16px; text-align: justify; font-size: 6.5px; line-height: 1.25; }
        .signature { margin: 12px 30px 0; border-top: 0.75px solid #000; text-align: center; font-size: 6.5px; }
        .verified { margin-top: 4px; font-size: 6.5px; }
        .footer { margin-top: 4px; font-size: 5.5px; color: #555; }
    </style>
</head>
<body>
<table class="copies">
    <tbody>
    <tr>
        @foreach ([1, 2] as $copy)
            <td class="copy">
                <div class="form-no">Civil Service Form No. 48</div>
                <div class="title">DAILY TIME RECORD</div>
                <div class="center">-----o0o-----</div>

                <div class="name">{{ $employee->full_name }}</div>
                <div class="caption">(Name)</div>

                <table class="meta">
                    <tr>
                        <td colspan="2">For the month of <span class="underline">{{ mb_strtoupper($month->format('F Y')) }}</span></td>
                    </tr>
                    <tr>
                        <td>Official hours for arrival and departure</td>
                        <td>Regular days: <span class="underline">{{ $schedule->sessionLabel($schedule->am_start, $schedule->am_end) }}, {{ $schedule->sessionLabel($schedule->pm_start, $schedule->pm_end) }}</span></td>
                    </tr>
                    <tr>
                        <td></td>
                        <td>Saturdays: <span class="underline">{{ $schedule->isWorkingDay(6) ? 'As above' : '' }}&nbsp;</span></td>
                    </tr>
                </table>

                <table class="dtr">
                    <thead>
                    <tr>
                        <th rowspan="2" style="width: 9%;">Day</th>
                        <th colspan="2">A.M.</th>
                        <th colspan="2">P.M.</th>
                        <th colspan="2">Undertime</th>
                    </tr>
                    <tr>
                        <th>Arrival</th>
                        <th>Departure</th>
                        <th>Arrival</th>
                        <th>Departure</th>
                        <th>Hours</th>
                        <th>Minutes</th>
                    </tr>
                    </thead>
                    <tbody>
                    @for ($dayNumber = 1; $dayNumber <= 31; $dayNumber++)
                        @php
                            $day = $daysByNumber->get($dayNumber);
                            $record = $day['record'] ?? null;
                            $hasPunches = $record && ($record->am_in || $record->am_out || $record->pm_in || $record->pm_out);
                            $tardiness = $record ? $record->late_minutes + $record->undertime_minutes : 0;
                            $note = match (true) {
                                $day === null || $hasPunches => null,
                                $day['holiday'] !== null => $day['holiday']->name,
                                $record?->status === \App\Enums\DtrStatus::Absent => 'Absent',
                                $day['date']->isSaturday() && ! $schedule->isWorkingDay(6) => 'Saturday',
                                $day['date']->isSunday() && ! $schedule->isWorkingDay(7) => 'Sunday',
                                default => null,
                            };
                        @endphp
                        <tr>
                            <td>{{ $dayNumber }}</td>
                            @if ($note)
                                <td colspan="4" class="note">{{ $note }}</td>
                            @else
                                <td>{{ DailyTimeRecord::displayTime($record?->am_in) }}</td>
                                <td>{{ DailyTimeRecord::displayTime($record?->am_out) }}</td>
                                <td>{{ DailyTimeRecord::displayTime($record?->pm_in) }}</td>
                                <td>{{ DailyTimeRecord::displayTime($record?->pm_out) }}</td>
                            @endif
                            <td>{{ $tardiness >= 60 ? intdiv($tardiness, 60) : '' }}</td>
                            <td>{{ $tardiness > 0 ? $tardiness % 60 : '' }}</td>
                        </tr>
                    @endfor
                    <tr>
                        <td colspan="5" style="text-align: right; font-weight: bold;">TOTAL</td>
                        <td style="font-weight: bold;">{{ $totalTardiness >= 60 ? intdiv($totalTardiness, 60) : '' }}</td>
                        <td style="font-weight: bold;">{{ $totalTardiness > 0 ? $totalTardiness % 60 : '' }}</td>
                    </tr>
                    </tbody>
                </table>

                <p class="certify">
                    I CERTIFY on my honor that the above is a true and correct report of the hours of work
                    performed, record of which was made daily at the time of arrival and departure from office.
                </p>
                <div class="signature">(Signature)</div>

                <div class="verified">VERIFIED as to the prescribed office hours:</div>
                <div class="signature">In Charge</div>

                <div class="footer">
                    {{ $employee->employee_number }} · Absences: {{ $totals['absent_days'] }} day(s) ·
                    Generated by Kagjanaw HRIS on {{ now()->format('M j, Y g:i A') }}
                </div>
            </td>
        @endforeach
    </tr>
    </tbody>
</table>
</body>
</html>
