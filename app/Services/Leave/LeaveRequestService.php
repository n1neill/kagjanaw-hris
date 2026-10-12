<?php

namespace App\Services\Leave;

use App\Enums\LeaveCreditSource;
use App\Enums\LeaveEndorsementRoute;
use App\Enums\LeaveLedgerEntryType;
use App\Enums\LeavePortion;
use App\Enums\LeaveRequestEvent;
use App\Enums\LeaveRequestStatus;
use App\Models\Employee;
use App\Models\LeaveLedgerEntry;
use App\Models\LeaveRequest;
use App\Models\LeaveRequestDay;
use App\Models\LeaveType;
use App\Models\User;
use App\Notifications\LeaveRequestNotification;
use App\Services\Attendance\AttendanceProcessor;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * The leave workflow: the employee files, the department head (or the
 * Municipal Mayor) endorses or rejects, and HR approves or rejects. Credits
 * are charged only on final approval, inside a transaction that locks the
 * balances, and the affected Daily Time Records are recomputed. The employee
 * is notified at every step.
 */
class LeaveRequestService
{
    public function __construct(
        private LeaveApplicationValidator $validator,
        private LeaveEndorsementRouter $router,
        private LeaveChargeCalculator $calculator,
        private LeaveLedger $ledger,
        private LeaveBalanceService $balances,
        private AttendanceProcessor $attendance,
    ) {}

    /**
     * @throws ValidationException when the application breaks a leave rule.
     */
    public function file(
        Employee $employee,
        LeaveType $type,
        CarbonImmutable $from,
        CarbonImmutable $to,
        LeavePortion $portion,
        string $details,
        ?UploadedFile $attachment,
        User $filedBy,
    ): LeaveRequest {
        $attachmentPath = $attachment?->store("leave-requests/{$employee->id}", LeaveRequest::ATTACHMENT_DISK);

        try {
            $request = DB::transaction(function () use ($employee, $type, $from, $to, $portion, $details, $attachment, $attachmentPath, $filedBy): LeaveRequest {
                // Serialize applications per employee so two cannot claim the same dates or allotment.
                Employee::query()->whereKey($employee->id)->lockForUpdate()->first();

                $check = $this->validator->check($employee, $type, $from, $to, $portion, $attachment !== null);

                if ($check['errors'] !== []) {
                    throw ValidationException::withMessages($check['errors']);
                }

                $route = $this->router->routeFor($employee);

                $request = new LeaveRequest([
                    'employee_id' => $employee->id,
                    'leave_type_id' => $type->id,
                    'date_from' => $from->toDateString(),
                    'date_to' => $to->toDateString(),
                    'portion' => $from->isSameDay($to) ? $portion : LeavePortion::FullDay,
                    'days' => LeaveDays::toDecimal($check['total']),
                    'details' => $details,
                ]);

                $request->forceFill([
                    'status' => $route === LeaveEndorsementRoute::NotRequired ? LeaveRequestStatus::Endorsed : LeaveRequestStatus::Pending,
                    'endorsement_route' => $route,
                    'filed_by' => $filedBy->id,
                    'attachment_path' => $attachmentPath,
                    'attachment_name' => $attachment?->getClientOriginalName(),
                    'attachment_mime' => $attachment?->getMimeType(),
                    'attachment_size' => $attachment?->getSize(),
                ])->save();

                $request->leaveDays()->createMany(array_map(fn (array $day): array => [
                    'employee_id' => $employee->id,
                    'date' => $day['date']->toDateString(),
                    'portion' => $day['portion'],
                    'days' => LeaveDays::toDecimal($day['thousandths']),
                ], $check['days']));

                return $request;
            });
        } catch (Throwable $exception) {
            if ($attachmentPath !== null) {
                Storage::disk(LeaveRequest::ATTACHMENT_DISK)->delete($attachmentPath);
            }

            throw $exception;
        }

        $request->load(['employee.user', 'leaveType']);

        $this->notify(
            $request->status === LeaveRequestStatus::Endorsed ? $this->router->hrOfficers() : $this->router->endorsersFor($request),
            $request,
            LeaveRequestEvent::Filed,
        );

        return $request;
    }

    public function endorse(LeaveRequest $request, User $endorser, ?string $remarks): LeaveRequest
    {
        $request = $this->transition($request, LeaveRequestStatus::Pending, function (LeaveRequest $request) use ($endorser, $remarks): void {
            $request->forceFill([
                'status' => LeaveRequestStatus::Endorsed,
                'endorsed_by' => $endorser->id,
                'endorsed_at' => now(),
                'endorsement_remarks' => $remarks,
            ])->save();
        });

        $this->notifyEmployee($request, LeaveRequestEvent::Endorsed);
        $this->notify($this->router->hrOfficers(), $request, LeaveRequestEvent::Endorsed);

        return $request;
    }

    /**
     * Reject at the endorsement stage (department head or Mayor) or at HR.
     * Only the stage the application was at when the user was authorized is
     * accepted, so an endorser cannot reject one that was endorsed meanwhile.
     */
    public function reject(LeaveRequest $request, User $user, string $remarks): LeaveRequest
    {
        if (! in_array($request->status, [LeaveRequestStatus::Pending, LeaveRequestStatus::Endorsed], true)) {
            throw $this->alreadyActedOn($request);
        }

        $request = $this->transition($request, $request->status, function (LeaveRequest $request) use ($user, $remarks): void {
            $request->forceFill($request->status === LeaveRequestStatus::Pending
                ? ['endorsed_by' => $user->id, 'endorsed_at' => now(), 'endorsement_remarks' => $remarks]
                : ['decided_by' => $user->id, 'decided_at' => now(), 'decision_remarks' => $remarks]);
            $request->forceFill(['status' => LeaveRequestStatus::Rejected])->save();
        });

        $this->notifyEmployee($request, LeaveRequestEvent::Rejected);

        return $request;
    }

    /**
     * Final approval by HR: charge the credits, split the days into with and
     * without pay, and excuse the days on the DTR.
     *
     * @throws ValidationException when a yearly allotment would be exceeded.
     */
    public function approve(LeaveRequest $request, User $hrOfficer, ?string $remarks): LeaveRequest
    {
        $request = $this->transition($request, LeaveRequestStatus::Endorsed, function (LeaveRequest $request) use ($hrOfficer, $remarks): void {
            $type = $request->leaveType;
            $employee = $request->employee;
            $days = LeaveDays::toThousandths($request->days);

            if ($type->annual_days !== null) {
                $used = $this->balances->usedInYear($employee, $type, $request->date_from->year, except: $request);

                if ($used + $days > LeaveDays::toThousandths($type->annual_days)) {
                    throw ValidationException::withMessages(['decision' => __('Approving this would exceed the :year allotment of :type.', [
                        'year' => $request->date_from->year,
                        'type' => $type->name,
                    ])]);
                }
            }

            $vacationType = LeaveType::vacation();
            $sickType = LeaveType::sick();

            $charge = $this->calculator->charge(
                $type->credit_source,
                $days,
                $this->ledger->lockedBalance($employee, $vacationType),
                $this->ledger->lockedBalance($employee, $sickType),
            );

            $particulars = $type->name.', '.$request->periodLabel();
            $post = function (LeaveType $ledgerType, int $used, int $withoutPay, string $particulars) use ($employee, $request, $hrOfficer): void {
                if ($used === 0 && $withoutPay === 0) {
                    return;
                }

                $this->ledger->post(
                    $employee, $ledgerType, LeaveLedgerEntryType::Availment, -$used, $particulars, $request->date_from,
                    withoutPay: $withoutPay, leaveRequest: $request, user: $hrOfficer,
                );
            };

            if ($type->credit_source === LeaveCreditSource::SickCredits) {
                $post($sickType, $charge->fromSick, $charge->withoutPay, $particulars);

                if ($charge->fromVacation > 0) {
                    $post($vacationType, $charge->fromVacation, 0, $particulars.' (sick leave charged to vacation leave)');
                }
            } elseif ($type->credit_source === LeaveCreditSource::VacationCredits) {
                $post($vacationType, $charge->fromVacation, $charge->withoutPay, $particulars);
            }

            $this->allocatePay($request, $charge->withPay());

            $request->forceFill([
                'status' => LeaveRequestStatus::Approved,
                'days_with_pay' => LeaveDays::toDecimal($charge->withPay()),
                'days_without_pay' => LeaveDays::toDecimal($charge->withoutPay),
                'decided_by' => $hrOfficer->id,
                'decided_at' => now(),
                'decision_remarks' => $remarks,
            ])->save();
        });

        $this->refreshAttendance($request);
        $this->notifyEmployee($request, LeaveRequestEvent::Approved);

        return $request;
    }

    /**
     * The employee withdraws an application still waiting for a decision, or
     * HR cancels an approved leave (e.g. the employee was recalled to work),
     * which restores the credits used.
     */
    public function cancel(LeaveRequest $request, User $user, ?string $remarks): LeaveRequest
    {
        $wasApproved = $request->status === LeaveRequestStatus::Approved;

        $request = $this->transition($request, $request->status, function (LeaveRequest $request) use ($user, $remarks): void {
            if ($request->status === LeaveRequestStatus::Approved) {
                $request->ledgerEntries()
                    ->where('entry_type', LeaveLedgerEntryType::Availment)
                    ->with('leaveType')
                    ->get()
                    ->each(fn (LeaveLedgerEntry $entry) => $this->ledger->post(
                        $request->employee,
                        $entry->leaveType,
                        LeaveLedgerEntryType::Reversal,
                        -LeaveDays::toThousandths($entry->days),
                        $entry->particulars.' — cancelled',
                        CarbonImmutable::today(),
                        withoutPay: -LeaveDays::toThousandths($entry->days_without_pay),
                        leaveRequest: $request,
                        user: $user,
                        remarks: $remarks,
                    ));

                $request->leaveDays()->update(['days_with_pay' => 0]);
            }

            $request->forceFill([
                'status' => LeaveRequestStatus::Cancelled,
                'cancelled_by' => $user->id,
                'cancelled_at' => now(),
                'cancellation_remarks' => $remarks,
            ])->save();
        }, allowed: [LeaveRequestStatus::Pending, LeaveRequestStatus::Endorsed, LeaveRequestStatus::Approved]);

        if ($wasApproved) {
            $this->refreshAttendance($request);
        }

        if (! $request->isFiledBy($user)) {
            $this->notifyEmployee($request, LeaveRequestEvent::Cancelled);
        }

        return $request;
    }

    /**
     * Run a state change on a freshly locked copy of the request, refusing it
     * if someone else acted first.
     *
     * @param  callable(LeaveRequest): void  $change
     * @param  list<LeaveRequestStatus>|null  $allowed
     */
    private function transition(LeaveRequest $request, LeaveRequestStatus $expected, callable $change, ?array $allowed = null): LeaveRequest
    {
        $allowed ??= [$expected];

        return DB::transaction(function () use ($request, $change, $allowed): LeaveRequest {
            $locked = LeaveRequest::query()->whereKey($request->id)->lockForUpdate()->sole();

            if (! in_array($locked->status, $allowed, true)) {
                throw $this->alreadyActedOn($locked);
            }

            $locked->load(['employee.user', 'leaveType']);
            $change($locked);

            return $locked;
        });
    }

    private function alreadyActedOn(LeaveRequest $request): ValidationException
    {
        return ValidationException::withMessages(['status' => __('This application is already :status.', ['status' => lcfirst($request->status->label())])]);
    }

    /**
     * Mark the earliest days as paid until the paid days run out.
     */
    private function allocatePay(LeaveRequest $request, int $withPay): void
    {
        $remaining = $withPay;

        $request->leaveDays()->get()->each(function (LeaveRequestDay $day) use (&$remaining): void {
            $paid = min($remaining, LeaveDays::toThousandths($day->days));
            $remaining -= $paid;

            $day->update(['days_with_pay' => LeaveDays::toDecimal($paid)]);
        });
    }

    /**
     * Recompute the DTR for the leave dates that have already come.
     */
    private function refreshAttendance(LeaveRequest $request): void
    {
        $dates = $request->leaveDays()
            ->whereDate('date', '<=', CarbonImmutable::today()->toDateString())
            ->pluck('date')
            ->map(fn ($date): string => $date->toDateString())
            ->all();

        if ($dates !== []) {
            $this->attendance->processDates([$request->employee_id => $dates]);
        }
    }

    private function notifyEmployee(LeaveRequest $request, LeaveRequestEvent $event): void
    {
        if ($request->employee->user !== null) {
            $this->notify(collect([$request->employee->user]), $request, $event);
        }
    }

    /**
     * @param  Collection<int, User>|iterable<User>  $users
     */
    private function notify(iterable $users, LeaveRequest $request, LeaveRequestEvent $event): void
    {
        Notification::send($users, new LeaveRequestNotification($request, $event));
    }
}
