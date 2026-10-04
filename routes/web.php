<?php

use App\Http\Controllers\AccountStatusController;
use App\Http\Controllers\Admin\AwardController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\DealerController;
use App\Http\Controllers\Admin\SalesController;
use App\Http\Controllers\Admin\SubmissionReviewController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\AwardHistoryController;
use App\Http\Controllers\LandingPageController;
use App\Http\Controllers\LeaderboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Sales\DashboardController;
use App\Http\Controllers\Sales\SubmissionController;
use App\Http\Controllers\SubmissionEvidenceController;
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
        Route::resource('submissions', SubmissionController::class)->except(['store', 'update', 'destroy']);
        Route::middleware('throttle:submissions')->group(function () {
            Route::post('submissions', [SubmissionController::class, 'store'])->name('submissions.store');
            Route::match(['put', 'patch'], 'submissions/{submission}', [SubmissionController::class, 'update'])->name('submissions.update');
            Route::delete('submissions/{submission}', [SubmissionController::class, 'destroy'])->name('submissions.destroy');
        });
    });
    Route::get('/admin/dashboard', AdminDashboardController::class)->middleware(['role:admin', 'account.active'])->name('admin.dashboard');
    Route::prefix('admin')->name('admin.')->middleware(['role:admin', 'account.active', 'throttle:admin-writes'])->group(function () {
        Route::get('/awards', [AwardController::class, 'index'])->name('awards.index');
        Route::post('/awards', [AwardController::class, 'store'])->name('awards.store');
        Route::get('/awards/{award}', [AwardController::class, 'show'])->whereNumber('award')->name('awards.show');
        Route::get('/submissions', [SubmissionReviewController::class, 'index'])->name('submissions.index');
        Route::get('/submissions/{submission}', [SubmissionReviewController::class, 'show'])->whereNumber('submission')->name('submissions.show');
        Route::post('/submissions/{submission}/approve', [SubmissionReviewController::class, 'approve'])->whereNumber('submission')->name('submissions.approve');
        Route::post('/submissions/{submission}/reject', [SubmissionReviewController::class, 'reject'])->whereNumber('submission')->name('submissions.reject');
        Route::resource('dealers', DealerController::class)->except(['show', 'destroy']);
        Route::patch('/dealers/{dealer}/status', [DealerController::class, 'status'])->whereNumber('dealer')->name('dealers.status');
        Route::get('/sales', [SalesController::class, 'index'])->name('sales.index');
        Route::get('/sales/{sales}/edit', [SalesController::class, 'edit'])->whereNumber('sales')->name('sales.edit');
        Route::patch('/sales/{sales}', [SalesController::class, 'update'])->whereNumber('sales')->name('sales.update');
        Route::get('/sales/{sales}', [SalesController::class, 'show'])->whereNumber('sales')->name('sales.show');
        Route::post('/sales/{sales}/{action}', [SalesController::class, 'review'])
            ->whereNumber('sales')->whereIn('action', ['approve', 'reject', 'block', 'reactivate'])->name('sales.review');
    });
});
