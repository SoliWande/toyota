<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class CallAuditFindingResolutionRequest extends CallAuditReadRequest
{
    public function rules(): array
    {
        $rules = array_diff_key(parent::rules(), ['tab' => true]);
        if ($this->isMethod('POST')) {
            unset($rules['page'], $rules['per_page']);
            $rules += [
                'attempt_id' => ['required', 'integer', 'min:1', 'max:4294967294'],
                'row_version' => ['required', 'integer', 'min:1', 'max:4294967294'],
                'resolution_version' => ['required', 'integer', 'min:0', 'max:4294967294'],
                'decision' => ['required', 'string', Rule::in(['confirmed', 'dismissed', 'needs_review'])],
                'reason' => ['required', 'string', 'max:2000'],
                'idempotency_key' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{1,64}$/D'],
            ];
        } elseif (! $this->routeIs('admin.call-audits.resolutions.history')) {
            unset($rules['page'], $rules['per_page']);
        }

        return $rules;
    }

    public function validationData(): array
    {
        return $this->isMethod('POST')
            ? array_diff_key($this->isJson() ? $this->json()->all() : $this->request->all(), ['_token' => true])
            : $this->query->all();
    }

    protected function prepareForValidation(): void
    {
        if (($this->isMethod('POST') && $this->query->count())
            || array_diff(array_keys($this->validationData()), array_keys($this->rules()))) {
            $this->reject();
        }
    }

    protected function failedValidation(Validator $validator): void
    {
        $this->reject();
    }

    private function reject(): never
    {
        throw new HttpResponseException(response()->json(['error' => 'invalid_resolution_command'], 422)
            ->header('Cache-Control', 'no-store, private'));
    }
}
