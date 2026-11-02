<?php

namespace App\Http\Controllers;

use App\Models\CustomerSubmission;
use App\Models\Dealer;
use App\Services\DealerRegistrationDraft;
use App\Services\RegistrationPhone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class DealerRegistrationDraftController extends Controller
{
    public function store(Request $request, string $slug, DealerRegistrationDraft $drafts)
    {
        $dealer = Dealer::where('slug', $slug)->where('is_active', true)->firstOrFail();
        $input = $request->all();
        if (is_string($input['phone'] ?? null)) {
            $input['phone'] = RegistrationPhone::normalize($input['phone']);
        }
        $rules = [
            'customer_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^0[1-9][0-9]{8,9}$/D'],
            'vehicle_description' => ['required', 'string', Rule::in(DealerRegistrationDraft::VEHICLES)],
            'website' => ['nullable', 'string', 'max:0'],
        ];
        // An allowlist rejects all system fields, including future fields, without echoing input.
        foreach (array_diff(array_keys($input), [...array_keys($rules), '_token']) as $field) {
            $rules[$field] = ['prohibited'];
        }
        $validator = Validator::make($input, $rules);
        $validator->fails();
        $messages = [
            'customer_name' => ['required' => 'Vui lòng nhập họ tên.', 'max' => 'Họ tên không được vượt quá 255 ký tự.', 'default' => 'Họ tên không hợp lệ.'],
            'phone' => ['required' => 'Vui lòng nhập số điện thoại.', 'default' => 'Số điện thoại không hợp lệ.'],
            'vehicle_description' => ['default' => 'Vui lòng chọn dòng xe.'],
            'website' => ['default' => 'Vui lòng để trống trường Website.'],
        ];
        $errors = [];
        foreach ($validator->failed() as $field => $failed) {
            $fieldMessages = $messages[$field] ?? ['default' => 'Trường này không được phép gửi.'];
            $rule = strtolower(array_key_first($failed));
            $errors[$field] = [$fieldMessages[$rule] ?? $fieldMessages['default']];
        }
        $values = [];
        foreach (['customer_name', 'phone', 'vehicle_description'] as $field) {
            // Preserve editable text, including invalid phone text, only in the existing encrypted draft.
            $raw = $request->input($field);
            if (is_string($raw)) {
                $values[$field] = $field === 'phone' && ! isset($errors['phone'])
                    ? $input[$field] : mb_substr($raw, 0, $field === 'customer_name' ? 255 : 30);
            }
        }
        $duplicate = false;
        if (! isset($errors['phone'])) {
            // Historical Sales rows can have missing/stale normalized phone values.
            // Never expose matching rows or put raw phone into a query/cache key.
            foreach (CustomerSubmission::whereIn('status', ['pending', 'approved'])->select(['id', 'phone', 'phone_normalized'])->lazyById(500) as $existing) {
                if (RegistrationPhone::normalize($existing->phone ?? '') === $input['phone']
                    || RegistrationPhone::normalize($existing->phone_normalized ?? '') === $input['phone']) {
                    $duplicate = true;
                    $errors['phone'] = ['Số điện thoại này đã đăng ký tham gia cộng đồng.'];
                    break;
                }
            }
        }
        $drafts->write($request, $dealer, $values, $errors, $duplicate);

        return redirect()->to($errors ? route('dealers.profile', $slug).'#registration' : route('dealers.registrations.step-two', $slug))
            ->header('Cache-Control', 'no-store, private');
    }

    public function show(Request $request, string $slug, DealerRegistrationDraft $drafts)
    {
        $dealer = Dealer::where('slug', $slug)->firstOrFail();
        $draft = $drafts->read($request, $dealer);
        if (! $dealer->is_active || ! ($draft['complete'] ?? false)) {
            return redirect()->to(route('dealers.profile', $slug).'#registration')
                ->with('dealer_draft_notice', 'Phiên đăng ký không còn hợp lệ. Vui lòng nhập lại thông tin bước 1.')
                ->header('Cache-Control', 'no-store, private');
        }

        return response()->view('dealers.registration.step-two', ['dealer' => $dealer, 'values' => $draft['values']])
            ->header('Cache-Control', 'no-store, private');
    }
}
