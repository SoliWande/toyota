<?php

namespace App\Http\Controllers;

use App\Http\Requests\DealerRegistrationRequest;
use App\Models\CustomerSubmission;
use App\Models\Dealer;
use App\Services\AdministrativeDivisions;
use App\Services\RegistrationPhone;
use App\Services\SubmissionIntake;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Throwable;

class DealerRegistrationController extends Controller
{
    public function store(Request $request, string $slug, AdministrativeDivisions $directory): RedirectResponse|JsonResponse
    {
        if ($request->input('_flow') === 'two-step' || $request->has('_draft')) {
            return app()->call([app(DealerRegistrationFinalController::class), 'store'], ['request' => $request, 'slug' => $slug]);
        }
        $request = app(DealerRegistrationRequest::class);
        $data = $request->validated();
        // An atomic cache add suppresses repeats, including phone formatting variants.
        // This is a short spam cooldown, never a replacement for the approved Facebook identity rule.
        $dealer = Dealer::where('slug', $slug)->where('is_active', true)->firstOrFail();
        $key = 'dealer-qr-phone:'.RegistrationPhone::key($dealer->id.'|'.$data['phone']);
        abort_unless(Cache::add($key, true, 600), 429);

        try {
            app(SubmissionIntake::class)->locked(function () use ($dealer, $data, $directory) {
                // Serialize intake with Dealer activation changes without touching historical attribution.
                Dealer::whereKey($dealer->id)->where('is_active', true)->lockForUpdate()->firstOrFail();
                $submission = new CustomerSubmission;
                $submission->forceFill([
                    'source' => 'dealer_qr', 'sales_id' => null, 'dealer_id' => $dealer->id,
                    'customer_name' => $data['customer_name'], 'phone' => $data['phone'], 'phone_normalized' => $data['phone'],
                    'facebook_url' => null, 'status' => 'pending',
                    'address_line' => $data['address_line'], 'province_code' => $data['province_code'],
                    'province_name' => $directory->province($data['province_code'])['name'],
                    'ward_code' => $data['ward_code'], 'ward_name' => $directory->ward($data['province_code'], $data['ward_code'])['name'],
                    'vehicle_description' => $data['vehicle_description'],
                    'consented_at' => now(), 'consent_version' => config('dealer.consent_version'),
                ])->save();
            });
        } catch (Throwable $error) {
            Cache::forget($key);
            throw $error;
        }

        $message = 'Đã nhận đăng ký. Đại lý sẽ liên hệ để xác minh; thông tin đang chờ Admin duyệt.';
        $response = $request->expectsJson()
            ? response()->json(['message' => $message], 201)
            : redirect()->to(route('dealers.profile', ['slug' => $slug]).'#registration')->with('registration_success', $message);
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }

    public function wards(string $slug, string $province, AdministrativeDivisions $directory): JsonResponse
    {
        abort_unless(Dealer::where('slug', $slug)->where('is_active', true)->exists(), 404);
        abort_if($directory->province($province) === null, 404);

        return response()->json(['wards' => $directory->wards($province)]);
    }
}
