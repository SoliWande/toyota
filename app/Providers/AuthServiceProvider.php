<?php

namespace App\Providers;

// use Illuminate\Support\Facades\Gate;
use App\Auth\DealerUserProvider;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Auth;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        // Policies are discovered using Laravel's model/policy conventions.
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        Auth::provider('dealer-accounts', fn ($app, array $config) => new DealerUserProvider($app['hash'], $config['model']));
    }
}
