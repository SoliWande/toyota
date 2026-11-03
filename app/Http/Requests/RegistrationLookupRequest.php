<?php

namespace App\Http\Requests;

use App\Http\Controllers\RegistrationLookupController;
use App\Services\RegistrationPhone;
use App\Services\SubmissionIntake;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

final class RegistrationLookupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'phone' => ['bail', 'required', 'string', 'max:30', function ($attribute, $value, $fail) {
                if (! preg_match('/^0[1-9][0-9]{8,9}$/D', RegistrationPhone::normalize($value))) {
                    $fail('Số điện thoại không hợp lệ.');
                }
            }],
            'license_plate' => ['bail', 'required', 'string', 'max:30', function ($attribute, $value, $fail) {
                if (! preg_match('/^[A-Z0-9]{3,20}$/D', SubmissionIntake::plate($value))) {
                    $fail('Biển số xe không hợp lệ.');
                }
            }],
            'website' => ['nullable', 'string', 'max:0'],
        ];
    }

    public function messages(): array
    {
        return ['phone.required' => 'Vui lòng nhập số điện thoại.',
            'phone.string' => 'Số điện thoại không hợp lệ.', 'phone.max' => 'Số điện thoại không hợp lệ.',
            'license_plate.required' => 'Vui lòng nhập biển số xe.',
            'license_plate.string' => 'Biển số xe không hợp lệ.', 'license_plate.max' => 'Biển số xe không hợp lệ.',
            'website.*' => 'Không thể tra cứu. Vui lòng kiểm tra thông tin.'];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (array_diff(array_keys($this->all()), ['phone', 'license_plate', 'website', '_token'])) {
                $validator->errors()->add('lookup', 'Không thể tra cứu. Vui lòng kiểm tra thông tin.');
            }
        });
    }

    protected function failedValidation(Validator $validator): void
    {
        // Render POST directly: preserve bounded editable values without flash/session/URL PII.
        throw new HttpResponseException(app(RegistrationLookupController::class)
            ->page($this, errors: $validator->errors()->messages(), status: 422));
    }
}
