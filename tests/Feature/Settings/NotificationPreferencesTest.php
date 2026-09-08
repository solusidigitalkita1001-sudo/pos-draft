<?php

use App\Actions\Organizations\RequestCustomPlanAction;
use App\Enums\NotificationPreferenceKey;
use App\Models\User;
use App\Notifications\Organizations\CustomPlanRequestSubmittedNotification;
use App\Notifications\Organizations\SubscriptionSuspendedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

test('a user with no preferences set yet is treated as wanting every notification (opt-out, not opt-in)', function () {
    $user = User::factory()->create();

    expect($user->notification_preferences)->toBeNull();

    foreach (NotificationPreferenceKey::all() as $key) {
        expect($user->wantsNotification($key))->toBeTrue();
    }
});

test('a user can disable a specific notification type', function () {
    $user = User::factory()->create();

    $user->update([
        'notification_preferences' => [
            NotificationPreferenceKey::TrialEndingSoon->value => false,
        ],
    ]);

    expect($user->fresh()->wantsNotification(NotificationPreferenceKey::TrialEndingSoon))->toBeFalse();
    // Keys not explicitly set stay enabled by default.
    expect($user->fresh()->wantsNotification(NotificationPreferenceKey::SubscriptionSuspended))->toBeTrue();
});

test('a disabled notification is never actually sent', function () {
    Notification::fake();

    $user = User::factory()->create();
    $user->update([
        'notification_preferences' => [
            NotificationPreferenceKey::SubscriptionSuspended->value => false,
        ],
    ]);
    $user->refresh();

    $subscription = $user->currentOrganization->currentSubscription();
    $user->notify(new SubscriptionSuspendedNotification($subscription));

    Notification::assertNotSentTo($user, SubscriptionSuspendedNotification::class);
});

test('the notification preferences page can be rendered', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('notifications.edit'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('settings/notifications')
        ->has('preferences', count(NotificationPreferenceKey::all()))
    );
});

test('a user can update their notification preferences via the settings page', function () {
    $user = User::factory()->create();

    $payload = collect(NotificationPreferenceKey::all())
        ->mapWithKeys(fn ($key) => [$key->value => true])
        ->put(NotificationPreferenceKey::TrialEndingSoon->value, false)
        ->all();

    $response = $this
        ->actingAs($user)
        ->patch(route('notifications.update'), ['preferences' => $payload]);

    $response->assertRedirect();

    $user->refresh();
    expect($user->wantsNotification(NotificationPreferenceKey::TrialEndingSoon))->toBeFalse();
    expect($user->wantsNotification(NotificationPreferenceKey::SubscriptionSuspended))->toBeTrue();
});

test('a custom plan request submission notification still records in-app even if email is disabled', function () {
    Notification::fake();

    $admin = User::factory()->create(['is_platform_admin' => true]);
    $admin->update([
        'notification_preferences' => [
            NotificationPreferenceKey::CustomPlanRequestSubmitted->value => false,
        ],
    ]);
    $admin->refresh();

    $owner = User::factory()->create();

    (new RequestCustomPlanAction)->execute(
        organization: $owner->currentOrganization,
        requestedBy: $owner,
        requestedMaxStores: 10,
        requestedMaxOwners: 2,
        message: null,
    );

    Notification::assertSentTo(
        $admin,
        CustomPlanRequestSubmittedNotification::class,
        fn ($notification, $channels) => $channels === ['database']
    );
});
