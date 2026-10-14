<?php

namespace App\Http\Controllers;

use App\Enums\AnnouncementAudience;
use App\Enums\Permission;
use App\Http\Requests\AnnouncementRequest;
use App\Models\Announcement;
use App\Models\Department;
use App\Services\AnnouncementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Announcements: everyone reads the current ones addressed to them; the HR
 * Office also sees drafts and expired ones, and posts, edits and deletes.
 */
class AnnouncementController extends Controller
{
    public function __construct(private AnnouncementService $announcements) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $canManage = $user->can(Permission::ManageAnnouncements);

        return view('announcements.index', [
            'announcements' => Announcement::query()
                ->when(! $canManage, fn ($query) => $query->visibleTo($user))
                ->with(['author', 'departments'])
                ->latestFirst()
                ->paginate(10),
            'canManage' => $canManage,
        ]);
    }

    public function show(Announcement $announcement): View
    {
        Gate::authorize('view', $announcement);

        $announcement->load(['author', 'departments']);

        return view('announcements.show', [
            'announcement' => $announcement,
            'recipientCount' => Gate::allows('update', $announcement) ? $this->announcements->recipients($announcement)->count() : null,
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Announcement::class);

        $announcement = new Announcement(['audience' => AnnouncementAudience::Everyone]);

        return view('announcements.form', [
            'announcement' => $announcement,
            ...$this->formOptions($announcement),
        ]);
    }

    public function store(AnnouncementRequest $request): RedirectResponse
    {
        $announcement = $this->announcements->create($request->announcement(), $request->shouldPublish(), $request->user());

        return redirect()->route('announcements.show', $announcement)
            ->with('status', $request->shouldPublish() ? __('Announcement published.') : __('Draft saved. It is not shown to employees until you publish it.'));
    }

    public function edit(Announcement $announcement): View
    {
        Gate::authorize('update', $announcement);

        $announcement->load('departments');

        return view('announcements.form', [
            'announcement' => $announcement,
            ...$this->formOptions($announcement),
        ]);
    }

    public function update(AnnouncementRequest $request, Announcement $announcement): RedirectResponse
    {
        $wasPublished = $announcement->published_at !== null;

        $this->announcements->update($announcement, $request->announcement(), $request->shouldPublish() || $wasPublished);

        return redirect()->route('announcements.show', $announcement)->with('status', __('Announcement saved.'));
    }

    public function destroy(Announcement $announcement): RedirectResponse
    {
        Gate::authorize('delete', $announcement);

        $announcement->delete();

        return redirect()->route('announcements.index')->with('status', __('Announcement deleted.'));
    }

    /**
     * Select options for the form. An inactive office stays listed only when
     * the announcement is already addressed to it.
     *
     * @return array<string, mixed>
     */
    private function formOptions(Announcement $announcement): array
    {
        $selected = $announcement->exists ? $announcement->departments->pluck('id') : collect();

        return [
            'audiences' => AnnouncementAudience::options(),
            'departments' => Department::query()
                ->where(fn ($query) => $query->where('is_active', true)->orWhereIn('id', $selected))
                ->orderBy('name')
                ->pluck('name', 'id'),
        ];
    }
}
