<?php

namespace App\Services\Approvals\Handlers;

use App\Enums\LeaveLedgerEntryType;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Services\Approvals\ChangeContext;
use App\Services\Leave\LeaveCreditPostingService;
use App\Services\Leave\LeaveDays;
use App\Services\Leave\LeaveLedger;
use App\Services\Leave\LeaveRequestService;
use App\Services\Leave\OpeningBalanceImporter;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Applies HR decisions on leave applications and changes to leave credits
 * and leave types.
 */
class LeaveChanges
{
    public function __construct(
        private LeaveRequestService $requests,
        private LeaveLedger $ledger,
        private LeaveCreditPostingService $posting,
        private OpeningBalanceImporter $openingBalances,
    ) {}

    public function approveLeaveRequest(ChangeContext $change): LeaveRequest
    {
        return $this->requests->approve($change->subject(LeaveRequest::class), $change->author, $change->input('remarks'));
    }

    public function rejectLeaveRequest(ChangeContext $change): LeaveRequest
    {
        return $this->requests->reject($change->subject(LeaveRequest::class), $change->author, (string) $change->input('remarks'));
    }

    public function cancelLeaveRequest(ChangeContext $change): LeaveRequest
    {
        return $this->requests->cancel($change->subject(LeaveRequest::class), $change->author, $change->input('remarks'));
    }

    public function createLeaveType(ChangeContext $change): LeaveType
    {
        return LeaveType::create([
            ...$change->payload,
            'sort_order' => (int) LeaveType::max('sort_order') + 10,
        ]);
    }

    public function updateLeaveType(ChangeContext $change): LeaveType
    {
        $type = $change->subject(LeaveType::class);
        $type->update($change->payload);

        return $type;
    }

    /**
     * A manual correction to the leave card, with the reason recorded.
     *
     * @return array{days: int, type: LeaveType}
     */
    public function adjustLeaveBalance(ChangeContext $change): array
    {
        $days = LeaveDays::toThousandths($change->input('days'));
        $ledgerType = $change->input('ledger') === LeaveType::SICK ? LeaveType::sick() : LeaveType::vacation();

        $this->ledger->post(
            $change->subject(Employee::class),
            $ledgerType,
            LeaveLedgerEntryType::Adjustment,
            $days,
            __('Adjustment'),
            CarbonImmutable::createFromFormat('!Y-m-d', $change->input('entry_date')),
            user: $change->author,
            remarks: $change->input('reason'),
        );

        return ['days' => $days, 'type' => $ledgerType];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function postLeaveCredits(ChangeContext $change): Collection
    {
        return $this->posting->postMonthForAll(
            CarbonImmutable::createFromFormat('!Y-m', $change->input('month'))->startOfMonth(),
            $change->author,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function importOpeningBalances(ChangeContext $change): array
    {
        return $this->openingBalances->import(
            $change->file('file')->getRealPath(),
            CarbonImmutable::createFromFormat('!Y-m-d', $change->input('as_of')),
            $change->author,
        );
    }
}
