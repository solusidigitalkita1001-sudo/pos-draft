<?php

use App\Actions\Organizations\ProcessSubscriptionLifecycleAction;
use App\Enums\OrganizationRole;
use App\Enums\SubscriptionStatus;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\Organizations\SubscriptionPastDueNotification;
use App\Notifications\Organizations\SubscriptionSuspendedNotification;
use App\Notifications\Organizations\TrialEndingSoonNotification;
use Illuminate\Support\Facades\Notification;

/**
 * Build a standalone organization (owned by $user) with a subscription
 * in a specific state, without relying on UserFactory's default
 * active/generous subscription.
 */
function organizationWithSubscription(User $user, array $subscriptionAttributes): Organization
{
    $organization = Organization::factory()->create();

    $organization->memberships()->create([
        'user_id' => $user->id,
        'role' => OrganizationRole::Owner,
    ]);

    $plan = Plan::factory()->create();

    Subscription::factory()->create(array_merge([
        'organization_id' => $organization->id,
        'plan_id' => $plan->id,
    ], $subscriptionAttributes));

    return $organization->fresh();
}

test('a trial ending within 3 days sends exactly one reminder', function () {
    Notification::fake();

    $user = User::factory()->create();
    $organization = organizationWithSubscription($user, [
        'status' => SubscriptionStatus::Trial,
        'trial_ends_at' => now()->addDays(2),
    ]);

    (new ProcessSubscriptionLifecycleAction)->execute();
    (new ProcessSubscriptionLifecycleAction)->execute(); // run twice, same day

    Notification::assertSentToTimes($user, TrialEndingSoonNotification::class, 1);
});

test('a trial that has already ended is suspended', function () {
    Notification::fake();

    $user = User::factory()->create();
    $organization = organizationWithSubscription($user, [
        'status' => SubscriptionStatus::Trial,
        'trial_ends_at' => now()->subDay(),
    ]);

    (new ProcessSubscriptionLifecycleAction)->execute();

    expect($organization->currentSubscription()->fresh()->status)->toBe(SubscriptionStatus::Suspended);
    Notification::assertSentTo($user, SubscriptionSuspendedNotification::class);
});

test('an active subscription past its period end becomes past_due', function () {
    Notification::fake();

    $user = User::factory()->create();
    $organization = organizationWithSubscription($user, [
        'status' => SubscriptionStatus::Active,
        'current_period_end' => now()->subDay(),
    ]);

    (new ProcessSubscriptionLifecycleAction)->execute();

    expect($organization->currentSubscription()->fresh()->status)->toBe(SubscriptionStatus::PastDue);
    Notification::assertSentTo($user, SubscriptionPastDueNotification::class);
});

test('a past_due subscription beyond the grace period becomes suspended', function () {
    Notification::fake();

    $user = User::factory()->create();
    $organization = organizationWithSubscription($user, [
        'status' => SubscriptionStatus::PastDue,
        'current_period_end' => now()->subDays(4), // grace period is 3 days
    ]);

    (new ProcessSubscriptionLifecycleAction)->execute();

    expect($organization->currentSubscription()->fresh()->status)->toBe(SubscriptionStatus::Suspended);
    Notification::assertSentTo($user, SubscriptionSuspendedNotification::class);
});

test('a past_due subscription still within the grace period is left alone', function () {
    Notification::fake();

    $user = User::factory()->create();
    $organization = organizationWithSubscription($user, [
        'status' => SubscriptionStatus::PastDue,
        'current_period_end' => now()->subDay(), // only 1 day into the 3-day grace period
    ]);

    (new ProcessSubscriptionLifecycleAction)->execute();

    expect($organization->currentSubscription()->fresh()->status)->toBe(SubscriptionStatus::PastDue);
    Notification::assertNotSentTo($user, SubscriptionSuspendedNotification::class);
});

test('a suspended organization is redirected away from its team routes', function () {
    $user = User::factory()->create();
    $team = $user->personalTeam();
    $organization = $team->organization;

    $organization->currentSubscription()->update(['status' => SubscriptionStatus::Suspended]);

    $response = $this
        ->actingAs($user)
        ->get(route('dashboard', ['current_team' => $team->slug]));

    $response->assertRedirect(route('organizations.stores'));
});
