<?php

namespace App\Http\Requests\Admin;

use App\Models\CallAuditBatch;
use App\Models\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class CallAuditClaimRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user() ? User::select(['id', 'role', 'status'])->find($this->user()->id) : null;

        return $actor?->can('viewAny', CallAuditBatch::class) ?? false;
    }

    public function rules(): array
    {
        $rules = [
            'purpose' => ['required', Rule::in([CallAuditBatch::PURPOSE_GENERAL, CallAuditBatch::PURPOSE_AWARD])],
            'row_version' => ['required', 'integer', 'min:1', 'max:4294967294'],
            'idempotency_key' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{1,64}$/D'],
        ];
        if ($this->routeIs('admin.call-audits.release', 'admin.call-audits.record')) {
            $rules['claim_token'] = ['required', 'string', 'regex:/^[a-f0-9]{64}$/D'];
        }
        if ($this->routeIs('admin.call-audits.record')) {
            $rules += [
                'outcome' => ['required', Rule::in(['correct', 'wrong', 'no_answer'])],
                'answer_1' => ['present', 'nullable', 'boolean'],
                'answer_2' => ['present', 'nullable', 'boolean'],
                'answer_3' => ['present', 'nullable', 'boolean'],
                'note' => ['nullable', 'string', 'max:2000'],
            ];
        }

        return $rules;
    }

    protected function prepareForValidation(): void
    {
        if ($this->query->count() || array_diff(array_keys($this->validationData()), array_keys($this->rules()))) {
            $this->reject();
        }
    }

    public function validationData(): array
    {
        return array_diff_key($this->isJson() ? $this->json()->all() : $this->request->all(), ['_token' => true]);
    }

    protected function failedValidation(Validator $validator): void
    {
        $this->reject();
    }

    private function reject(): never
    {
        // No input echo, session flashing or redirect containing a token/PII.
        throw new HttpResponseException(response()->json(['error' => 'invalid_call_command'], 422)
            ->header('Cache-Control', 'no-store, private'));
    }
}
