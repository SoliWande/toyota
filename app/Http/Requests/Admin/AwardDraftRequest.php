<?php

namespace App\Http\Requests\Admin;

use App\Enums\AwardPeriod;
use App\Models\Award;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AwardDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Award::class);
    }

    public function rules(): array
    {
        return [
            'period_type' => ['required', Rule::enum(AwardPeriod::class)],
            'period_date' => ['required', 'date_format:Y-m-d'],
            'title' => ['nullable', 'string', 'max:255'],
        ];
    }
}
