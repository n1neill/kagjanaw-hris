<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Console\ServeCommand;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
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

        $this->preventLazyLoading();

        // The notification bell in the main layout.
        View::composer('layouts.app', function (\Illuminate\View\View $view): void {
            $user = Auth::user();

            $view->with([
                'unreadNotificationCount' => $user?->unreadNotifications()->count() ?? 0,
                'recentNotifications' => $user?->notifications()->latest()->limit(8)->get() ?? collect(),
            ]);
        });
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
