<?php

namespace App\Http\Controllers\ReferenceData;

use App\Enums\ChangeAction;
use App\Http\Controllers\Concerns\SendsChangesForApproval;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReferenceData\PositionRequest;
use App\Models\Department;
use App\Models\Position;
use App\Services\Approvals\ChangeRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Positions are deactivated rather than deleted so historical records keep their title.
 */
class PositionController extends Controller
{
    use SendsChangesForApproval;

    public function index(): View
    {
        return view('reference-data.positions.index', [
            'positions' => Position::query()->with('department')->orderBy('title')->get(),
        ]);
    }

    public function create(): View
    {
        return view('reference-data.positions.form', [
            'position' => new Position(['is_active' => true]),
            'departments' => $this->departments(),
        ]);
    }

    public function store(PositionRequest $request, ChangeRequestService $changes): RedirectResponse
    {
        $outcome = $changes->perform($request->user(), ChangeAction::CreatePosition, $request->validated());

        if ($outcome->isQueued()) {
            return $this->sentForApproval(route('reference-data.positions.index'));
        }

        $position = $outcome->result;

        return redirect()
            ->route('reference-data.positions.index')
            ->with('status', __(':name added.', ['name' => $position->title]));
    }

    public function edit(Position $position): View
    {
        return view('reference-data.positions.form', [
            'position' => $position,
            'departments' => $this->departments(),
        ]);
    }

    public function update(PositionRequest $request, Position $position, ChangeRequestService $changes): RedirectResponse
    {
        $outcome = $changes->perform($request->user(), ChangeAction::UpdatePosition, $request->validated(), $position);

        if ($outcome->isQueued()) {
            return $this->sentForApproval(route('reference-data.positions.index'));
        }

        return redirect()
            ->route('reference-data.positions.index')
            ->with('status', __(':name updated.', ['name' => $position->title]));
    }

    /**
     * @return Collection<int, string>
     */
    private function departments(): Collection
    {
        return Department::query()->orderBy('name')->pluck('name', 'id');
    }
}
