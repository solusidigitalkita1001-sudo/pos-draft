<?php

use App\Actions\Organizations\UpgradePlanAction;
use App\Actions\Teams\CreateTeam;
use App\Enums\OrganizationRole;
use App\Exceptions\StoreQuotaExceededException;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;

test('the organization stores page can be rendered', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('organizations.stores'));

    $response->assertOk();
});

test('the owner can upgrade the organization to a bigger plan', function () {
    $user = User::factory()->create();
    $organization = $user->currentOrganization;

    $premiumPlan = Plan::factory()->create(['code' => 'premium', 'max_stores' => 3, 'is_custom' => false]);

    $response = $this
        ->actingAs($user)
        ->post(route('organizations.upgrade'), ['plan_code' => 'premium']);

    $response->assertRedirect(route('organizations.stores'));

    expect($organization->currentSubscription()->plan_id)->toBe($premiumPlan->id);
});

test('upgrading to the custom plan via self-service is rejected', function () {
    $user = User::factory()->create();

    Plan::factory()->create(['code' => 'custom', 'is_custom' => true]);

    $response = $this
        ->actingAs($user)
        ->post(route('organizations.upgrade'), ['plan_code' => 'custom']);

    $response->assertSessionHasErrors('plan_code');
});

test('upgrade action refuses a plan smaller than the current store count', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    $organization->memberships()->create([
        'user_id' => $user->id,
        'role' => OrganizationRole::Owner,
    ]);

    $bigPlan = Plan::factory()->create(['max_stores' => 5]);
    Subscription::factory()->active()->create([
        'organization_id' => $organization->id,
        'plan_id' => $bigPlan->id,
    ]);

    $user->switchOrganization($organization);

    // Fill 3 stores under this organization.
    (new CreateTeam)->handle($user, 'Toko A', $organization);
    (new CreateTeam)->handle($user, 'Toko B', $organization);
    (new CreateTeam)->handle($user, 'Toko C', $organization);

    $smallPlan = Plan::factory()->create(['max_stores' => 1]);

    expect(fn () => (new UpgradePlanAction)->execute($organization, $smallPlan))
        ->toThrow(StoreQuotaExceededException::class);
});
