<?php

namespace App\Http\Controllers\Leave;

use App\Enums\LeaveCreditSource;
use App\Enums\LeaveRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Leave\LeaveCertificateRequest;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Services\Leave\LeaveDays;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

/**
 * Certificate of Leave Credits issued by the HRMO, following the
 * "Certification of Leave Credits" block of CS Form No. 6 (total earned,
 * less applications pending, balance).
 */
class LeaveCertificateController extends Controller
{
    public function __invoke(LeaveCertificateRequest $request, Employee $employee): Response
    {
        $employee->load(['department', 'position']);
        $asOf = $request->asOf();

        $balanceAsOf = fn (LeaveType $type): int => LeaveDays::toThousandths(
            $employee->leaveLedgerEntries()
                ->where('leave_type_id', $type->id)
                ->whereDate('entry_date', '<=', $asOf->toDateString())
                ->orderByDesc('entry_date')
                ->orderByDesc('id')
                ->value('balance_after')
        );

        $pending = $employee->leaveRequests()
            ->whereIn('status', [LeaveRequestStatus::Pending, LeaveRequestStatus::Endorsed])
            ->with('leaveType')
            ->get();

        $pendingFor = fn (LeaveCreditSource $source): int => $pending
            ->filter(fn (LeaveRequest $leave): bool => $leave->leaveType->credit_source === $source)
            ->sum(fn (LeaveRequest $leave): int => LeaveDays::toThousandths($leave->days));

        $vacation = $balanceAsOf(LeaveType::vacation());
        $sick = $balanceAsOf(LeaveType::sick());
        $lessVacation = $pendingFor(LeaveCreditSource::VacationCredits);
        $lessSick = $pendingFor(LeaveCreditSource::SickCredits);

        $pdf = Pdf::loadView('leave.certificate-pdf', [
            'employee' => $employee,
            'asOf' => $asOf,
            'purpose' => $request->validated('purpose'),
            'rows' => [
                'vacation' => ['earned' => $vacation, 'less' => $lessVacation, 'balance' => $vacation - $lessVacation],
                'sick' => ['earned' => $sick, 'less' => $lessSick, 'balance' => $sick - $lessSick],
            ],
            'issuedBy' => $request->user(),
        ])
            ->setPaper('a4', 'portrait')
            ->setOption('isFontSubsettingEnabled', true);

        return $pdf->stream(sprintf('leave-credits-%s-%s.pdf', $employee->employee_number, $asOf->format('Y-m-d')));
    }
}
