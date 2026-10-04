<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
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
    }
}
