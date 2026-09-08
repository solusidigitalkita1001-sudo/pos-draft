<?php

namespace App\Models;

use App\Enums\BillingPeriod;
use App\Enums\InvoiceStatus;
use Database\Factories\SubscriptionInvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organization_id',
    'subscription_id',
    'plan_id',
    'order_id',
    'amount',
    'billing_period',
    'status',
    'snap_token',
    'paid_at',
    'expires_at',
])]
class SubscriptionInvoice extends Model
{
    /** @use HasFactory<SubscriptionInvoiceFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * Get the route key for the model — invoices are looked up by their
     * order_id (the reference shared with Midtrans), never by raw id.
     */
    public function getRouteKeyName(): string
    {
        return 'order_id';
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'billing_period' => BillingPeriod::class,
            'status' => InvoiceStatus::class,
            'paid_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}
