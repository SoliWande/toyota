<?php

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Rule;

/** C02 GET only. Mutation requests retain their original validation contract. */
class SubmissionQueueRequest extends SubmissionIndexRequest
{
    public function rules(): array
    {
        return parent::rules() + [
            'workflow' => ['nullable', Rule::in(['pending', 'supplement', 'group', 'approved', 'rejected'])],
            'selected' => ['nullable', 'integer', 'min:1'],
            // The stored sales source cannot distinguish manual intake from public Sales links.
            'source' => ['nullable', Rule::in(['dealer_qr'])],
            'flagged' => ['prohibited'],
        ];
    }
}
