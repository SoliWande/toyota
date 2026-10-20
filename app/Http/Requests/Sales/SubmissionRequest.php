<?php

namespace App\Http\Requests\Sales;

use App\Models\CustomerSubmission;
use App\Services\AdministrativeDivisions;
use App\Services\FacebookUrlNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class SubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $submission = $this->route('submission');

        return $submission ? $this->user()->can('update', $submission) : $this->user()->can('create', CustomerSubmission::class);
    }

    public function rules(): array
    {
        $directory = app(AdministrativeDivisions::class);

        return [
            'customer_name' => ['required', 'string', 'max:255'],
            'facebook_url' => ['bail', 'required', 'string', 'max:2048', function ($attribute, $value, $fail) {
                try {
                    app(FacebookUrlNormalizer::class)->normalize($value);
                } catch (InvalidArgumentException) {
                    $fail('Vui lòng nhập URL trang cá nhân Facebook hợp lệ.');
                }
            }],
            'customer_phone' => ['nullable', 'string', 'max:30'],
            'note' => ['nullable', 'string', 'max:2000'],
            'address_line' => ['nullable', 'string', 'max:500'],
            'province_code' => ['bail', 'nullable', 'required_with:ward_code,address_line', 'string', 'regex:/^[0-9]{2}$/D',
                Rule::in(array_column($directory->provinces(), 'code'))],
            'ward_code' => ['bail', 'nullable', 'required_with:province_code,address_line', 'string', 'regex:/^[0-9]{5}$/D',
                function ($attribute, $value, $fail) use ($directory) {
                    $province = $this->input('province_code');
                    if (! is_string($province) || $directory->ward($province, $value) === null) {
                        $fail('Phường/Xã không thuộc Tỉnh/Thành đã chọn.');
                    }
                }],
            'province_name' => ['prohibited'], 'ward_name' => ['prohibited'],
            'vehicle_model' => ['required', 'string', Rule::in(array_keys(config('vehicles.toyota_models')))],
            'first_registration_year' => ['required', 'integer', 'digits:4', 'between:1900,'.now(config('app.timezone'))->year],
            'vehicle_color' => ['required', 'string', 'max:50'],
            'license_plate' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9][A-Z0-9.\-]{2,19}$/D'],
            'evidence_image' => [
                'bail',
                $this->route('submission')?->evidence_image_path ? 'nullable' : 'required',
                'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=8000,max_height=8000',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('license_plate'))) {
            $this->merge(['license_plate' => strtoupper(preg_replace('/\s+/u', '', $this->input('license_plate')))]);
        }
    }

    public function submissionData(): array
    {
        $data = $this->validated();
        $address = [];
        // Older clients omitting the entire address must not erase an existing one.
        if ($this->hasAny(['address_line', 'province_code', 'ward_code'])) {
            $directory = app(AdministrativeDivisions::class);
            $provinceCode = $data['province_code'] ?? null;
            $wardCode = $data['ward_code'] ?? null;
            $address = [
                'address_line' => $data['address_line'] ?? null,
                'province_code' => $provinceCode,
                'province_name' => $provinceCode ? $directory->province($provinceCode)['name'] : null,
                'ward_code' => $wardCode,
                'ward_name' => $provinceCode && $wardCode ? $directory->ward($provinceCode, $wardCode)['name'] : null,
            ];
        }

        return [
            'customer_name' => $data['customer_name'],
            'facebook_url' => $data['facebook_url'],
            'phone' => $data['customer_phone'] ?? null,
            'notes' => $data['note'] ?? null,
            'vehicle_model' => $data['vehicle_model'],
            'first_registration_year' => (int) $data['first_registration_year'],
            'vehicle_color' => $data['vehicle_color'],
            'license_plate' => $data['license_plate'],
        ] + $address;
    }

    public function attributes(): array
    {
        return [
            'address_line' => 'địa chỉ chi tiết', 'province_code' => 'Tỉnh/Thành', 'ward_code' => 'Phường/Xã',
            'province_name' => 'tên Tỉnh/Thành', 'ward_name' => 'tên Phường/Xã',
            'customer_name' => 'họ tên khách hàng', 'facebook_url' => 'URL Facebook', 'customer_phone' => 'số điện thoại', 'note' => 'ghi chú',
            'vehicle_model' => 'dòng xe Toyota', 'first_registration_year' => 'năm đăng ký lần đầu',
            'vehicle_color' => 'màu xe', 'license_plate' => 'biển số xe', 'evidence_image' => 'ảnh bằng chứng',
        ];
    }

    public function messages(): array
    {
        return [
            'province_code.in' => 'Vui lòng chọn Tỉnh/Thành trong danh sách.',
            'province_code.regex' => 'Tỉnh/Thành không hợp lệ.',
            'ward_code.regex' => 'Phường/Xã không hợp lệ.',
            'required_with' => 'Vui lòng chọn :attribute khi bổ sung địa chỉ.',
            'prohibited' => 'Không được gửi :attribute.',
            'required' => 'Vui lòng nhập :attribute.', 'string' => ':attribute phải là chuỗi ký tự.', 'max' => ':attribute không được vượt quá :max ký tự.',
            'vehicle_model.in' => 'Vui lòng chọn một dòng xe Toyota trong danh sách.',
            'first_registration_year.integer' => 'Năm đăng ký lần đầu phải là năm gồm 4 chữ số.',
            'first_registration_year.digits' => 'Năm đăng ký lần đầu phải gồm 4 chữ số.',
            'first_registration_year.between' => 'Năm đăng ký lần đầu phải từ 1900 đến năm hiện tại.',
            'license_plate.regex' => 'Biển số chỉ gồm chữ, số, dấu chấm và dấu gạch ngang.',
            'evidence_image.required' => 'Vui lòng tải lên 1 ảnh bằng chứng.',
            'evidence_image.file' => 'Vui lòng tải lên đúng 1 file ảnh.',
            'evidence_image.image' => 'Ảnh bằng chứng phải là file ảnh hợp lệ.',
            'evidence_image.mimes' => 'Ảnh bằng chứng phải có định dạng JPG, PNG hoặc WebP.',
            'evidence_image.max' => 'Ảnh bằng chứng không được vượt quá 5 MB.',
            'evidence_image.dimensions' => 'Kích thước ảnh không được vượt quá 8000 × 8000 pixel.',
        ];
    }
}
