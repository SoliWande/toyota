<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReviewAccountRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(in_array($this->route('action'), ['approve', 'reject'], true) ? 'reviewRegistration' : 'manageStaff', $this->route('account'));
    }

    public function rules(): array
    {
        return ['rejection_reason' => ['nullable', 'string', 'max:2000'], 'role' => ['prohibited'], 'status' => ['prohibited'],
            'dealer_id' => ['missing'], 'email' => ['missing'], 'password' => ['missing'], 'admin_note' => ['missing'], 'reviewed_by' => ['missing'], 'reviewed_at' => ['missing']];
    }
}
