<?php

namespace App\Http\Requests\Dealer;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SalesIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->role === UserRole::Dealer && $this->user()->status === UserStatus::Active && $this->user()->dealer_id !== null;
    }

    public function rules(): array
    {
        return ['q' => ['nullable', 'string', 'max:255'], 'status' => ['nullable', Rule::enum(UserStatus::class)],
            'page' => ['nullable', 'integer', 'min:1'], 'dealer_id' => ['missing']];
    }
}
