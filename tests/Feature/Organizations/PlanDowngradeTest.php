<?php

use App\Actions\Organizations\CancelPlanDowngradeAction;
use App\Actions\Organizations\CancelSubscriptionAction;
use App\Actions\Organizations\ProcessSubscriptionLifecycleAction;
use App\Actions\Organizations\RequestPlanDowngradeAction;
use App\Actions\Teams\CreateTeam;
use App\Enums\OrganizationRole;
use App\Enums\PlanCode;
use App\Models\Plan;
use App\Models\User;
use Database\Seeders\PlanSeeder;

test('requesting a downgrade schedules it without changing the plan immediately', function () {
    $this->seed(PlanSeeder::class);

    $owner = User::factory()->create();
    $organization = $owner->currentOrganization;
    $ultra = Plan::findByCode(PlanCode::Ultra);
    $premium = Plan::findByCode(PlanCode::Premium);

    $organization->currentSubscription()->update([
        'plan_id' => $ultra->id,
        'current_period_end' => now()->addDays(10),
    ]);

    $subscription = (new RequestPlanDowngradeAction)->execute($organization, $premium);

    expect($subscription->pending_plan_id)->toBe($premium->id);
    expect($subscription->plan_id)->toBe($ultra->id); // unchanged until period ends
});

test('requesting a downgrade to an equal-or-higher plan is refused', function () {
    $this->seed(PlanSeeder::class);

    $owner = User::factory()->create();
    $organization = $owner->currentOrganization;
    $basic = Plan::findByCode(PlanCode::Basic);
    $premium = Plan::findByCode(PlanCode::Premium);

    $organization->currentSubscription()->update(['plan_id' => $basic->id]);

    expect(fn () => (new RequestPlanDowngradeAction)->execute($organization, $premium))
        ->toThrow(RuntimeException::class);
});

test('requesting a downgrade whose quota does not fit the current store count is refused', function () {
    $this->seed(PlanSeeder::class);

    $owner = User::factory()->create();
    $organization = $owner->currentOrganization;
    $ultra = Plan::findByCode(PlanCode::Ultra);
    $basic = Plan::findByCode(PlanCode::Basic);

    $organization->currentSubscription()->update(['plan_id' => $ultra->id]);

    (new CreateTeam)->handle($owner, 'Toko 2', $organization);
    (new CreateTeam)->handle($owner, 'Toko 3', $organization); // 3 stores total, basic only allows 1

    expect(fn () => (new RequestPlanDowngradeAction)->execute($organization, $basic))
        ->toThrow(RuntimeException::class);
});

test('a scheduled downgrade can be canceled before it takes effect', function () {
    $this->seed(PlanSeeder::class);

    $owner = User::factory()->create();
    $organization = $owner->currentOrganization;
    $ultra = Plan::findByCode(PlanCode::Ultra);
    $premium = Plan::findByCode(PlanCode::Premium);

    $organization->currentSubscription()->update(['plan_id' => $ultra->id]);
    (new RequestPlanDowngradeAction)->execute($organization, $premium);

    $subscription = (new CancelPlanDowngradeAction)->execute($organization);

    expect($subscription->pending_plan_id)->toBeNull();
    expect($subscription->plan_id)->toBe($ultra->id);
});

test('the lifecycle job applies a scheduled downgrade once the period ends', function () {
    $this->seed(PlanSeeder::class);

    $owner = User::factory()->create();
    $organization = $owner->currentOrganization;
    $ultra = Plan::findByCode(PlanCode::Ultra);
    $premium = Plan::findByCode(PlanCode::Premium);

    $organization->currentSubscription()->update([
        'plan_id' => $ultra->id,
        'current_period_end' => now()->subDay(), // already ended
    ]);
    (new RequestPlanDowngradeAction)->execute($organization, $premium);

    (new ProcessSubscriptionLifecycleAction)->execute();

    $subscription = $organization->currentSubscription()->fresh();
    expect($subscription->plan_id)->toBe($premium->id);
    expect($subscription->pending_plan_id)->toBeNull();
});

test('the lifecycle job skips a downgrade that no longer fits the store count, and drops it', function () {
    $this->seed(PlanSeeder::class);

    $owner = User::factory()->create();
    $organization = $owner->currentOrganization;
    $ultra = Plan::findByCode(PlanCode::Ultra);
    $basic = Plan::findByCode(PlanCode::Basic);

    $organization->currentSubscription()->update([
        'plan_id' => $ultra->id,
        'current_period_end' => now()->subDay(),
    ]);
    (new RequestPlanDowngradeAction)->execute($organization, $basic);

    // Store count grows AFTER the downgrade was requested but BEFORE it's finalized.
    (new CreateTeam)->handle($owner, 'Toko 2', $organization);
    (new CreateTeam)->handle($owner, 'Toko 3', $organization);

    (new ProcessSubscriptionLifecycleAction)->execute();

    $subscription = $organization->currentSubscription()->fresh();
    expect($subscription->plan_id)->toBe($ultra->id); // unchanged, downgrade dropped
    expect($subscription->pending_plan_id)->toBeNull();
});

test('canceling a subscription clears any scheduled downgrade', function () {
    $this->seed(PlanSeeder::class);

    $owner = User::factory()->create();
    $organization = $owner->currentOrganization;
    $ultra = Plan::findByCode(PlanCode::Ultra);
    $premium = Plan::findByCode(PlanCode::Premium);

    $organization->currentSubscription()->update([
        'plan_id' => $ultra->id,
        'current_period_end' => now()->addDays(5),
    ]);
    (new RequestPlanDowngradeAction)->execute($organization, $premium);

    (new CancelSubscriptionAction)->execute($organization);

    $subscription = $organization->currentSubscription()->fresh();
    expect($subscription->pending_plan_id)->toBeNull();
    expect($subscription->canceled_at)->not->toBeNull();
});

test('the downgrade endpoint requires owner access', function () {
    $this->seed(PlanSeeder::class);

    $owner = User::factory()->create();
    $organization = $owner->currentOrganization;
    $ultra = Plan::findByCode(PlanCode::Ultra);
    $organization->currentSubscription()->update(['plan_id' => $ultra->id]);

    $manager = User::factory()->create();
    $organization->memberships()->create([
        'user_id' => $manager->id,
        'role' => OrganizationRole::Manager,
    ]);
    $manager->switchOrganization($organization);

    $response = $this
        ->actingAs($manager)
        ->post(route('organizations.subscription.downgrade'), ['plan_code' => 'basic']);

    $response->assertForbidden();
});
