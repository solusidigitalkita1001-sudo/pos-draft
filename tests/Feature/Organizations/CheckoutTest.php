<?php

use App\Actions\Organizations\CreateCheckoutInvoiceAction;
use App\Enums\BillingPeriod;
use App\Enums\InvoiceStatus;
use App\Models\Plan;
use App\Models\SubscriptionInvoice;
use App\Models\User;
use App\Services\Midtrans\MidtransClient;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set([
        'inertia.ssr.enabled' => false,
        'midtrans.server_key' => 'fake-server-key',
        'midtrans.is_production' => false,
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'app.sandbox.midtrans.com/*' => Http::response([
            'token' => 'snap-token-123',
            'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v1/redirect/snap-token-123',
        ], 200),
    ]);
});

test('checking out a plan creates a pending invoice with a snap token', function () {
    $user = User::factory()->create();
    $organization = $user->currentOrganization;

    $plan = Plan::factory()->create([
        'code' => 'premium',
        'max_stores' => 3,
        'price_monthly' => 350000,
    ]);

    $invoice = (new CreateCheckoutInvoiceAction(new MidtransClient('fake-server-key')))
        ->execute($organization, $plan, $user, BillingPeriod::Monthly);

    expect($invoice->status)->toBe(InvoiceStatus::Pending);
    expect($invoice->amount)->toEqual('350000.00');
    expect($invoice->snap_token)->toBe('snap-token-123');
    expect($invoice->order_id)->toStartWith('SUB-');

    Http::assertSent(function ($request) use ($invoice) {
        return str_contains($request->url(), 'snap/v1/transactions')
            && $request['transaction_details']['order_id'] === $invoice->order_id;
    });
});

test('checkout refuses a custom plan', function () {
    $user = User::factory()->create();
    $organization = $user->currentOrganization;

    $plan = Plan::factory()->create(['is_custom' => true]);

    expect(fn () => (new CreateCheckoutInvoiceAction(new MidtransClient('fake-server-key')))
        ->execute($organization, $plan, $user, BillingPeriod::Monthly))
        ->toThrow(RuntimeException::class);
});

test('the checkout endpoint redirects to the checkout show page', function () {
    $user = User::factory()->create();

    Plan::factory()->create(['code' => 'premium', 'max_stores' => 3, 'price_monthly' => 350000]);

    $response = $this
        ->actingAs($user)
        ->post(route('organizations.checkout.store'), [
            'plan_code' => 'premium',
            'billing_period' => 'monthly',
        ]);

    $invoice = SubscriptionInvoice::first();

    $response->assertRedirect(route('organizations.checkout.show', ['invoice' => $invoice->order_id]));
    expect($invoice->snap_token)->toBe('snap-token-123');
});

test('the checkout show page renders with the snap token', function () {
    $user = User::factory()->create();

    Plan::factory()->create(['code' => 'ultra', 'max_stores' => 7, 'price_monthly' => 750000]);

    $this->actingAs($user)->post(route('organizations.checkout.store'), [
        'plan_code' => 'ultra',
        'billing_period' => 'monthly',
    ]);

    $invoice = SubscriptionInvoice::first();

    $response = $this->actingAs($user)->get(route('organizations.checkout.show', ['invoice' => $invoice->order_id]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('organizations/checkout')
        ->where('invoice.snapToken', 'snap-token-123')
    );
});
