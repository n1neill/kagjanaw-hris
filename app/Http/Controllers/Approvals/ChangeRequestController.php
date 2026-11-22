<?php

namespace App\Http\Controllers\Approvals;

use App\Enums\ChangeRequestStatus;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\ChangeRequest;
use App\Services\Approvals\ChangeRequestPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The approval queue: changes made through granted permissions, waiting for
 * an HR Officer. HR Officers see the queue and recent decisions; submitters
 * see their own submissions.
 */
class ChangeRequestController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', ChangeRequest::class);

        $user = $request->user();
        $isReviewer = $user->can(Permission::ApproveChanges);

        return view('approvals.index', [
            'isReviewer' => $isReviewer,
            'pending' => $isReviewer
                ? ChangeRequest::query()->pending()->with('requester')->oldest()->oldest('id')->get()
                : collect(),
            'decided' => $isReviewer
                ? ChangeRequest::query()->where('status', '!=', ChangeRequestStatus::Pending)->with(['requester', 'reviewer'])->latest('updated_at')->latest('id')->limit(50)->get()
                : collect(),
            'submissions' => ChangeRequest::query()->where('requested_by', $user->id)->with('reviewer')->latest()->latest('id')->limit(100)->get(),
        ]);
    }

    public function show(ChangeRequest $changeRequest, ChangeRequestPresenter $presenter): View
    {
        Gate::authorize('view', $changeRequest);

        $changeRequest->load(['requester', 'reviewer', 'subject']);

        return view('approvals.show', [
            'change' => $changeRequest,
            'rows' => $presenter->rows($changeRequest),
        ]);
    }

    /**
     * Download a file submitted with a pending change, so it can be checked
     * before approval. Files are removed once the change is decided.
     */
    public function file(ChangeRequest $changeRequest, string $key): StreamedResponse
    {
        Gate::authorize('view', $changeRequest);

        $file = $changeRequest->files[$key] ?? null;
        $disk = Storage::disk(ChangeRequest::DISK);

        abort_if($file === null || ! $disk->exists($file['path']), 404);

        return $disk->download($file['path'], $file['name'], [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }
}
