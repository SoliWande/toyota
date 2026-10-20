<?php

namespace App\Http\Requests\Admin;

use App\Services\SalesAvatars;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSalesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('sales'));
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'dealer_id' => ['required', 'integer', Rule::exists('dealers', 'id')],
            'avatar' => SalesAvatars::rules(),
            'avatar_path' => ['missing'], 'public_slug' => ['missing'],
            'email' => ['missing'], 'role' => ['missing'], 'status' => ['missing'], 'password' => ['missing'],
        ];
    }
}
