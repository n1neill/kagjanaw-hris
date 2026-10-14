<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AuditLogIndexRequest;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;

/**
 * The audit log, newest first: changes to records, access to personal data,
 * sign-ins and denied requests. Entries cannot be edited or deleted here.
 */
class AuditLogController extends Controller
{
    public function index(AuditLogIndexRequest $request): View
    {
        $filters = $request->validated();

        $entries = AuditLog::query()
            ->with('user')
            ->when($filters['category'] ?? null, fn (Builder $query, string $category) => $query->whereIn('event', AuditEvent::inCategory($category)))
            ->when($filters['event'] ?? null, fn (Builder $query, string $event) => $query->where('event', $event))
            ->when($filters['user'] ?? null, fn (Builder $query, string $user) => $query->whereHas('user', fn (Builder $users) => $users
                ->where('username', 'like', '%'.$user.'%')
                ->orWhere('name', 'like', '%'.$user.'%')))
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where('description', 'like', '%'.$search.'%'))
            ->when($filters['from'] ?? null, fn (Builder $query, string $from) => $query->where('created_at', '>=', $from.' 00:00:00'))
            ->when($filters['to'] ?? null, fn (Builder $query, string $to) => $query->where('created_at', '<=', $to.' 23:59:59'))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        return view('admin.audit-logs.index', [
            'entries' => $entries,
            'filters' => $filters,
            'categories' => collect(AuditEvent::CATEGORIES)->mapWithKeys(fn (string $category): array => [$category => AuditEvent::categoryLabel($category)])->all(),
            'events' => collect(AuditEvent::cases())->mapWithKeys(fn (AuditEvent $event): array => [$event->value => $event->label()])->all(),
            'failedSignInsToday' => AuditLog::query()
                ->whereIn('event', [AuditEvent::LoginFailed, AuditEvent::LockedOut])
                ->where('created_at', '>=', today())
                ->count(),
        ]);
    }

    public function show(AuditLog $auditLog): View
    {
        $auditLog->load('user');

        $keys = array_values(array_unique([...array_keys($auditLog->old_values ?? []), ...array_keys($auditLog->new_values ?? [])]));

        return view('admin.audit-logs.show', [
            'entry' => $auditLog,
            'changes' => collect($keys)->map(fn (string $key): array => [
                'field' => $key,
                'old' => $auditLog->old_values[$key] ?? null,
                'new' => $auditLog->new_values[$key] ?? null,
            ]),
        ]);
    }
}
