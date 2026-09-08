<?php

use App\Actions\Organizations\HandleMidtransNotificationAction;
use App\Enums\InvoiceStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\SubscriptionInvoice;
use App\Models\User;
use App\Services\Midtrans\MidtransClient;

const FAKE_SERVER_KEY = 'test-server-key';

function midtransSignature(string $orderId, string $statusCode, string $grossAmount): string
{
    return hash('sha512', $orderId.$statusCode.$grossAmount.FAKE_SERVER_KEY);
}

function pendingInvoiceFor(User $user, array $planAttributes = []): SubscriptionInvoice
{
    $organization = $user->currentOrganization;
    $plan = Plan::factory()->create($planAttributes);

    return SubscriptionInvoice::factory()->create([
        'organization_id' => $organization->id,
        'subscription_id' => $organization->currentSubscription()->id,
        'plan_id' => $plan->id,
        'status' => InvoiceStatus::Pending,
        'amount' => 350000,
    ]);
}

test('a settlement notification with a valid signature marks the invoice paid and activates the plan', function () {
    $user = User::factory()->create();
    $invoice = pendingInvoiceFor($user, ['code' => 'premium', 'max_stores' => 3]);

    $payload = [
        'order_id' => $invoice->order_id,
        'status_code' => '200',
        'gross_amount' => '350000.00',
        'transaction_status' => 'settlement',
        'fraud_status' => 'accept',
        'signature_key' => midtransSignature($invoice->order_id, '200', '350000.00'),
    ];

    (new HandleMidtransNotificationAction(new MidtransClient(FAKE_SERVER_KEY)))->execute($payload);

    $invoice->refresh();
    expect($invoice->status)->toBe(InvoiceStatus::Paid);
    expect($invoice->paid_at)->not->toBeNull();

    $subscription = $user->currentOrganization->fresh()->currentSubscription();
    expect($subscription->plan_id)->toBe($invoice->plan_id);
    expect($subscription->status)->toBe(SubscriptionStatus::Active);
});

test('a notification with an invalid signature is logged but not applied', function () {
    $user = User::factory()->create();
    $invoice = pendingInvoiceFor($user);

    $payload = [
        'order_id' => $invoice->order_id,
        'status_code' => '200',
        'gross_amount' => '350000.00',
        'transaction_status' => 'settlement',
        'signature_key' => 'tampered-signature',
    ];

    (new HandleMidtransNotificationAction(new MidtransClient(FAKE_SERVER_KEY)))->execute($payload);

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Pending);

    $this->assertDatabaseHas('payment_webhook_logs', [
        'order_id' => $invoice->order_id,
        'signature_valid' => false,
    ]);
});

test('the same settlement notification delivered twice only applies once', function () {
    $user = User::factory()->create();
    $invoice = pendingInvoiceFor($user, ['code' => 'premium', 'max_stores' => 3]);

    $payload = [
        'order_id' => $invoice->order_id,
        'status_code' => '200',
        'gross_amount' => '350000.00',
        'transaction_status' => 'settlement',
        'fraud_status' => 'accept',
        'signature_key' => midtransSignature($invoice->order_id, '200', '350000.00'),
    ];

    $action = new HandleMidtransNotificationAction(new MidtransClient(FAKE_SERVER_KEY));
    $action->execute($payload);
    $paidAtFirstRun = $invoice->fresh()->paid_at;

    $action->execute($payload); // redelivery

    expect($invoice->fresh()->paid_at->equalTo($paidAtFirstRun))->toBeTrue();
    $this->assertDatabaseCount('payment_webhook_logs', 2); // both deliveries logged for audit
});

test('an expire notification marks the invoice expired without touching the subscription', function () {
    $user = User::factory()->create();
    $invoice = pendingInvoiceFor($user);
    $originalPlanId = $user->currentOrganization->currentSubscription()->plan_id;

    $payload = [
        'order_id' => $invoice->order_id,
        'status_code' => '200',
        'gross_amount' => '350000.00',
        'transaction_status' => 'expire',
        'signature_key' => midtransSignature($invoice->order_id, '200', '350000.00'),
    ];

    (new HandleMidtransNotificationAction(new MidtransClient(FAKE_SERVER_KEY)))->execute($payload);

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Expired);
    expect($user->currentOrganization->fresh()->currentSubscription()->plan_id)->toBe($originalPlanId);
});

test('the webhook endpoint always responds 200 even for an unknown order_id', function () {
    $response = $this->postJson(route('webhooks.midtrans'), [
        'order_id' => 'DOES-NOT-EXIST',
        'status_code' => '200',
        'gross_amount' => '100.00',
        'transaction_status' => 'settlement',
        'signature_key' => 'whatever',
    ]);

    $response->assertOk();
});
