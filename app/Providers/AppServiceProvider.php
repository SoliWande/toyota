<?php

namespace App\Providers;

use App\Services\AdministrativeDivisions;
use App\Services\ToyotaRealisticDemo;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(AdministrativeDivisions::class);
        $this->app->singleton(ToyotaRealisticDemo::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Laravel 13 no longer supplies the legacy login-route fallback.
        AuthenticationException::redirectUsing(
            fn (Request $request) => $request->expectsJson() ? null : route('login')
        );

        if ($this->app->environment('production')) {
            // Fail safe even if deployment accidentally reuses local environment settings.
            config(['app.debug' => false, 'session.secure' => true]);
        }
    }
}
