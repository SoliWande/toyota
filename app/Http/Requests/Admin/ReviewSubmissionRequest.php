<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ReviewSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('review', $this->route('submission'));
    }

    public function rules(): array
    {
        return [
            ...SubmissionIndexRequest::createFrom($this)->rules(),
            'admin_note' => ['nullable', 'string', 'max:2000'],
            'rejection_reason' => ['nullable', 'string', 'max:2000'],
            'return_to_queue' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'admin_note.string' => 'Ghi chú admin phải là chuỗi ký tự.',
            'admin_note.max' => 'Ghi chú admin tối đa 2000 ký tự.',
            'rejection_reason.string' => 'Lý do từ chối phải là chuỗi ký tự.',
            'rejection_reason.max' => 'Lý do từ chối tối đa 2000 ký tự.',
        ];
    }
}
