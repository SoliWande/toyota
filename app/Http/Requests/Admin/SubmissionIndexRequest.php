<?php

namespace App\Http\Requests\Admin;

use App\Enums\SubmissionStatus;
use App\Enums\UserRole;
use App\Models\CustomerSubmission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmissionIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('review', new CustomerSubmission);
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:255'],
            'dealer_id' => ['nullable', 'integer', Rule::exists('dealers', 'id')],
            'sales_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('role', UserRole::Sales->value)],
            'status' => ['nullable', Rule::enum(SubmissionStatus::class)],
            'submitted_from' => ['nullable', 'date_format:Y-m-d'],
            'submitted_to' => ['nullable', 'date_format:Y-m-d', ...($this->filled('submitted_from') ? ['after_or_equal:submitted_from'] : [])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
