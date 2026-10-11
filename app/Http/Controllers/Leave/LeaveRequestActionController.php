<?php

namespace App\Http\Controllers\Leave;

use App\Http\Controllers\Controller;
use App\Http\Requests\Leave\LeaveRemarksRequest;
use App\Models\LeaveRequest;
use App\Services\Leave\LeaveRequestService;
use Illuminate\Http\RedirectResponse;

/**
 * Endorse, approve, reject or cancel a leave application. Authorization
 * per action is in LeaveRemarksRequest (via LeaveRequestPolicy).
 */
class LeaveRequestActionController extends Controller
{
    public function __construct(private LeaveRequestService $service) {}

    public function endorse(LeaveRemarksRequest $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        $this->service->endorse($leaveRequest, $request->user(), $request->validated('remarks'));

        return $this->backTo($leaveRequest, __('The application was endorsed to HR.'));
    }

    public function approve(LeaveRemarksRequest $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        $approved = $this->service->approve($leaveRequest, $request->user(), $request->validated('remarks'));

        return $this->backTo($leaveRequest, $approved->days_without_pay !== '0.000'
            ? __('The application was approved: :paid days with pay and :unpaid days without pay.', ['paid' => $approved->days_with_pay, 'unpaid' => $approved->days_without_pay])
            : __('The application was approved.'));
    }

    public function reject(LeaveRemarksRequest $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        $this->service->reject($leaveRequest, $request->user(), $request->validated('remarks'));

        return $this->backTo($leaveRequest, __('The application was rejected.'));
    }

    public function cancel(LeaveRemarksRequest $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        $this->service->cancel($leaveRequest, $request->user(), $request->validated('remarks'));

        return $this->backTo($leaveRequest, __('The application was cancelled.'));
    }

    private function backTo(LeaveRequest $leaveRequest, string $message): RedirectResponse
    {
        return redirect()->route('leave.requests.show', $leaveRequest)->with('status', $message);
    }
}
