<?php

namespace App\Http\Controllers;

use App\Models\CustomerSubmission;
use App\Services\FacebookUrlNormalizer;
use App\Services\RegistrationLookup;
use App\Services\RegistrationLookupStateResolver;
use App\Services\SubmissionVerification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class RegistrationSupplementController extends Controller
{
    public function store(Request $request, SubmissionVerification $verification)
    {
        $page = app(RegistrationLookupController::class);
        $dealer = $page->dealer($request);
        $key = $request->input('supplement_key');
        abort_unless(is_string($key) && Str::isUuid($key), 403);
        $state = DB::table('submission_verification_states')->where('supplement_key', $key)->first();
        abort_unless($state && $dealer->is_active && $verification->owns($request, $dealer, $state->submission_id), 403);
        $submission = CustomerSubmission::findOrFail($state->submission_id);
        abort_unless($submission->dealer_id === $dealer->id, 403);
        $validator = Validator::make($request->all(), [
            'supplement_key' => ['required', 'uuid'], 'verification_version' => ['required', 'string', 'size:64'],
            'facebook_profile_url' => ['bail', 'required', 'string', 'max:2048', function ($attribute, $value, $fail) {
                try {
                    app(FacebookUrlNormalizer::class)->normalize($value);
                } catch (\InvalidArgumentException) {
                    $fail('Liên kết Facebook không hợp lệ.');
                }
            }], 'website' => ['nullable', 'string', 'max:0'],
        ], ['facebook_profile_url.required' => 'Vui lòng nhập liên kết Facebook cá nhân.',
            'facebook_profile_url.*' => 'Liên kết Facebook không hợp lệ.']);
        $validator->after(function ($validator) use ($request) {
            if (array_diff(array_keys($request->all()), ['supplement_key', 'verification_version', 'facebook_profile_url', 'website', '_token'])) {
                $validator->errors()->add('facebook_profile_url', 'Không thể gửi bổ sung. Vui lòng kiểm tra thông tin.');
            }
        });
        $errors = $validator->fails() ? $validator->errors()->messages() : [];
        if (! $errors) {
            try {
                $verification->respond($request, $dealer, $key, $request->string('verification_version')->toString(), $request->string('facebook_profile_url')->toString());
            } catch (ValidationException $e) {
                $errors = $e->errors();
            }
        }
        if ($errors) {
            $result = app(RegistrationLookupStateResolver::class)->resolve($submission->id,
                app(RegistrationLookup::class)->summary($submission->customer_name, $submission->vehicle_description ?? $submission->vehicle_model), $request, $dealer);
            $result['supplement_errors'] = ['facebook_profile_url' => $errors['facebook_profile_url'] ?? ['Không thể gửi bổ sung. Vui lòng tra cứu lại.']];
            $input = $request->input('facebook_profile_url');
            $result['supplement_value'] = is_string($input) ? mb_substr($input, 0, 2048) : '';

            return $page->page($request, result: $result, status: 422);
        }
        $request->session()->put('lookup_supplemented.'.$dealer->id, $key);

        return redirect()->route('dealers.lookup.supplemented', $dealer->slug)
            ->header('Cache-Control', 'no-store, private')->header('X-Robots-Tag', 'noindex, nofollow')->header('Referrer-Policy', 'no-referrer');
    }

    public function show(Request $request, SubmissionVerification $verification)
    {
        $page = app(RegistrationLookupController::class);
        $dealer = $page->dealer($request);
        $key = $request->session()->get('lookup_supplemented.'.$dealer->id);
        $state = is_string($key) ? DB::table('submission_verification_states')->where('supplement_key', $key)->first() : null;
        abort_unless($state && $state->supplement_state === 'submitted' && $verification->owns($request, $dealer, $state->submission_id), 403);

        return $page->page($request, result: ['kind' => 'supplemented']);
    }
}
