<?php

namespace App\Http\Requests\Admin;

use App\Services\AdministrativeDivisions;
use App\Services\RegistrationPhone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSubmissionRequest extends FormRequest
{
    public function validationData(): array
    {
        // Queue filters are query parameters, never editable system fields in the PATCH body.
        return $this->request->all();
    }

    public function authorize(): bool
    {
        return $this->user()->can('review', $this->route('submission'));
    }

    public function rules(): array
    {
        $directory = app(AdministrativeDivisions::class);
        $qr = $this->route('submission')->source === 'dealer_qr';

        return [
            'customer_name' => ['required', 'string', 'max:255'],
            'phone' => [$qr ? 'required' : 'nullable', 'string', 'regex:/^0[0-9]{9,10}$/D'],
            'address_line' => [$qr ? 'required' : 'nullable', 'string', 'max:500'],
            'province_code' => ['bail', $qr ? 'required' : 'nullable', 'required_with:ward_code,address_line', 'string', Rule::in(array_column($directory->provinces(), 'code'))],
            'ward_code' => ['bail', $qr ? 'required' : 'nullable', 'required_with:province_code,address_line', 'string', function ($attribute, $value, $fail) use ($directory) {
                if (! is_string($this->input('province_code')) || $directory->ward($this->input('province_code'), $value) === null) {
                    $fail('Phường/Xã không thuộc Tỉnh/Thành đã chọn.');
                }
            }],
            'vehicle_model' => [$qr ? 'prohibited' : 'required', 'string', Rule::in(array_keys(config('vehicles.toyota_models')))],
            'vehicle_description' => [$qr ? 'required' : 'prohibited', 'string', 'max:255'],
            'dealer_id' => ['nullable', 'integer', Rule::exists('dealers', 'id')],
            'sales_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('role', 'sales')],
            'edit_version' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/D'],
            'edit_submission_id' => ['required', 'string', Rule::in([(string) $this->route('submission')->id])],
            ...array_fill_keys(['status', 'source', 'submitted_at', 'reviewed_at', 'reviewed_by', 'admin_note', 'rejection_reason',
                'facebook_url', 'facebook_url_normalized', 'approved_facebook_identity', 'evidence_image_path', 'phone_normalized',
                'province_name', 'ward_name', 'consented_at', 'consent_version', 'points'], ['prohibited']),
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->request->get('phone'))) {
            $this->merge(['phone' => RegistrationPhone::normalize($this->request->get('phone'))]);
        }
    }

    protected function getRedirectUrl(): string
    {
        $validator = validator($this->query->all(), (new SubmissionIndexRequest)->rules());
        $query = $validator->passes() ? $validator->validated() : ['status' => 'pending'];
        $query['status'] = $query['status'] ?? '';

        return route('admin.submissions.index', $query).'#submission-'.$this->route('submission')->id;
    }
}
