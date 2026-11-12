<?php

namespace App\Http\Requests\Admin;

use App\Models\DealerPenaltyEntry;
use App\Models\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class DealerPenaltyRequest extends CallAuditReadRequest
{
    public function authorize(): bool
    {
        $actor = $this->user() ? User::select(['id', 'role', 'status'])->find($this->user()->id) : null;

        return $actor?->can('applyDealerPenalty', DealerPenaltyEntry::class) ?? false;
    }

    public function rules(): array
    {
        $rules = ['month' => ['required', 'string', 'date_format:Y-m']];
        if ($this->isMethod('POST')) {
            $rules += ['purpose' => ['required', Rule::in(['general_audit', 'award_top3'])],
                'resolution_version' => ['required', 'integer', 'min:1', 'max:2'],
                'idempotency_key' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{1,64}$/D']];
        }

        return $rules;
    }

    public function validationData(): array
    {
        return $this->isMethod('POST') ? array_diff_key($this->isJson() ? $this->json()->all() : $this->request->all(), ['_token' => true]) : $this->query->all();
    }

    protected function prepareForValidation(): void
    {
        if (($this->isMethod('POST') && $this->query->count()) || array_diff(array_keys($this->validationData()), array_keys($this->rules()))) {
            $this->reject();
        }
    }

    protected function failedValidation(Validator $validator): void
    {
        $this->reject();
    }

    private function reject(): never
    {
        throw new HttpResponseException(response()->json(['error' => 'invalid_penalty_command'], 422)->header('Cache-Control', 'no-store, private'));
    }
}
