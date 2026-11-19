<?php

namespace App\Http\Controllers\ReferenceData;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReferenceData\PositionRequest;
use App\Models\Department;
use App\Models\Position;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Positions are deactivated rather than deleted so historical records keep their title.
 */
class PositionController extends Controller
{
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

    public function store(PositionRequest $request): RedirectResponse
    {
        $position = Position::create($request->validated());

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

    public function update(PositionRequest $request, Position $position): RedirectResponse
    {
        $position->update($request->validated());

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
