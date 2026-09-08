<?php

namespace App\Concerns;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait HasOrganizations
{
    /**
     * Get all of the organizations the user belongs to (as owner or manager).
     *
     * @return BelongsToMany<Organization, $this>
     */
    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'organization_members', 'user_id', 'organization_id')
            ->withPivot(['role'])
            ->withTimestamps();
    }

    /**
     * Get all of the organization memberships for the user.
     *
     * @return HasMany<OrganizationMembership, $this>
     */
    public function organizationMemberships(): HasMany
    {
        return $this->hasMany(OrganizationMembership::class, 'user_id');
    }

    /**
     * Get the user's current organization.
     *
     * @return BelongsTo<Organization, $this>
     */
    public function currentOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'current_organization_id');
    }

    /**
     * Switch to the given organization.
     */
    public function switchOrganization(Organization $organization): bool
    {
        if (! $this->belongsToOrganization($organization)) {
            return false;
        }

        $this->update(['current_organization_id' => $organization->id]);
        $this->setRelation('currentOrganization', $organization);

        return true;
    }

    /**
     * Determine if the user belongs to the given organization.
     */
    public function belongsToOrganization(Organization $organization): bool
    {
        return $this->organizations()->where('organizations.id', $organization->id)->exists();
    }

    /**
     * Determine if the user is an owner of the given organization.
     */
    public function ownsOrganization(Organization $organization): bool
    {
        return $this->organizationRole($organization) === OrganizationRole::Owner;
    }

    /**
     * Get the user's role on the given organization.
     */
    public function organizationRole(Organization $organization): ?OrganizationRole
    {
        return $this->organizationMemberships()
            ->where('organization_id', $organization->id)
            ->first()
            ?->role;
    }

    /**
     * Get a fallback organization for the user (used when their current
     * organization becomes unavailable).
     */
    public function fallbackOrganization(?Organization $excluding = null): ?Organization
    {
        return $this->organizations()
            ->when($excluding, fn ($query) => $query->where('organizations.id', '!=', $excluding->id))
            ->orderByRaw('LOWER(organizations.name)')
            ->first();
    }
}
