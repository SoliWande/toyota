<?php

use App\Http\Controllers\AccountStatusController;
use App\Http\Controllers\Admin\AwardController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\DealerAccountController;
use App\Http\Controllers\Admin\DealerController;
use App\Http\Controllers\Admin\SalesController;
use App\Http\Controllers\Admin\SubmissionReviewController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\AwardHistoryController;
use App\Http\Controllers\Dealer\AwardController as DealerAwardController;
use App\Http\Controllers\Dealer\CustomerController as DealerCustomerController;
use App\Http\Controllers\Dealer\DashboardController as DealerDashboardController;
use App\Http\Controllers\Dealer\SalesController as DealerSalesController;
use App\Http\Controllers\DealerProfileMediaController;
use App\Http\Controllers\DealerRegistrationController;
use App\Http\Controllers\LandingPageController;
use App\Http\Controllers\LeaderboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicDealerController;
use App\Http\Controllers\PublicSalesProfileController;
use App\Http\Controllers\Sales\AddressController;
use App\Http\Controllers\Sales\DashboardController;
use App\Http\Controllers\Sales\SubmissionController;
use App\Http\Controllers\SalesAvatarController;
use App\Http\Controllers\SubmissionEvidenceController;
use App\Services\DealerSlugs;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', LandingPageController::class)->name('home');
Route::get('/leaderboard', LeaderboardController::class)->name('leaderboard');
Route::get('/dealers', [PublicDealerController::class, 'index'])->name('dealers.index');
Route::get('/dealers/{dealer}', [PublicDealerController::class, 'legacy'])->whereNumber('dealer')->name('dealers.show');
Route::get('/dealers/{dealer}/qr', [DealerProfileMediaController::class, 'qr'])->whereNumber('dealer')->name('dealers.qr');
Route::get('/dealers/{dealer}/photo', [DealerProfileMediaController::class, 'photo'])->whereNumber('dealer')->name('dealers.photo');
Route::get('/awards', [AwardHistoryController::class, 'index'])->name('awards.index');
Route::get('/awards/{award}', [AwardHistoryController::class, 'show'])->whereNumber('award')->name('awards.show');

Route::middleware('guest')->group(function () {
    Route::get('/forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'email'])->middleware('throttle:password-email')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:password-reset')->name('password.store');
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:login');
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])->middleware('throttle:registration');
});

Route::middleware(['auth', 'auth.session'])->group(function () {
    Route::middleware('account.active')->group(function () {
        Route::get('/sales/{sales}/avatar', SalesAvatarController::class)->whereNumber('sales')->name('sales.avatar');
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])->middleware('throttle:profile-writes')->name('profile.update');
        Route::put('/profile/password', [ProfileController::class, 'password'])->middleware('throttle:password-change')->name('profile.password');
    });
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::get('/account/status', AccountStatusController::class)->name('account.status');
    Route::get('/submissions/{submission}/evidence', SubmissionEvidenceController::class)
        ->whereNumber('submission')->middleware('account.active')->name('submissions.evidence');
    Route::get('/sales/dashboard', DashboardController::class)->middleware(['role:sales', 'account.active'])->name('sales.dashboard');
    Route::prefix('sales')->name('sales.')->middleware(['role:sales', 'account.active'])->group(function () {
        Route::get('address/wards/{province}', AddressController::class)->where('province', '[0-9]{2}')->name('address.wards');
        Route::resource('submissions', SubmissionController::class)->except(['store', 'update', 'destroy']);
        Route::middleware('throttle:submissions')->group(function () {
            Route::post('submissions', [SubmissionController::class, 'store'])->name('submissions.store');
            Route::match(['put', 'patch'], 'submissions/{submission}', [SubmissionController::class, 'update'])->name('submissions.update');
            Route::delete('submissions/{submission}', [SubmissionController::class, 'destroy'])->name('submissions.destroy');
        });
    });
    Route::get('/admin/dashboard', AdminDashboardController::class)->middleware(['role:admin', 'account.active'])->name('admin.dashboard');
    Route::get('/dealer/dashboard', DealerDashboardController::class)->middleware(['role:dealer', 'account.active'])->name('dealer.dashboard');
    Route::prefix('dealer')->name('dealer.')->middleware(['role:dealer', 'account.active'])->group(function () {
        Route::get('/sales', [DealerSalesController::class, 'index'])->name('sales.index');
        Route::get('/sales/{sales}', [DealerSalesController::class, 'show'])->whereNumber('sales')->name('sales.show');
        Route::get('/customers', [DealerCustomerController::class, 'index'])->name('customers.index');
        Route::get('/customers/{submission}', [DealerCustomerController::class, 'show'])->whereNumber('submission')->name('customers.show');
        Route::get('/awards', [DealerAwardController::class, 'index'])->name('awards.index');
    });
    Route::prefix('admin')->name('admin.')->middleware(['role:admin', 'account.active', 'throttle:admin-writes'])->group(function () {
        Route::get('/awards', [AwardController::class, 'index'])->name('awards.index');
        Route::post('/awards', [AwardController::class, 'store'])->name('awards.store');
        Route::get('/awards/{award}', [AwardController::class, 'show'])->whereNumber('award')->name('awards.show');
        Route::get('/submissions', [SubmissionReviewController::class, 'index'])->name('submissions.index');
        Route::get('/submissions/address/wards/{province}', [SubmissionReviewController::class, 'wards'])->where('province', '[0-9]{2}')->name('submissions.address.wards');
        Route::patch('/submissions/{submission}', [SubmissionReviewController::class, 'update'])->whereNumber('submission')->name('submissions.update');
        Route::get('/submissions/{submission}', [SubmissionReviewController::class, 'show'])->whereNumber('submission')->name('submissions.show');
        Route::post('/submissions/{submission}/approve', [SubmissionReviewController::class, 'approve'])->whereNumber('submission')->name('submissions.approve');
        Route::post('/submissions/{submission}/reject', [SubmissionReviewController::class, 'reject'])->whereNumber('submission')->name('submissions.reject');
        Route::resource('dealers', DealerController::class)->except(['destroy']);
        Route::get('/dealers/{dealer}/account', [DealerAccountController::class, 'edit'])->whereNumber('dealer')->name('dealers.account.edit');
        Route::post('/dealers/{dealer}/account', [DealerAccountController::class, 'store'])->whereNumber('dealer')->name('dealers.account.store');
        Route::patch('/dealers/{dealer}/account', [DealerAccountController::class, 'update'])->whereNumber('dealer')->name('dealers.account.update');
        Route::patch('/dealers/{dealer}/status', [DealerController::class, 'status'])->whereNumber('dealer')->name('dealers.status');
        Route::get('/sales', [SalesController::class, 'index'])->name('sales.index');
        Route::get('/sales/{sales}/edit', [SalesController::class, 'edit'])->whereNumber('sales')->name('sales.edit');
        Route::patch('/sales/{sales}', [SalesController::class, 'update'])->whereNumber('sales')->name('sales.update');
        Route::get('/sales/{sales}', [SalesController::class, 'show'])->whereNumber('sales')->name('sales.show');
        Route::post('/sales/{sales}/{action}', [SalesController::class, 'review'])
            ->whereNumber('sales')->whereIn('action', ['approve', 'reject', 'block', 'reactivate'])->name('sales.review');
    });
});

// One-segment Dealer slugs are resolved after all application routes.
// The opaque s-UUID pattern cannot capture /sales/dashboard, /sales/submissions or numeric private avatar routes.
Route::get('/sales/{slug}/avatar', [PublicSalesProfileController::class, 'avatar'])
    ->where('slug', 's-[a-f0-9]{32}')->middleware('throttle:public-sales')->name('sales.public.avatar');
Route::get('/sales/{slug}', [PublicSalesProfileController::class, 'show'])
    ->where('slug', 's-[a-f0-9]{32}')->middleware('throttle:public-sales')->name('sales.public.show');
Route::post('/{slug}/registrations', [DealerRegistrationController::class, 'store'])
    ->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')->middleware('throttle:dealer-registrations')->name('dealers.registrations.store');
Route::get('/{slug}/address/wards/{province}', [DealerRegistrationController::class, 'wards'])
    ->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')->where('province', '[0-9]{2}')
    ->middleware('throttle:dealer-address')->name('dealers.address.wards');
Route::get('/{slug}', [PublicDealerController::class, 'show'])
    ->where('slug', '(?!(?:'.implode('|', DealerSlugs::RESERVED).')(?:/|$))[a-z0-9]+(?:-[a-z0-9]+)*')->name('dealers.profile');
