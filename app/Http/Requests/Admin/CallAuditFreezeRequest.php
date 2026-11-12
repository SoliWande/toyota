<?php

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Rule;

class CallAuditFreezeRequest extends CallAuditClaimRequest
{
    public function rules(): array
    {
        return ['purpose' => ['required', Rule::in(['general_audit', 'award_top3'])], 'month' => ['required', 'date_format:Y-m'],
            'idempotency_key' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{1,64}$/D'],
            'award_id' => ['nullable', 'integer', 'min:1', 'required_if:purpose,award_top3', 'prohibited_if:purpose,general_audit']];
    }
}
