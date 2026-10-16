<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class PublishAwardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('publish', $this->route('award'));
    }

    public function rules(): array
    {
        return ['preview_hash' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/D', function ($attribute, $value, $fail) {
            $seen = $this->session()->get('awards.preview.'.$this->route('award')->id);
            if (! is_string($seen) || ! hash_equals($seen, $value)) {
                $fail('Vui lòng xem Preview trước khi công bố.');
            }
        }]];
    }
}
