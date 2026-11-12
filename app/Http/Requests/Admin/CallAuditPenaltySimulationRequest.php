<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class CallAuditPenaltySimulationRequest extends CallAuditReadRequest
{
    public function rules(): array
    {
        return ['month' => ['required', 'string', 'date_format:Y-m']];
    }

    protected function prepareForValidation(): void
    {
        if (array_diff(array_keys($this->query->all()), ['month'])) {
            $this->reject();
        }
    }

    protected function failedValidation(Validator $validator): void
    {
        $this->reject();
    }

    private function reject(): never
    {
        throw new HttpResponseException(response()->json(['error' => 'invalid_simulation_filters'], 422)
            ->header('Cache-Control', 'no-store, private'));
    }
}
