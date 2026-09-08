<?php

use App\Enums\OrganizationRole;
use App\Models\OrganizationInvitation;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

test('an organization owner can invite a second owner', function () {
    Notification::fake();

    $owner = User::factory()->create();
    $organization = $owner->currentOrganization;

    // UserFactory's default subscription has a generous max_owners (5),
    // so a second invite fits.
    $response = $this
        ->actingAs($owner)
        ->post(route('organizations.invitations.store'), [
            'email' => 'co-owner@example.test',
            'role' => OrganizationRole::Owner->value,
        ]);

    $response->assertRedirect(route('organizations.members.index'));

    $this->assertDatabaseHas('organization_invitations', [
        'organization_id' => $organization->id,
        'email' => 'co-owner@example.test',
        'role' => OrganizationRole::Owner->value,
    ]);
});

test('inviting a member is blocked once the max_owners quota is reached', function () {
    Notification::fake();

    $owner = User::factory()->create();
    $organization = $owner->currentOrganization;

    // Pin the quota to 1 (just the owner) so the very next invite is refused.
    $organization->currentSubscription()->update(['max_owners_override' => 1]);

    $response = $this
        ->actingAs($owner)
        ->post(route('organizations.invitations.store'), [
            'email' => 'second-owner@example.test',
            'role' => OrganizationRole::Manager->value,
        ]);

    $response->assertRedirect(route('organizations.members.index'));

    $this->assertDatabaseMissing('organization_invitations', [
        'email' => 'second-owner@example.test',
    ]);
});

test('a non-owner cannot invite members', function () {
    $owner = User::factory()->create();
    $manager = User::factory()->create(['email' => 'manager@example.test']);
    $organization = $owner->currentOrganization;

    $organization->memberships()->create([
        'user_id' => $manager->id,
        'role' => OrganizationRole::Manager,
    ]);
    $manager->switchOrganization($organization);

    $response = $this
        ->actingAs($manager)
        ->post(route('organizations.invitations.store'), [
            'email' => 'someone@example.test',
            'role' => OrganizationRole::Manager->value,
        ]);

    $response->assertForbidden();
});

test('an invited user can accept and join the organization', function () {
    $owner = User::factory()->create();
    $organization = $owner->currentOrganization;
    $invitedUser = User::factory()->create(['email' => 'invited@example.test']);

    $invitation = OrganizationInvitation::factory()->create([
        'organization_id' => $organization->id,
        'email' => 'invited@example.test',
        'role' => OrganizationRole::Manager,
        'invited_by' => $owner->id,
    ]);

    $response = $this
        ->actingAs($invitedUser)
        ->get(route('organization-invitations.accept', $invitation));

    $response->assertRedirect(route('organizations.stores'));

    expect($invitedUser->fresh()->belongsToOrganization($organization))->toBeTrue();
    expect($invitedUser->fresh()->organizationRole($organization))->toBe(OrganizationRole::Manager);
    expect($invitation->fresh()->accepted_at)->not->toBeNull();
});

test('an expired invitation cannot be accepted', function () {
    $owner = User::factory()->create();
    $organization = $owner->currentOrganization;
    $invitedUser = User::factory()->create(['email' => 'invited@example.test']);

    $invitation = OrganizationInvitation::factory()->expired()->create([
        'organization_id' => $organization->id,
        'email' => 'invited@example.test',
        'invited_by' => $owner->id,
    ]);

    $response = $this
        ->actingAs($invitedUser)
        ->get(route('organization-invitations.accept', $invitation));

    $response->assertSessionHasErrors('invitation');
    expect($invitedUser->fresh()->belongsToOrganization($organization))->toBeFalse();
});

test('accepting an invitation is refused if the quota filled up in the meantime', function () {
    $owner = User::factory()->create();
    $organization = $owner->currentOrganization;
    $invitedUser = User::factory()->create(['email' => 'invited@example.test']);

    $invitation = OrganizationInvitation::factory()->create([
        'organization_id' => $organization->id,
        'email' => 'invited@example.test',
        'role' => OrganizationRole::Manager,
        'invited_by' => $owner->id,
    ]);

    // Quota tightened to 1 (just the owner) AFTER the invite was sent —
    // simulates a downgrade or another member joining in between.
    $organization->currentSubscription()->update(['max_owners_override' => 1]);

    $response = $this
        ->actingAs($invitedUser)
        ->get(route('organization-invitations.accept', $invitation));

    $response->assertRedirect(route('organizations.stores'));
    expect($invitedUser->fresh()->belongsToOrganization($organization))->toBeFalse();
    expect($invitation->fresh()->accepted_at)->toBeNull();
});

test('an owner can remove another member', function () {
    $owner = User::factory()->create();
    $organization = $owner->currentOrganization;
    $manager = User::factory()->create();

    $organization->memberships()->create([
        'user_id' => $manager->id,
        'role' => OrganizationRole::Manager,
    ]);

    $response = $this
        ->actingAs($owner)
        ->delete(route('organizations.members.destroy', $manager));

    $response->assertRedirect(route('organizations.members.index'));

    $this->assertDatabaseMissing('organization_members', [
        'organization_id' => $organization->id,
        'user_id' => $manager->id,
    ]);
});

test('the last owner of an organization cannot be removed', function () {
    $owner = User::factory()->create();
    $organization = $owner->currentOrganization;

    $response = $this
        ->actingAs($owner)
        ->delete(route('organizations.members.destroy', $owner));

    expect($organization->fresh()->memberships()->count())->toBe(1);
});
