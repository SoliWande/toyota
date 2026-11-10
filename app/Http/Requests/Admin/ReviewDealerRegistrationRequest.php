<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\ReviewAccountRegistrationRequest;

class ReviewDealerRegistrationRequest extends ReviewAccountRegistrationRequest
{
    protected function prepareForValidation(): void
    {
        $reason = $this->input('rejection_reason');
        $note = $this->input('rejection_note');
        // Both fields are explicitly public in C06. The existing action stores one public reason.
        if ($this->route('action') === 'reject' && ($reason === null || is_string($reason)) && is_string($note) && trim($note) !== '') {
            $reason = trim($reason ?? '');
            $this->merge(['rejection_reason' => ($reason !== '' ? $reason."\n" : '').trim($note)]);
        }
    }

    public function rules(): array
    {
        return parent::rules() + ['rejection_note' => ['nullable', 'string', 'max:2000']];
    }
}
