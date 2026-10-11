<?php

namespace App\Http\Controllers\Leave;

use App\Enums\LeavePortion;
use App\Enums\LeaveRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Leave\LeaveRequestIndexRequest;
use App\Http\Requests\Leave\StoreLeaveRequestRequest;
use App\Models\Department;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Services\Leave\LeaveBalanceService;
use App\Services\Leave\LeaveRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LeaveRequestController extends Controller
{
    /**
     * HR's list of applications, by default those endorsed and awaiting approval.
     */
    public function index(LeaveRequestIndexRequest $request): View
    {
        $status = $request->status();

        $requests = LeaveRequest::query()
            ->with(['employee.department', 'leaveType'])
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($request->validated('department'), fn ($query, $department) => $query->whereHas('employee', fn ($employees) => $employees->where('department_id', $department)))
            ->when($request->validated('type'), fn ($query, $type) => $query->where('leave_type_id', $type))
            ->whereYear('date_from', $request->year())
            ->orderByDesc('date_from')
            ->orderByDesc('id')
            ->limit(1000)
            ->get();

        return view('leave.index', [
            'requests' => $requests,
            'status' => $status,
            'statuses' => LeaveRequestStatus::options(),
            'departments' => Department::query()->orderBy('name')->pluck('name', 'id'),
            'types' => LeaveType::query()->ordered()->pluck('name', 'id'),
            'year' => $request->year(),
            'filters' => $request->validated(),
        ]);
    }

    public function create(Request $request, LeaveBalanceService $balances): View
    {
        $employee = $request->user()->employee;

        if ($employee === null) {
            return view('employees.no-record');
        }

        $types = LeaveType::query()->active()->ordered()->get()
            ->filter(fn (LeaveType $type): bool => $type->eligible_sex === null || $type->eligible_sex === $employee->sex)
            ->values();

        return view('leave.create', [
            'employee' => $employee,
            'types' => $types,
            'portions' => collect(LeavePortion::cases())->mapWithKeys(fn (LeavePortion $portion): array => [$portion->value => $portion->label()])->all(),
            'summary' => $balances->summary($employee),
        ]);
    }

    public function store(StoreLeaveRequestRequest $request, LeaveRequestService $service): RedirectResponse
    {
        $leaveRequest = $service->file(
            $request->user()->employee,
            $request->leaveType(),
            $request->dateFrom(),
            $request->dateTo(),
            LeavePortion::from($request->validated('portion')),
            $request->validated('details'),
            $request->file('attachment'),
            $request->user(),
        );

        return redirect()
            ->route('leave.requests.show', $leaveRequest)
            ->with('status', $leaveRequest->status === LeaveRequestStatus::Endorsed
                ? __('Your application was filed and sent to HR.')
                : __('Your application was filed and sent for endorsement.'));
    }

    public function show(Request $request, LeaveRequest $leaveRequest, LeaveBalanceService $balances): View
    {
        Gate::authorize('view', $leaveRequest);

        $leaveRequest->load(['employee.department', 'employee.position', 'leaveType', 'leaveDays', 'filer', 'endorser', 'decider', 'canceller', 'ledgerEntries.leaveType']);

        $user = $request->user();

        return view('leave.show', [
            'leaveRequest' => $leaveRequest,
            'summary' => $balances->summary($leaveRequest->employee, $leaveRequest->date_from->year),
            'isOwn' => $leaveRequest->isFiledBy($user),
            'can' => [
                'endorse' => $user->can('endorse', $leaveRequest),
                'decide' => $user->can('decide', $leaveRequest),
                'cancel' => $user->can('cancel', $leaveRequest),
                'viewLeaveCard' => $user->can('viewLeaveCard', $leaveRequest->employee),
            ],
        ]);
    }

    /**
     * The supporting document, shown only to those who may see the application.
     */
    public function attachment(LeaveRequest $leaveRequest): StreamedResponse
    {
        Gate::authorize('view', $leaveRequest);

        $disk = Storage::disk(LeaveRequest::ATTACHMENT_DISK);
        abort_unless($leaveRequest->hasAttachment() && $disk->exists($leaveRequest->attachment_path), 404);

        $inline = in_array($leaveRequest->attachment_mime, ['application/pdf', 'image/jpeg', 'image/png'], true);

        return $disk->response($leaveRequest->attachment_path, $leaveRequest->attachment_name, [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
            'Content-Type' => $leaveRequest->attachment_mime ?? 'application/octet-stream',
        ], $inline ? 'inline' : 'attachment');
    }
}
