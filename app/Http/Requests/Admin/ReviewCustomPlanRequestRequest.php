<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\In;

class ReviewCustomPlanRequestRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', new In(['approve', 'reject'])],
            'approved_max_stores' => ['required_if:decision,approve', 'nullable', 'integer', 'min:1', 'max:1000'],
            'approved_max_owners' => ['required_if:decision,approve', 'nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
