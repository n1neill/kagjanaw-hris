<?php

namespace App\Http\Controllers\Leave;

use App\Enums\ChangeAction;
use App\Enums\Permission;
use App\Http\Controllers\Concerns\SendsChangesForApproval;
use App\Http\Controllers\Controller;
use App\Http\Requests\Leave\ImportOpeningBalancesRequest;
use App\Services\Approvals\ChangeRequestService;
use App\Services\Leave\OpeningBalanceImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Bulk import of vacation and sick leave balances as of a cut-off date.
 */
class OpeningBalanceImportController extends Controller
{
    use SendsChangesForApproval;

    public function create(): View
    {
        Gate::authorize(Permission::ManageLeaveCredits);

        return view('leave.opening-balances', [
            'columns' => OpeningBalanceImporter::COLUMNS,
            'maxRows' => OpeningBalanceImporter::MAX_ROWS,
            'suggestedAsOf' => now()->subMonthNoOverflow()->endOfMonth()->toDateString(),
        ]);
    }

    public function store(ImportOpeningBalancesRequest $request, ChangeRequestService $changes): RedirectResponse
    {
        $outcome = $changes->perform(
            $request->user(),
            ChangeAction::ImportOpeningBalances,
            ['as_of' => $request->asOf()->toDateString(), 'filename' => $request->file('file')->getClientOriginalName()],
            files: ['file' => $request->file('file')],
        );

        if ($outcome->isQueued()) {
            return $this->sentForApproval(route('leave.opening-balances.create'));
        }

        $result = $outcome->result;

        return redirect()
            ->route('leave.opening-balances.create')
            ->with('importResult', [
                ...$result,
                'filename' => $request->file('file')->getClientOriginalName(),
                'asOf' => $request->asOf()->format('M j, Y'),
            ]);
    }

    public function template(): StreamedResponse
    {
        Gate::authorize(Permission::ManageLeaveCredits);

        return response()->streamDownload(
            function (): void {
                echo OpeningBalanceImporter::template();
            },
            'leave-opening-balances-template.csv',
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }
}
