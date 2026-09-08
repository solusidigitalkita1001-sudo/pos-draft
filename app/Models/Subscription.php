<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use Database\Factories\SubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organization_id',
    'plan_id',
    'pending_plan_id',
    'status',
    'max_stores_override',
    'max_owners_override',
    'trial_ends_at',
    'trial_reminder_sent_at',
    'current_period_start',
    'current_period_end',
    'canceled_at',
])]
class Subscription extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
    use HasFactory;

    /**
     * Get the organization that owns this subscription.
     *
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the plan this subscription is on.
     *
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * Get the plan a pending downgrade will switch to once the current
     * period ends (null if no downgrade is scheduled).
     *
     * @return BelongsTo<Plan, $this>
     */
    public function pendingPlan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'pending_plan_id');
    }

    /**
     * Effective max stores for this subscription. Falls back to the
     * plan's default when there is no per-organization override
     * (overrides exist mainly for the "custom" plan).
     */
    public function effectiveMaxStores(): ?int
    {
        return $this->max_stores_override ?? $this->plan->max_stores;
    }

    /**
     * Effective max owner accounts for this subscription.
     */
    public function effectiveMaxOwners(): ?int
    {
        return $this->max_owners_override ?? $this->plan->max_owners;
    }

    /**
     * Determine if the organization can still use the system on this
     * subscription (not suspended/canceled).
     */
    public function isUsable(): bool
    {
        return $this->status->isUsable();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'max_stores_override' => 'integer',
            'max_owners_override' => 'integer',
            'trial_ends_at' => 'datetime',
            'trial_reminder_sent_at' => 'datetime',
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'canceled_at' => 'datetime',
        ];
    }
}
