<?php

namespace Database\Seeders;

use App\Enums\PlanCode;
use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    /**
     * Seed the 4 SaaS packages.
     *
     * "custom" has max_stores/max_owners = null on purpose: the real
     * quota for a custom customer is negotiated manually and stored as
     * an override on their subscriptions row (see
     * Subscription::effectiveMaxStores() / effectiveMaxOwners()).
     */
    public function run(): void
    {
        $plans = [
            [
                'code' => PlanCode::Basic->value,
                'name' => PlanCode::Basic->label(),
                'max_stores' => 1,
                'max_owners' => 1,
                'price_monthly' => 150_000,
                'price_yearly' => 1_500_000,
                'is_custom' => false,
                'sort_order' => 1,
                'features' => [],
            ],
            [
                'code' => PlanCode::Premium->value,
                'name' => PlanCode::Premium->label(),
                'max_stores' => 3,
                'max_owners' => 1,
                'price_monthly' => 350_000,
                'price_yearly' => 3_500_000,
                'is_custom' => false,
                'sort_order' => 2,
                'features' => [],
            ],
            [
                'code' => PlanCode::Ultra->value,
                'name' => PlanCode::Ultra->label(),
                'max_stores' => 7,
                'max_owners' => 1,
                'price_monthly' => 750_000,
                'price_yearly' => 7_500_000,
                'is_custom' => false,
                'sort_order' => 3,
                'features' => [],
            ],
            [
                'code' => PlanCode::Custom->value,
                'name' => PlanCode::Custom->label(),
                'max_stores' => null,
                'max_owners' => null,
                'price_monthly' => null,
                'price_yearly' => null,
                'is_custom' => true,
                'sort_order' => 4,
                'features' => [],
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(['code' => $plan['code']], $plan);
        }
    }
}
