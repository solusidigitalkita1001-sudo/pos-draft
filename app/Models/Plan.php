<?php

namespace App\Models;

use App\Enums\PlanCode;
use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'code',
    'name',
    'max_stores',
    'max_owners',
    'price_monthly',
    'price_yearly',
    'is_custom',
    'is_active',
    'sort_order',
    'features',
])]
class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory;

    /**
     * Get all subscriptions on this plan.
     *
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Find an active plan by its code. Convenience helper used by
     * onboarding/checkout flows.
     */
    public static function findByCode(PlanCode $code): ?self
    {
        return static::query()
            ->where('code', $code->value)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'max_stores' => 'integer',
            'max_owners' => 'integer',
            'price_monthly' => 'decimal:2',
            'price_yearly' => 'decimal:2',
            'is_custom' => 'boolean',
            'is_active' => 'boolean',
            'features' => 'array',
        ];
    }
}
