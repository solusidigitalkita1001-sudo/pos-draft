<?php

namespace App\Http\Requests\Organizations;

use App\Enums\BillingPeriod;
use App\Enums\PlanCode;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class CreateCheckoutRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'plan_code' => [
                'required',
                new Enum(PlanCode::class),
                function (string $attribute, mixed $value, \Closure $fail) {
                    if ($value === PlanCode::Custom->value) {
                        $fail(__('Paket Custom tidak bisa checkout langsung — ajukan lewat form request custom plan.'));
                    }
                },
            ],
            'billing_period' => ['required', new Enum(BillingPeriod::class)],
        ];
    }
}
