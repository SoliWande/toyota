<?php

namespace App\Http\Requests\Admin;

use App\Models\CallAuditBatch;
use Illuminate\Validation\Rule;

class CallAuditWorkspaceRequest extends CallAuditClaimRequest
{
    public function rules(): array
    {
        return ['purpose' => ['required', Rule::in([CallAuditBatch::PURPOSE_GENERAL, CallAuditBatch::PURPOSE_AWARD])],
            'row_version' => ['required', 'integer', 'min:1', 'max:4294967294'],
            'claim_token' => ['sometimes', 'required', 'string', 'regex:/^[a-f0-9]{64}$/D']];
    }
}
