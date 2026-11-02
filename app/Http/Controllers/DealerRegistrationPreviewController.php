<?php

namespace App\Http\Controllers;

use App\Models\Dealer;
use App\Services\AdministrativeDivisions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/** UI-only fixtures: no saved models, sessions, customer records or POST endpoints. */
class DealerRegistrationPreviewController extends Controller
{
    public function show(string $screen = 'landing'): Response
    {
        // Check at request time as well, so a cached route can never expose previews in production.
        abort_unless(app()->environment(['local', 'testing']), 404);
        $dealer = new Dealer;
        $dealer->forceFill(['name' => 'Đại lý Toyota · Xem trước', 'slug' => 'preview-dealer', 'is_active' => $screen !== 'inactive']);
        $view = match ($screen) {
            'landing', 'inactive' => 'dealers.show',
            'step-two' => 'dealers.registration.step-two',
            'success' => 'dealers.registration.success',
            default => abort(404),
        };

        return response()->view($view, ['dealer' => $dealer, 'standing' => null, 'preview' => true])
            ->header('Cache-Control', 'no-store, private')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function wards(string $province, AdministrativeDivisions $directory): JsonResponse
    {
        abort_unless(app()->environment(['local', 'testing']), 404);
        abort_if($directory->province($province) === null, 404);

        return response()->json(['wards' => $directory->wards($province)])->header('Cache-Control', 'no-store, private');
    }
}
