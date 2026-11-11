<?php

namespace App\Http\Requests\Admin;

use App\Models\CustomerSubmission;
use App\Services\RegistrationPhone;
use Illuminate\Foundation\Http\FormRequest;

final class GroupLookupRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->routeIs('admin.group-lookup.search')) {
            // A failed new search must not leave the previous customer's form actionable.
            $this->session()->forget('admin_group_lookup');
        }
    }

    public function authorize(): bool
    {
        return $this->user()->can('review', new CustomerSubmission);
    }

    public function rules(): array
    {
        $rules = ['status' => ['missing'], 'points' => ['missing'], 'action' => ['missing'], 'group_progress' => ['missing']];
        if ($this->routeIs('admin.group-lookup.verify')) {
            return $rules + [
                'submission_id' => ['required', 'integer', 'min:1'],
                'verification_version' => ['required', 'string', 'size:64'],
                'plate_match' => ['accepted'], 'facebook_match' => ['accepted'],
            ];
        }

        return $rules + ['phone' => ['bail', 'required', 'string', 'max:30', function ($attribute, $value, $fail) {
            if (! preg_match('/^0[1-9][0-9]{8,9}$/D', RegistrationPhone::normalize($value))) {
                $fail('Số điện thoại không hợp lệ.');
            }
        }]];
    }
}
