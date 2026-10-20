<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
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
            'phone' => $this->user()->role === UserRole::Sales ? ['nullable', 'string', 'max:30'] : ['missing'],
            'avatar' => $this->user()->role === UserRole::Sales
                ? SalesAvatars::rules() : ['missing'],
            'public_slug' => ['missing'],
            'avatar_path' => ['missing'],
            'email' => ['missing'], 'dealer_id' => ['missing'], 'role' => ['missing'], 'status' => ['missing'],
            'password' => ['missing'], 'user_id' => ['missing'], 'id' => ['missing'],
        ];
    }
}
