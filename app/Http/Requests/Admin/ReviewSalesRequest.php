<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ReviewSalesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('moderate', $this->route('sales'));
    }

    public function rules(): array
    {
        return [
            'email' => ['missing'], 'dealer_id' => ['missing'], 'password' => ['missing'],
            'rejection_reason' => ['nullable', 'string', 'max:2000'],
            'admin_note' => ['nullable', 'string', 'max:2000'],
            'status' => ['prohibited'],
            'role' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'rejection_reason.max' => 'Lý do từ chối tối đa 2000 ký tự.',
            'admin_note.max' => 'Ghi chú tối đa 2000 ký tự.',
        ];
    }
}
