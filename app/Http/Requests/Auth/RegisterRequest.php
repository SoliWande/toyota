<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
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
            'password' => ['required', 'string', 'max:255', 'confirmed', Password::min(8)],
            'dealer_id' => ['required', 'integer', Rule::exists('dealers', 'id')->where('is_active', true)],
            'role' => ['prohibited'],
            'status' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Vui lòng nhập họ tên.',
            'name.string' => 'Họ tên không hợp lệ.',
            'name.max' => 'Họ tên không được vượt quá 255 ký tự.',
            'email.required' => 'Vui lòng nhập email.',
            'email.string' => 'Email không hợp lệ.',
            'email.email' => 'Email không hợp lệ.',
            'email.max' => 'Email không được vượt quá 255 ký tự.',
            'email.unique' => 'Email này đã được đăng ký.',
            'password.required' => 'Vui lòng nhập mật khẩu.',
            'password.string' => 'Mật khẩu không hợp lệ.',
            'password.min' => 'Mật khẩu cần ít nhất 8 ký tự.',
            'password.max' => 'Mật khẩu không được vượt quá 255 ký tự.',
            'password.confirmed' => 'Mật khẩu xác nhận không khớp.',
            'dealer_id.required' => 'Vui lòng chọn đại lý.',
            'dealer_id.integer' => 'Đại lý không hợp lệ.',
            'dealer_id.exists' => 'Đại lý không tồn tại hoặc đang ngừng nhận đăng ký.',
            'role.prohibited' => 'Không được tự chọn vai trò tài khoản.',
            'status.prohibited' => 'Không được tự chọn trạng thái tài khoản.',
        ];
    }
}
