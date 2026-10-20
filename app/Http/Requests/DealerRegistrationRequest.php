<?php

namespace App\Http\Requests;

use App\Services\AdministrativeDivisions;
use App\Services\RegistrationPhone;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class DealerRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $directory = app(AdministrativeDivisions::class);

        return [
            'customer_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'regex:/^0[1-9][0-9]{8,9}$/D'],
            'address_line' => ['required', 'string', 'max:500'],
            'province_code' => ['bail', 'required', 'string', Rule::in(array_column($directory->provinces(), 'code'))],
            'ward_code' => ['bail', 'required', 'string', function ($attribute, $value, $fail) use ($directory) {
                $province = $this->input('province_code');
                if (! is_string($province) || $directory->ward($province, $value) === null) {
                    $fail('Phường/Xã không thuộc Tỉnh/Thành đã chọn.');
                }
            }],
            'vehicle_description' => ['required', 'string', 'max:255'],
            'consent' => ['required', 'accepted'],
            'website' => ['nullable', 'string', 'max:0'],
            ...array_fill_keys(['dealer_id', 'sales_id', 'source', 'status', 'reviewed_by', 'reviewed_at', 'points',
                'facebook_url', 'facebook_url_normalized', 'approved_facebook_identity', 'evidence_image_path',
                'consented_at', 'consent_version', 'province_name', 'ward_name'], ['prohibited']),
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('phone'))) {
            $this->merge(['phone' => RegistrationPhone::normalize($this->input('phone'))]);
        }
    }

    protected function failedValidation(Validator $validator): void
    {
        // Field errors contain no rejected values; never flash public personal data to other pages.
        $errors = array_map(fn ($messages) => ['Vui lòng kiểm tra lại thông tin của trường này.'], $validator->errors()->messages());
        $response = $this->expectsJson()
            ? response()->json(['message' => 'Thông tin đăng ký chưa hợp lệ.', 'errors' => $errors], 422)
            : redirect()->to(route('dealers.profile', ['slug' => $this->route('slug')]).'#registration')->withErrors($errors);
        $response->headers->set('Cache-Control', 'no-store, private');
        throw new HttpResponseException($response);
    }
}
