<?php

namespace App\Models;

use App\Concerns\GeneratesUniqueOrganizationSlugs;
use App\Enums\OrganizationRole;
use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

#[Fillable(['name', 'slug'])]
class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use GeneratesUniqueOrganizationSlugs, HasFactory, SoftDeletes;

    /**
     * Bootstrap the model and its traits.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Organization $organization) {
            if (empty($organization->slug)) {
                $organization->slug = static::generateUniqueOrganizationSlug($organization->name);
            }
        });
    }

    /**
     * Get all members (owner/manager accounts) of this organization.
     *
     * @return BelongsToMany<User, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organization_members', 'organization_id', 'user_id')
            ->using(OrganizationMembership::class)
            ->withPivot(['role'])
            ->withTimestamps();
    }

    /**
     * Get all memberships for this organization.
     *
     * @return HasMany<OrganizationMembership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(OrganizationMembership::class);
    }

    /**
     * Get all teams (toko) that belong to this organization.
     *
     * @return HasMany<Team, $this>
     */
    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    /**
     * Get all subscriptions (history) for this organization.
     *
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Get the organization's current (most recent) subscription.
     */
    public function currentSubscription(): ?Subscription
    {
        return $this->subscriptions()
            ->latest('id')
            ->first();
    }

    /**
     * Get all custom plan requests submitted by this organization.
     *
     * @return HasMany<CustomPlanRequest, $this>
     */
    public function customPlanRequests(): HasMany
    {
        return $this->hasMany(CustomPlanRequest::class);
    }

    /**
     * Get all subscription invoices for this organization.
     *
     * @return HasMany<SubscriptionInvoice, $this>
     */
    public function subscriptionInvoices(): HasMany
    {
        return $this->hasMany(SubscriptionInvoice::class);
    }

    /**
     * Get all pending/past invitations sent for this organization.
     *
     * @return HasMany<OrganizationInvitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(OrganizationInvitation::class);
    }

    /**
     * Get every owner-role user of this organization — the people who
     * should receive billing/subscription notifications.
     *
     * @return Collection<int, User>
     */
    public function owners(): Collection
    {
        return $this->members()
            ->wherePivot('role', OrganizationRole::Owner->value)
            ->get();
    }

    /**
     * Determine if the given user is an owner of this organization.
     */
    public function ownedBy(User $user): bool
    {
        return $this->memberships()
            ->where('user_id', $user->id)
            ->where('role', OrganizationRole::Owner->value)
            ->exists();
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
