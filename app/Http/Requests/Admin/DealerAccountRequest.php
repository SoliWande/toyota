<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class DealerAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('dealer'));
    }

    public function rules(): array
    {
        $creating = $this->isMethod('post');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => $creating ? ['required', 'email', 'max:255', Rule::unique('users', 'email')] : ['missing'],
            // Only an administrator-supplied password; never seed or generate a shared default.
            'password' => $creating ? ['required', 'string', 'max:255', 'confirmed', Password::min(12)] : ['missing'],
            'status' => ['required', Rule::in([UserStatus::Active->value, UserStatus::Blocked->value])],
            'role' => ['missing'], 'dealer_id' => ['missing'], 'id' => ['missing'], 'user_id' => ['missing'],
        ];
    }
}
