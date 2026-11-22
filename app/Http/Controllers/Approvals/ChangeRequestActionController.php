<?php

namespace App\Http\Controllers\Approvals;

use App\Http\Controllers\Controller;
use App\Http\Requests\Approvals\ReviewChangeRequest;
use App\Models\ChangeRequest;
use App\Services\Approvals\ChangeCannotBeApplied;
use App\Services\Approvals\ChangeRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Approve, reject or withdraw a submitted change. Authorization for review
 * is in ReviewChangeRequest (via ChangeRequestPolicy).
 */
class ChangeRequestActionController extends Controller
{
    public function __construct(private ChangeRequestService $changes) {}

    public function approve(ReviewChangeRequest $request, ChangeRequest $changeRequest): RedirectResponse
    {
        try {
            $this->changes->approve($changeRequest, $request->user(), $request->validated('remarks'));
        } catch (ChangeCannotBeApplied $exception) {
            return redirect()->route('approvals.show', $changeRequest)
                ->withErrors(['change' => __('The change could not be applied: :reason', ['reason' => $exception->getMessage()])]);
        }

        return redirect()->route('approvals.index')
            ->with('status', __('Change approved and recorded: :change.', ['change' => $changeRequest->description]));
    }

    public function reject(ReviewChangeRequest $request, ChangeRequest $changeRequest): RedirectResponse
    {
        try {
            $this->changes->reject($changeRequest, $request->user(), $request->validated('remarks'));
        } catch (ChangeCannotBeApplied $exception) {
            return redirect()->route('approvals.show', $changeRequest)->withErrors(['change' => $exception->getMessage()]);
        }

        return redirect()->route('approvals.index')
            ->with('status', __('Change rejected: :change.', ['change' => $changeRequest->description]));
    }

    public function cancel(Request $request, ChangeRequest $changeRequest): RedirectResponse
    {
        Gate::authorize('cancel', $changeRequest);

        try {
            $this->changes->cancel($changeRequest, $request->user());
        } catch (ChangeCannotBeApplied $exception) {
            return redirect()->route('approvals.show', $changeRequest)->withErrors(['change' => $exception->getMessage()]);
        }

        return redirect()->route('approvals.index')
            ->with('status', __('Change withdrawn: :change.', ['change' => $changeRequest->description]));
    }
}
