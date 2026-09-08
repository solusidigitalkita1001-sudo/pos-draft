<?php

namespace App\Http\Requests\Organizations;

use App\Enums\OrganizationRole;
use App\Rules\UniqueOrganizationInvitation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CreateOrganizationInvitationRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'role' => ['required', 'string', Rule::enum(OrganizationRole::class)],
        ];
    }

    /**
     * The organization isn't bound via the route (same pattern as the
     * rest of organizations.* — always resolved from the current user),
     * so the uniqueness check is added here once we know it.
     */
    public function withValidator(Validator $validator): void
    {
        $organization = $this->user()?->currentOrganization;

        if (! $organization) {
            return;
        }

        $validator->addRules([
            'email' => ['required', 'string', 'email', 'max:255', new UniqueOrganizationInvitation($organization)],
        ]);
    }
}
