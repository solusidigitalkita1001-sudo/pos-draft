<?php

namespace App\Http\Requests\Organizations;

use App\Enums\PlanCode;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpgradeOrganizationPlanRequest extends FormRequest
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
                // Plan "custom" is negotiated manually (contact sales),
                // not a self-service switch.
                function (string $attribute, mixed $value, \Closure $fail) {
                    if ($value === PlanCode::Custom->value) {
                        $fail(__('Paket Custom perlu dikoordinasikan lewat tim sales, tidak bisa upgrade otomatis.'));
                    }
                },
            ],
        ];
    }
}
