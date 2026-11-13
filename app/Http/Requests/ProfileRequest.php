<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Services\RegistrationPhone;
use App\Services\SalesAvatars;
use Illuminate\Foundation\Http\FormRequest;

class ProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => match ($this->user()->role) {
                UserRole::Dealer => ['bail', 'nullable', 'string', 'max:30', 'regex:/\A\+?[0-9]{9,15}\z/'],
                UserRole::Sales => ['nullable', 'string', 'max:30'],
                default => ['missing'],
            },
            'avatar' => in_array($this->user()->role, [UserRole::Sales, UserRole::Dealer], true)
                ? [...SalesAvatars::rules(), ...($this->user()->role === UserRole::Dealer ? ['extensions:jpg,jpeg,png,webp'] : [])] : ['missing'],
            'public_slug' => ['missing'],
            'avatar_path' => ['missing'],
            'email' => ['missing'], 'dealer_id' => ['missing'], 'role' => ['missing'], 'status' => ['missing'],
            'password' => ['missing'], 'user_id' => ['missing'], 'id' => ['missing'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->user()?->role === UserRole::Dealer && is_string($this->input('phone'))) {
            $this->merge(['phone' => RegistrationPhone::normalize(trim($this->input('phone')))]);
        }
    }
}
