<?php

namespace Database\Factories;

use App\Enums\BillingPeriod;
use App\Enums\InvoiceStatus;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\SubscriptionInvoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubscriptionInvoice>
 */
class SubscriptionInvoiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'plan_id' => Plan::factory(),
            'order_id' => 'SUB-'.now()->format('Ymd').'-'.str_pad((string) fake()->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'amount' => 150000,
            'billing_period' => BillingPeriod::Monthly,
            'status' => InvoiceStatus::Pending,
            'expires_at' => now()->addHours(24),
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => InvoiceStatus::Paid,
            'paid_at' => now(),
        ]);
    }
}
