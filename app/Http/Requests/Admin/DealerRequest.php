<?php

namespace App\Http\Requests\Admin;

use App\Models\Dealer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DealerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $dealer = $this->route('dealer');

        return $dealer ? $this->user()->can('update', $dealer) : $this->user()->can('create', Dealer::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', Rule::unique('dealers', 'code')->ignore($this->route('dealer'))],
            'province' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'tên đại lý', 'code' => 'mã đại lý', 'province' => 'tỉnh/thành phố', 'phone' => 'điện thoại', 'address' => 'địa chỉ', 'is_active' => 'trạng thái'];
    }

    public function messages(): array
    {
        return [
            'required' => 'Vui lòng nhập :attribute.',
            'string' => ':attribute phải là chuỗi ký tự.',
            'max' => ':attribute không được vượt quá :max ký tự.',
            'code.unique' => 'Mã đại lý này đã tồn tại.',
            'is_active.boolean' => 'Trạng thái đại lý không hợp lệ.',
        ];
    }
}
