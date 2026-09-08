<?php

use App\Actions\Teams\CreateTeam;
use App\Enums\OrganizationRole;
use App\Enums\PlanCode;
use App\Enums\SubscriptionStatus;
use App\Exceptions\StoreQuotaExceededException;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Policies\TeamPolicy;
use Database\Seeders\PlanSeeder;

/**
 * Build an organization pinned to a specific store quota, owned by the
 * given user, without relying on UserFactory's generous default quota
 * (10) — Sprint 2 quota tests need to control the limit precisely.
 */
function organizationWithStoreLimit(User $user, ?int $maxStores): Organization
{
    $organization = Organization::factory()->create();

    $organization->memberships()->create([
        'user_id' => $user->id,
        'role' => OrganizationRole::Owner,
    ]);

    $plan = Plan::factory()->create(['max_stores' => $maxStores]);

    Subscription::factory()->active()->create([
        'organization_id' => $organization->id,
        'plan_id' => $plan->id,
    ]);

    $user->switchOrganization($organization);

    return $organization->fresh();
}

test('creating a store is blocked once the plan quota is reached', function () {
    $user = User::factory()->create();
    $organization = organizationWithStoreLimit($user, maxStores: 1);

    // The user already has 1 personal team from UserFactory, but it
    // isn't under this organization — attach a store to fill the quota.
    (new CreateTeam)->handle($user, 'Toko Pertama', $organization);

    expect(fn () => (new CreateTeam)->handle($user, 'Toko Kedua', $organization))
        ->toThrow(StoreQuotaExceededException::class);

    $this->assertDatabaseMissing('teams', ['name' => 'Toko Kedua']);
});

test('creating a store succeeds while under the plan quota', function () {
    $user = User::factory()->create();
    $organization = organizationWithStoreLimit($user, maxStores: 3);

    (new CreateTeam)->handle($user, 'Toko A', $organization);
    (new CreateTeam)->handle($user, 'Toko B', $organization);

    $this->assertDatabaseHas('teams', ['name' => 'Toko A', 'organization_id' => $organization->id]);
    $this->assertDatabaseHas('teams', ['name' => 'Toko B', 'organization_id' => $organization->id]);
});

test('a null quota (custom plan without override) allows unlimited stores', function () {
    $user = User::factory()->create();
    $organization = organizationWithStoreLimit($user, maxStores: null);

    (new CreateTeam)->handle($user, 'Toko A', $organization);
    (new CreateTeam)->handle($user, 'Toko B', $organization);
    (new CreateTeam)->handle($user, 'Toko C', $organization);

    expect($organization->teams()->count())->toBe(3);
});

test('the team store endpoint redirects to organization stores page when quota is exceeded', function () {
    $user = User::factory()->create();
    $organization = organizationWithStoreLimit($user, maxStores: 1);

    (new CreateTeam)->handle($user, 'Toko Pertama', $organization);

    $response = $this
        ->actingAs($user)
        ->post(route('teams.store'), ['name' => 'Toko Kedua']);

    $response->assertRedirect(route('organizations.stores'));
    $this->assertDatabaseMissing('teams', ['name' => 'Toko Kedua']);
});

test('a user who is not a member of the organization cannot be authorized to create a store for it', function () {
    $owner = User::factory()->create();
    $organization = organizationWithStoreLimit($owner, maxStores: 5);

    $outsider = User::factory()->create();

    expect((new TeamPolicy)->create($outsider, $organization))->toBeFalse();
    expect((new TeamPolicy)->create($owner, $organization))->toBeTrue();
});

test('registering a new user creates an organization on the basic plan', function () {
    $this->seed(PlanSeeder::class);

    $response = $this->post('/register', [
        'name' => 'Budi Santoso',
        'email' => 'budi@example.test',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $user = User::where('email', 'budi@example.test')->first();

    expect($user)->not->toBeNull();

    $organization = $user->currentOrganization;

    expect($organization)->not->toBeNull();

    $subscription = $organization->currentSubscription();

    expect($subscription->status)->toBe(SubscriptionStatus::Trial);
    expect($subscription->plan->code)->toBe(PlanCode::Basic->value);
    expect($user->personalTeam()->organization_id)->toBe($organization->id);
});
