<?php

namespace App\Http\Requests\Sales;

use App\Models\CustomerSubmission;
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

        return [
            'customer_name' => $data['customer_name'],
            'facebook_url' => $data['facebook_url'],
            'phone' => $data['customer_phone'] ?? null,
            'notes' => $data['note'] ?? null,
            'vehicle_model' => $data['vehicle_model'],
            'first_registration_year' => (int) $data['first_registration_year'],
            'vehicle_color' => $data['vehicle_color'],
            'license_plate' => $data['license_plate'],
        ];
    }

    public function attributes(): array
    {
        return [
            'customer_name' => 'họ tên khách hàng', 'facebook_url' => 'URL Facebook', 'customer_phone' => 'số điện thoại', 'note' => 'ghi chú',
            'vehicle_model' => 'dòng xe Toyota', 'first_registration_year' => 'năm đăng ký lần đầu',
            'vehicle_color' => 'màu xe', 'license_plate' => 'biển số xe', 'evidence_image' => 'ảnh bằng chứng',
        ];
    }

    public function messages(): array
    {
        return [
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
