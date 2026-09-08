<?php

use App\Actions\Organizations\ProcessSubscriptionLifecycleAction;
use App\Enums\OrganizationRole;
use App\Enums\SubscriptionStatus;
use App\Models\OrganizationInvitation;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

test('an owner can change another members role', function () {
    $owner = User::factory()->create();
    $organization = $owner->currentOrganization;
    $manager = User::factory()->create();

    $organization->memberships()->create([
        'user_id' => $manager->id,
        'role' => OrganizationRole::Manager,
    ]);

    $response = $this
        ->actingAs($owner)
        ->patch(route('organizations.members.update', $manager), [
            'role' => OrganizationRole::Owner->value,
        ]);

    $response->assertRedirect(route('organizations.members.index'));

    $this->assertDatabaseHas('organization_members', [
        'organization_id' => $organization->id,
        'user_id' => $manager->id,
        'role' => OrganizationRole::Owner->value,
    ]);
});

test('the last owner cannot be demoted', function () {
    $owner = User::factory()->create();

    $response = $this
        ->actingAs($owner)
        ->patch(route('organizations.members.update', $owner), [
            'role' => OrganizationRole::Manager->value,
        ]);

    $this->assertDatabaseHas('organization_members', [
        'user_id' => $owner->id,
        'role' => OrganizationRole::Owner->value,
    ]);
});

test('a pending invitation can be resent', function () {
    Notification::fake();

    $owner = User::factory()->create();
    $organization = $owner->currentOrganization;

    $invitation = OrganizationInvitation::factory()->create([
        'organization_id' => $organization->id,
        'invited_by' => $owner->id,
        'expires_at' => now()->addHour(),
    ]);

    $response = $this
        ->actingAs($owner)
        ->post(route('organizations.invitations.resend', $invitation));

    $response->assertRedirect(route('organizations.members.index'));

    $refreshedExpiry = $invitation->fresh()->expires_at;
    expect($refreshedExpiry->isAfter(now()->addDays(2)))->toBeTrue();
});

test('an already-accepted invitation cannot be resent', function () {
    $owner = User::factory()->create();
    $organization = $owner->currentOrganization;

    $invitation = OrganizationInvitation::factory()->accepted()->create([
        'organization_id' => $organization->id,
        'invited_by' => $owner->id,
    ]);

    $response = $this
        ->actingAs($owner)
        ->post(route('organizations.invitations.resend', $invitation));

    $response->assertSessionHas('error');
});

test('an owner can cancel their subscription and it stays active until period end', function () {
    $owner = User::factory()->create();
    $organization = $owner->currentOrganization;
    $organization->currentSubscription()->update([
        'current_period_end' => now()->addDays(10),
    ]);

    $response = $this
        ->actingAs($owner)
        ->post(route('organizations.subscription.cancel'));

    $response->assertRedirect(route('organizations.stores'));

    $subscription = $organization->currentSubscription()->fresh();
    expect($subscription->canceled_at)->not->toBeNull();
    expect($subscription->status)->toBe(SubscriptionStatus::Active); // not cut off immediately
    expect($subscription->isUsable())->toBeTrue();
});

test('a canceled subscription can be resumed before the period ends', function () {
    $owner = User::factory()->create();
    $organization = $owner->currentOrganization;
    $organization->currentSubscription()->update([
        'canceled_at' => now(),
        'current_period_end' => now()->addDays(10),
    ]);

    $response = $this
        ->actingAs($owner)
        ->post(route('organizations.subscription.resume'));

    $response->assertRedirect(route('organizations.stores'));
    expect($organization->currentSubscription()->fresh()->canceled_at)->toBeNull();
});

test('the lifecycle job finalizes a canceled subscription once its period ends, without going through past_due', function () {
    $owner = User::factory()->create();
    $organization = $owner->currentOrganization;
    $organization->currentSubscription()->update([
        'canceled_at' => now()->subDays(5),
        'current_period_end' => now()->subDay(),
    ]);

    (new ProcessSubscriptionLifecycleAction)->execute();

    $subscription = $organization->currentSubscription()->fresh();
    expect($subscription->status)->toBe(SubscriptionStatus::Canceled);
});

test('the invoice csv can be downloaded', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('organizations.invoices.download'));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toStartWith('text/csv');
});
