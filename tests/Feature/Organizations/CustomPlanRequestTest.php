<?php

use App\Actions\Organizations\RequestCustomPlanAction;
use App\Actions\Organizations\ReviewCustomPlanRequestAction;
use App\Actions\Teams\CreateTeam;
use App\Enums\CustomPlanRequestStatus;
use App\Enums\PlanCode;
use App\Enums\SubscriptionStatus;
use App\Models\CustomPlanRequest;
use App\Models\User;
use Database\Seeders\PlanSeeder;

test('an organization owner can submit a custom plan request', function () {
    $user = User::factory()->create();
    $organization = $user->currentOrganization;

    $request = (new RequestCustomPlanAction)->execute(
        organization: $organization,
        requestedBy: $user,
        requestedMaxStores: 15,
        requestedMaxOwners: 3,
        message: 'Butuh untuk waralaba 15 cabang.',
    );

    expect($request->status)->toBe(CustomPlanRequestStatus::Pending);
    $this->assertDatabaseHas('custom_plan_requests', [
        'organization_id' => $organization->id,
        'requested_max_stores' => 15,
    ]);
});

test('approving a custom plan request switches the organization to the custom plan with the approved quota', function () {
    $this->seed(PlanSeeder::class);

    $admin = User::factory()->create(['is_platform_admin' => true]);
    $owner = User::factory()->create();
    $organization = $owner->currentOrganization;

    $request = (new RequestCustomPlanAction)->execute(
        organization: $organization,
        requestedBy: $owner,
        requestedMaxStores: 15,
        requestedMaxOwners: 3,
        message: null,
    );

    (new ReviewCustomPlanRequestAction)->approve(
        request: $request,
        reviewer: $admin,
        approvedMaxStores: 12, // admin approved a lower number than requested
        approvedMaxOwners: 2,
    );

    $request->refresh();
    expect($request->status)->toBe(CustomPlanRequestStatus::Approved);
    expect($request->reviewed_by)->toBe($admin->id);

    $subscription = $organization->fresh()->currentSubscription();
    expect($subscription->plan->code)->toBe(PlanCode::Custom->value);
    expect($subscription->status)->toBe(SubscriptionStatus::Active);
    expect($subscription->effectiveMaxStores())->toBe(12);
    expect($subscription->effectiveMaxOwners())->toBe(2);
});

test('approving a custom plan request refuses a quota smaller than the current store count', function () {
    $admin = User::factory()->create(['is_platform_admin' => true]);
    $owner = User::factory()->create();
    $organization = $owner->currentOrganization;

    // organization already has 3 stores under it
    (new CreateTeam)->handle($owner, 'Toko A', $organization);
    (new CreateTeam)->handle($owner, 'Toko B', $organization);

    $request = (new RequestCustomPlanAction)->execute($organization, $owner, 10, 1, null);

    expect(fn () => (new ReviewCustomPlanRequestAction)->approve($request, $admin, 1, 1))
        ->toThrow(RuntimeException::class);
});

test('rejecting a custom plan request does not change the subscription', function () {
    $admin = User::factory()->create(['is_platform_admin' => true]);
    $owner = User::factory()->create();
    $organization = $owner->currentOrganization;
    $originalPlanId = $organization->currentSubscription()->plan_id;

    $request = (new RequestCustomPlanAction)->execute($organization, $owner, 10, 1, null);

    (new ReviewCustomPlanRequestAction)->reject($request, $admin);

    expect($request->fresh()->status)->toBe(CustomPlanRequestStatus::Rejected);
    expect($organization->fresh()->currentSubscription()->plan_id)->toBe($originalPlanId);
});

test('a non-admin cannot access the admin custom plan requests panel', function () {
    $user = User::factory()->create(); // is_platform_admin defaults to false

    $response = $this->actingAs($user)->get(route('admin.custom-plan-requests.index'));

    $response->assertForbidden();
});

test('a platform admin can view the admin custom plan requests panel', function () {
    $admin = User::factory()->create(['is_platform_admin' => true]);

    CustomPlanRequest::factory()->create();

    $response = $this->actingAs($admin)->get(route('admin.custom-plan-requests.index'));

    $response->assertOk();
});

test('platform-admin:grant command grants admin access', function () {
    $user = User::factory()->create();

    $this->artisan('platform-admin:grant', ['email' => $user->email])
        ->assertExitCode(0);

    expect($user->fresh()->is_platform_admin)->toBeTrue();
});
