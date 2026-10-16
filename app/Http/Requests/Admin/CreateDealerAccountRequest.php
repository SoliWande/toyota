<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class CreateDealerAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', User::class);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['bail', 'required', 'string', 'not_regex:/[\x00-\x1F\x7F]/', 'email', 'max:255', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'string', 'max:30'],
            'dealer_id' => ['required', 'integer', Rule::exists('dealers', 'id')->where('is_active', true)],
            'password' => ['required', 'string', 'max:255', 'confirmed', Password::min(8)],
            'role' => ['missing'], 'status' => ['missing'], 'id' => ['missing'], 'user_id' => ['missing'],
            'reviewed_by' => ['missing'], 'reviewed_at' => ['missing'], 'email_verified_at' => ['missing'],
            'admin_note' => ['missing'], 'rejection_reason' => ['missing'], 'created_at' => ['missing'],
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'tên tài khoản', 'phone' => 'số điện thoại', 'dealer_id' => 'đại lý', 'password' => 'mật khẩu'];
    }

    public function messages(): array
    {
        return [
            'required' => 'Vui lòng nhập :attribute.',
            'string' => ':attribute phải là chuỗi ký tự.',
            'max' => ':attribute vượt quá độ dài cho phép.',
            'missing' => 'Không được tự thiết lập trường :attribute.',
            'email.email' => 'Email không hợp lệ.',
            'email.unique' => 'Email này đã được đăng ký.',
            'dealer_id.exists' => 'Đại lý không tồn tại hoặc đang ngừng hoạt động.',
            'dealer_id.integer' => 'Đại lý không hợp lệ.',
            'password.confirmed' => 'Mật khẩu xác nhận không khớp.',
            'password.min' => 'Mật khẩu phải có ít nhất 8 ký tự.',
        ];
    }
}
