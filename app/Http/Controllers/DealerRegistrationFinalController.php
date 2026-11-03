<?php

namespace App\Http\Controllers;

use App\Models\CustomerSubmission;
use App\Models\Dealer;
use App\Services\AdministrativeDivisions;
use App\Services\DealerRegistrationDraft;
use App\Services\DealerRegistrationReceipt;
use App\Services\FacebookUrlNormalizer;
use App\Services\SubmissionIntake;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class DealerRegistrationFinalController extends Controller
{
    public function store(Request $request, string $slug, DealerRegistrationDraft $drafts, DealerRegistrationReceipt $receipts, SubmissionIntake $intake)
    {
        $dealer = Dealer::where('slug', $slug)->firstOrFail();
        $draftId = $request->input('_draft');
        if (! $dealer->is_active || ! is_string($draftId) || ! preg_match('/^[a-f0-9-]{36}$/D', $draftId)) {
            return $this->restart($slug);
        }
        // A replay never revalidates mutable payload or creates another submission.
        abort_if($request->filled('website'), 422);
        if ($receipts->find($request, $dealer, $draftId)) {
            return $this->success($slug);
        }
        $draft = $drafts->read($request, $dealer);
        if (! ($draft['complete'] ?? false) || ($draft['id'] ?? null) !== $draftId) {
            return $this->restart($slug);
        }
        $directory = app(AdministrativeDivisions::class);
        $input = $request->all();
        if (is_string($input['license_plate'] ?? null)) {
            $input['license_plate'] = SubmissionIntake::plate($input['license_plate']);
        }
        if (is_string($input['facebook_url'] ?? null) && ! preg_match('~^https?://~i', $input['facebook_url'])) {
            $input['facebook_url'] = 'https://'.$input['facebook_url'];
        }
        $rules = [
            'province_code' => ['bail', 'required', 'string', Rule::in(array_column($directory->provinces(), 'code'))],
            'ward_code' => ['bail', 'required', 'string', function ($attribute, $value, $fail) use ($directory, $input) {
                $province = $input['province_code'] ?? null;
                if (! is_string($province) || ! $directory->ward($province, $value)) {
                    $fail('Vui lòng chọn Phường/Xã.');
                }
            }],
            'address_line' => ['required', 'string', 'max:500'],
            'license_plate' => ['bail', 'required', 'string', 'regex:/^[A-Z0-9]{3,20}$/D'],
            'first_registration_year' => ['required', 'integer', 'between:1950,'.now()->year],
            'odo' => ['nullable', 'integer', 'min:0', 'max:4294967295'],
            'facebook_url' => ['bail', 'required', 'string', 'max:2048', function ($attribute, $value, $fail) {
                try {
                    app(FacebookUrlNormalizer::class)->normalize($value);
                } catch (\InvalidArgumentException) {
                    $fail('Liên kết Facebook không hợp lệ.');
                }
            }],
            'consent' => ['required', 'accepted'], 'website' => ['nullable', 'string', 'max:0'],
            '_draft' => ['required', 'string'], '_flow' => ['required', Rule::in(['two-step'])],
        ];
        foreach (array_diff(array_keys($input), [...array_keys($rules), '_token']) as $field) {
            $rules[$field] = ['prohibited'];
        }
        $validator = Validator::make($input, $rules);
        $validator->fails();
        $errors = array_map(fn () => ['invalid'], $validator->errors()->messages());
        // Keep bounded editable data in the encrypted draft, never in shared old input.
        $values = [];
        foreach (['province_code' => 2, 'ward_code' => 5, 'address_line' => 500, 'license_plate' => 30,
            'first_registration_year' => 8, 'odo' => 30, 'facebook_url' => 2048, 'consent' => 5] as $field => $limit) {
            if (is_scalar($input[$field] ?? null)) {
                $values[$field] = mb_substr((string) $input[$field], 0, $limit);
            }
        }
        $draft['step_two'] = ['values' => $values, 'errors' => $errors];
        if ($errors) {
            $this->saveState($request, $dealer, $draft, $drafts, $intake);

            return $this->back($slug);
        }
        try {
            $intake->locked(function () use ($request, $dealer, $draftId, $drafts, $receipts, $directory, $validator) {
                // Mutex precedes Dealer lock everywhere new final intake touches both.
                $active = Dealer::whereKey($dealer->id)->where('is_active', true)->lockForUpdate()->first();
                abort_unless($active, 404);
                // Locking read avoids a stale repeatable-read snapshot after another request commits.
                if (DB::table('dealer_registration_receipts')->where('draft_key', $receipts->key($request, $dealer, $draftId))->lockForUpdate()->exists()) {
                    return;
                }
                $current = $drafts->read($request, $active);
                if (! ($current['complete'] ?? false) || ($current['id'] ?? null) !== $draftId) {
                    abort(409);
                }
                $data = $validator->validated();
                $submission = new CustomerSubmission;
                $submission->forceFill([
                    'source' => 'dealer_qr', 'sales_id' => null, 'dealer_id' => $dealer->id, 'status' => 'pending',
                    'customer_name' => $current['values']['customer_name'], 'phone' => $current['values']['phone'],
                    'phone_normalized' => $current['values']['phone'], 'vehicle_description' => $current['values']['vehicle_description'],
                    'license_plate' => $data['license_plate'], 'first_registration_year' => $data['first_registration_year'],
                    'odo' => $data['odo'] ?? null, 'facebook_url' => $data['facebook_url'],
                    'province_code' => $data['province_code'], 'ward_code' => $data['ward_code'], 'address_line' => $data['address_line'],
                    'province_name' => $directory->province($data['province_code'])['name'],
                    'ward_name' => $directory->ward($data['province_code'], $data['ward_code'])['name'],
                    'consented_at' => now(), 'consent_version' => config('dealer.consent_version'),
                ])->save();
                $confirmation = $submission->only(['customer_name', 'phone', 'license_plate', 'vehicle_description', 'address_line', 'province_name', 'ward_name']);
                DB::table('dealer_registration_receipts')->insert([
                    'draft_key' => $receipts->key($request, $dealer, $draftId), 'owner_key' => $receipts->owner($request, $dealer),
                    'submission_id' => $submission->id, 'confirmation' => Crypt::encryptString(json_encode($confirmation, JSON_THROW_ON_ERROR)),
                    'expires_at' => now()->addDay(), 'created_at' => now(),
                ]);
            });
        } catch (ValidationException $error) {
            $draft['step_two']['errors'] = $error->errors();
            if (isset($error->errors()['phone'])) {
                $draft['errors'] = ['phone' => $error->errors()['phone']];
                $draft['duplicate'] = true;
                $draft['complete'] = false;
            }
            $this->saveState($request, $dealer, $draft, $drafts, $intake);

            return isset($error->errors()['phone']) ? $this->restart($slug, false) : $this->back($slug);
        }
        $intake->locked(function () use ($request, $dealer, $drafts, $draftId) {
            if (($drafts->read($request, $dealer)['id'] ?? null) === $draftId) {
                $drafts->forget($request, $dealer);
            }
        });

        return $this->success($slug);
    }

    private function saveState(Request $request, Dealer $dealer, array $draft, DealerRegistrationDraft $drafts, SubmissionIntake $intake): void
    {
        $intake->locked(function () use ($request, $dealer, $draft, $drafts) {
            abort_unless(($drafts->read($request, $dealer)['id'] ?? null) === $draft['id'], 409);
            $drafts->update($request, $dealer, $draft);
        });
    }

    public function show(Request $request, string $slug, DealerRegistrationReceipt $receipts)
    {
        $dealer = Dealer::where('slug', $slug)->firstOrFail();
        $confirmation = $receipts->find($request, $dealer);
        if (! $confirmation) {
            return $this->restart($slug);
        }

        return response()->view('dealers.registration.success', compact('dealer', 'confirmation'))->header('Cache-Control', 'no-store, private');
    }

    private function restart(string $slug, bool $notice = true)
    {
        $response = redirect()->to(route('dealers.profile', $slug).'#registration')->header('Cache-Control', 'no-store, private');
        if ($notice) {
            $response->with('dealer_draft_notice', 'Phiên đăng ký không còn hợp lệ. Vui lòng nhập lại thông tin bước 1.');
        }

        return $response;
    }

    private function back(string $slug)
    {
        return redirect()->route('dealers.registrations.step-two', $slug)->header('Cache-Control', 'no-store, private');
    }

    private function success(string $slug)
    {
        return redirect()->route('dealers.registrations.success', $slug)->header('Cache-Control', 'no-store, private');
    }
}
