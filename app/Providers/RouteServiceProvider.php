<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/account/status';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $email = $request->input('email');
            $identity = is_string($email) ? mb_strtolower(trim($email)) : '';

            return [
                Limit::perMinute(30)->by('login-ip:'.$request->ip()),
                Limit::perMinute(5)->by('login-user:'.hash('sha256', $identity.'|'.$request->ip())),
            ];
        });

        RateLimiter::for('registration', fn (Request $request) => Limit::perHour(5)->by('register-ip:'.$request->ip()));
        RateLimiter::for('password-email', fn (Request $request) => Limit::perMinute(5)->by('password-email:'.$request->ip()));
        RateLimiter::for('password-reset', fn (Request $request) => Limit::perMinute(10)->by('password-reset:'.$request->ip()));
        RateLimiter::for('profile-writes', fn (Request $request) => Limit::perMinute(20)->by('profile:'.$request->user()->id));
        RateLimiter::for('password-change', fn (Request $request) => Limit::perMinute(5)->by('password-change:'.$request->user()->id));
        RateLimiter::for('submissions', fn (Request $request) => Limit::perMinute(20)->by('submissions-user:'.$request->user()->id));
        RateLimiter::for('admin-writes', fn (Request $request) => $request->isMethod('GET') || $request->isMethod('HEAD')
            ? Limit::none()
            : Limit::perMinute(60)->by('admin-writes:'.$request->user()->id));

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }
}
