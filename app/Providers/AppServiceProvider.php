<?php

namespace App\Providers;

use App\Enums\Permission;
use App\Models\ChangeRequest;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Console\ServeCommand;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // On Windows, PHP needs TEMP/TMP to find a writable folder for uploads.
        // `artisan serve` strips them from its workers, which breaks 201-file uploads.
        ServeCommand::$passthroughVariables = array_values(array_unique([
            ...ServeCommand::$passthroughVariables,
            'TEMP',
            'TMP',
        ]));

        $this->configureUploadsDisk();
    }

    /**
     * Point the "uploads" disk at the disk named in filesystems.uploads_disk.
     * This runs after Laravel Cloud has registered its bucket disks, which
     * the config file cannot see.
     */
    private function configureUploadsDisk(): void
    {
        $diskName = config('filesystems.uploads_disk');
        $disk = config("filesystems.disks.{$diskName}");

        if ($diskName !== 'uploads' && is_array($disk)) {
            config(['filesystems.disks.uploads' => $disk]);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // The server runs on the municipal LAN without internet access, so the
        // breached-password check (uncompromised) is intentionally not used.
        Password::defaults(fn (): Password => Password::min(12)
            ->max(72)
            ->letters()
            ->mixedCase()
            ->numbers()
            ->symbols());

        URL::forceHttps(config('app.force_https'));

        // A kiosk checks in twice a minute and sends a punch per scan; this
        // leaves plenty of room for a morning queue while capping a runaway client.
        RateLimiter::for('kiosk', fn (Request $request): Limit => Limit::perMinute(240)->by($request->ip()));

        $this->preventLazyLoading();

        // The notification bell and the Approvals menu item in the main layout.
        View::composer('layouts.app', function (\Illuminate\View\View $view): void {
            $user = Auth::user();
            $isReviewer = $user?->can(Permission::ApproveChanges) ?? false;

            $view->with([
                'unreadNotificationCount' => $user?->unreadNotifications()->count() ?? 0,
                'recentNotifications' => $user?->notifications()->latest()->limit(8)->get() ?? collect(),
                'showApprovals' => $user?->can('viewAny', ChangeRequest::class) ?? false,
                'pendingApprovalCount' => $isReviewer ? ChangeRequest::query()->pending()->where('requested_by', '!=', $user->id)->count() : 0,
                'delegatedPermission' => $this->delegatedPermissionForPage($user),
            ]);
        });
    }

    /**
     * The permission behind the current HR page when the user holds it only
     * as a granted extra, so the layout can say their changes need approval.
     */
    private function delegatedPermissionForPage(?User $user): ?Permission
    {
        $routeName = request()->route()?->getName();

        if ($user === null || $routeName === null) {
            return null;
        }

        $permission = match (true) {
            str_starts_with($routeName, 'employees.') => Permission::ManageEmployees,
            str_starts_with($routeName, 'attendance.') && $routeName !== 'attendance.my' => Permission::ManageAttendance,
            str_starts_with($routeName, 'leave.requests.') && ! in_array($routeName, ['leave.requests.create', 'leave.requests.store'], true) => Permission::ApproveLeave,
            Str::startsWith($routeName, ['leave.credits', 'leave.card', 'leave.posting.', 'leave.opening-balances.', 'leave.types.']) => Permission::ManageLeaveCredits,
            str_starts_with($routeName, 'payroll.') && ! str_starts_with($routeName, 'payroll.payslips.') => Permission::ProcessPayroll,
            str_starts_with($routeName, 'work-calendar.') => Permission::ManageWorkCalendar,
            str_starts_with($routeName, 'reference-data.') => Permission::ManageReferenceData,
            str_starts_with($routeName, 'announcements.') => Permission::ManageAnnouncements,
            default => null,
        };

        return $permission !== null && $user->needsApprovalFor($permission) ? $permission : null;
    }

    /**
     * Catch N+1 queries: loading a relationship one model at a time inside a
     * loop. The test suite fails on them; a development server only logs them,
     * so a demo never breaks over one. Production is unaffected.
     */
    private function preventLazyLoading(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());

        if (! $this->app->runningUnitTests()) {
            Model::handleLazyLoadingViolationUsing(function (Model $model, string $relation): void {
                Log::warning(sprintf('Lazy loading of %s::%s (N+1 query).', $model::class, $relation));
            });
        }
    }
}
