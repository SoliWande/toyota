<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class CallAuditFindingReadRequest extends CallAuditReadRequest
{
    public function rules(): array
    {
        return array_diff_key(parent::rules(), ['tab' => true]);
    }

    protected function prepareForValidation(): void
    {
        $allowed = ['purpose', 'month'];
        if ($this->routeIs('admin.call-audits.findings.index')) {
            array_push($allowed, 'page', 'per_page');
        }
        if (array_diff(array_keys($this->query->all()), $allowed)) {
            $this->reject();
        }
    }

    protected function failedValidation(Validator $validator): void
    {
        $this->reject();
    }

    private function reject(): never
    {
        // No private input in JSON, redirects, session old input or pagination links.
        throw new HttpResponseException(response()->json(['error' => 'invalid_finding_filters'], 422)
            ->header('Cache-Control', 'no-store, private'));
    }
}
