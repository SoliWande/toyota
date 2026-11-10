<?php

namespace App\Http\Requests\Admin;

use App\Models\Dealer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DealerRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        foreach (['facebook_url', 'zalo_url'] as $field) {
            $value = $this->input($field);
            if (is_string($value) && preg_match('/^(?:www\.)?(?:facebook\.com|zalo\.me)\//i', trim($value))) {
                $this->merge([$field => 'https://'.trim($value)]);
            }
        }
    }

    public function authorize(): bool
    {
        $dealer = $this->route('dealer');

        return $dealer ? $this->user()->can('update', $dealer) : $this->user()->can('create', Dealer::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', Rule::unique('dealers', 'code')->ignore($this->route('dealer'))],
            'province' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:2000'],
            'opening_hours' => ['nullable', 'string', 'max:2000'],
            'facebook_url' => ['nullable', 'string', 'url:http,https', 'max:2048'],
            'zalo_url' => ['nullable', 'string', 'url:http,https', 'max:2048'],
            'is_active' => ['required', 'boolean'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=4096,max_height=4096'],
            'photo_path' => ['missing'],
            'slug' => ['missing'],
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'tên đại lý', 'code' => 'mã đại lý', 'province' => 'tỉnh/thành phố', 'phone' => 'điện thoại', 'address' => 'địa chỉ', 'is_active' => 'trạng thái', 'opening_hours' => 'giờ mở cửa', 'facebook_url' => 'link Facebook', 'zalo_url' => 'link Zalo'];
    }

    public function messages(): array
    {
        return [
            'required' => 'Vui lòng nhập :attribute.',
            'string' => ':attribute phải là chuỗi ký tự.',
            'max' => ':attribute không được vượt quá :max ký tự.',
            'code.unique' => 'Mã đại lý này đã tồn tại.',
            'url' => ':attribute phải là đường dẫn HTTP hoặc HTTPS hợp lệ.',
            'is_active.boolean' => 'Trạng thái đại lý không hợp lệ.',
        ];
    }
}
