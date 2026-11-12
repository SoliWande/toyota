<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class CallAuditImpactPreviewRequest extends CallAuditReadRequest
{
    public function rules(): array
    {
        $rules = array_diff_key(parent::rules(), ['tab' => true, 'page' => true, 'per_page' => true]);
        $rules['month'] = ['required', 'string', 'date_format:Y-m'];
        if ($this->routeIs('admin.call-audits.impact.index')) {
            $rules['purpose'][0] = 'nullable';
        }

        return $rules;
    }

    protected function prepareForValidation(): void
    {
        if (array_diff(array_keys($this->query->all()), ['purpose', 'month'])) {
            $this->reject();
        }
    }

    protected function failedValidation(Validator $validator): void
    {
        $this->reject();
    }

    private function reject(): never
    {
        throw new HttpResponseException(response()->json(['error' => 'invalid_impact_filters'], 422)
            ->header('Cache-Control', 'no-store, private'));
    }
}
