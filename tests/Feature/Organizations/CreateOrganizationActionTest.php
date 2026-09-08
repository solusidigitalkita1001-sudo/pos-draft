<?php

use App\Actions\Organizations\CreateOrganizationAction;
use App\Enums\OrganizationRole;
use App\Enums\PlanCode;
use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\User;
use Database\Seeders\PlanSeeder;

beforeEach(function () {
    $this->seed(PlanSeeder::class);
});

test('creating an organization also creates an owner membership and a trial subscription', function () {
    $user = User::factory()->create();
    $plan = Plan::findByCode(PlanCode::Basic);

    $organization = (new CreateOrganizationAction)->execute($user, 'Toko Kopi Kita', $plan);

    $this->assertDatabaseHas('organizations', [
        'id' => $organization->id,
        'name' => 'Toko Kopi Kita',
    ]);

    $this->assertDatabaseHas('organization_members', [
        'organization_id' => $organization->id,
        'user_id' => $user->id,
        'role' => OrganizationRole::Owner->value,
    ]);

    $subscription = $organization->currentSubscription();

    expect($subscription)->not->toBeNull();
    expect($subscription->plan_id)->toBe($plan->id);
    expect($subscription->status)->toBe(SubscriptionStatus::Trial);
    expect($subscription->trial_ends_at)->not->toBeNull();
});

test('basic plan allows only 1 store and 1 owner by default', function () {
    $plan = Plan::findByCode(PlanCode::Basic);

    expect($plan->max_stores)->toBe(1);
    expect($plan->max_owners)->toBe(1);
});

test('premium plan allows up to 3 stores', function () {
    $plan = Plan::findByCode(PlanCode::Premium);

    expect($plan->max_stores)->toBe(3);
});

test('ultra plan allows up to 7 stores', function () {
    $plan = Plan::findByCode(PlanCode::Ultra);

    expect($plan->max_stores)->toBe(7);
});

test('custom plan has negotiable quotas by default', function () {
    $plan = Plan::findByCode(PlanCode::Custom);

    expect($plan->is_custom)->toBeTrue();
    expect($plan->max_stores)->toBeNull();
    expect($plan->max_owners)->toBeNull();
});

test('subscription override takes precedence over the plan default', function () {
    $user = User::factory()->create();
    $plan = Plan::findByCode(PlanCode::Custom);

    $organization = (new CreateOrganizationAction)->execute($user, 'Grup Retail Custom', $plan);

    $organization->currentSubscription()->update(['max_stores_override' => 12]);

    expect($organization->currentSubscription()->fresh()->effectiveMaxStores())->toBe(12);
});
