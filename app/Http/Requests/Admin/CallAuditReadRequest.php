<?php

namespace App\Http\Requests\Admin;

use App\Models\CallAuditBatch;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CallAuditReadRequest extends FormRequest
{
    public function authorize(): bool
    {
        $current = $this->user() ? User::select(['id', 'role', 'status'])->find($this->user()->id) : null;

        return $current?->can('viewAny', CallAuditBatch::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'purpose' => ['required', 'string', Rule::in([CallAuditBatch::PURPOSE_GENERAL, CallAuditBatch::PURPOSE_AWARD])],
            'month' => ['nullable', 'string', 'date_format:Y-m'],
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'tab' => ['nullable', 'string', Rule::in(['needs_call', 'recall', 'completed'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        $allowed = ['purpose', 'month'];
        if ($this->routeIs('admin.call-audits.index', 'admin.call-audits.items')) {
            array_push($allowed, 'page', 'per_page');
        }
        if ($this->routeIs('admin.call-audits.items')) {
            $allowed[] = 'tab';
        }
        if (array_diff(array_keys($this->query->all()), $allowed)) {
            // Do not echo arbitrary keys/values, including accidentally supplied PII.
            throw ValidationException::withMessages(['filters' => [__('validation.prohibited', ['attribute' => 'filters'])]]);
        }
    }

    public function validationData(): array
    {
        return $this->query->all();
    }
}
