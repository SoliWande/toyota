<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SalesIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', User::class);
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::enum(UserStatus::class)],
            'dealer_id' => ['nullable', 'integer', Rule::exists('dealers', 'id')],
            'q' => ['nullable', 'string', 'max:255'],
            // Manager scope and high-rejection cohort require separate contracts.
            'role' => ['nullable', Rule::in(['sales'])],
            'lock' => ['nullable', 'integer', 'min:1'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
