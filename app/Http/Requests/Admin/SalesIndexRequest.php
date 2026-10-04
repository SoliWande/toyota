<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SalesIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', \App\Models\User::class);
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::enum(UserStatus::class)],
            'dealer_id' => ['nullable', 'integer', Rule::exists('dealers', 'id')],
            'q' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
