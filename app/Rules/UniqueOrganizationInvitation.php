<?php

namespace App\Rules;

use App\Models\Organization;
use App\Models\OrganizationInvitation;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class UniqueOrganizationInvitation implements ValidationRule
{
    public function __construct(protected Organization $organization)
    {
        //
    }

    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $email = strtolower($value);

        $isMember = $this->organization->members()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->exists();

        if ($isMember) {
            $fail(__('User ini sudah menjadi anggota organization.'));

            return;
        }

        $hasPendingInvitation = OrganizationInvitation::where('organization_id', $this->organization->id)
            ->whereRaw('LOWER(email) = ?', [$email])
            ->whereNull('accepted_at')
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->exists();

        if ($hasPendingInvitation) {
            $fail(__('Undangan untuk email ini sudah pernah dikirim.'));
        }
    }
}
