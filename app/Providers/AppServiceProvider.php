<?php

namespace App\Providers;

use Illuminate\Foundation\Console\ServeCommand;
use Illuminate\Support\Facades\Auth;
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

        // The notification bell in the main layout.
        View::composer('layouts.app', function (\Illuminate\View\View $view): void {
            $user = Auth::user();

            $view->with([
                'unreadNotificationCount' => $user?->unreadNotifications()->count() ?? 0,
                'recentNotifications' => $user?->notifications()->latest()->limit(8)->get() ?? collect(),
            ]);
        });
    }
}
