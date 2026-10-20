<?php

namespace App\Http\Requests\Dealer;

use App\Enums\SubmissionStatus;
use Illuminate\Validation\Rule;

class CustomerIndexRequest extends SalesIndexRequest
{
    public function rules(): array
    {
        return [...parent::rules(), 'status' => ['nullable', Rule::enum(SubmissionStatus::class)]];
    }
}
