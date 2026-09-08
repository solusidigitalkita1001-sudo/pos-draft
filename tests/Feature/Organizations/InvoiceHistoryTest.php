<?php

use App\Enums\InvoiceStatus;
use App\Models\Plan;
use App\Models\SubscriptionInvoice;
use App\Models\User;

test('the invoice history page can be rendered', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('organizations.invoices.index'));

    $response->assertOk();
});

test('the invoice history only shows invoices for the current organization', function () {
    $user = User::factory()->create();
    $organization = $user->currentOrganization;

    $plan = Plan::factory()->create();

    $mine = SubscriptionInvoice::factory()->create([
        'organization_id' => $organization->id,
        'plan_id' => $plan->id,
        'status' => InvoiceStatus::Paid,
    ]);

    $otherUser = User::factory()->create();
    SubscriptionInvoice::factory()->create([
        'organization_id' => $otherUser->currentOrganization->id,
        'plan_id' => $plan->id,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('organizations.invoices.index'));

    $response->assertInertia(fn ($page) => $page
        ->component('organizations/invoices')
        ->has('invoices', 1)
        ->where('invoices.0.orderId', $mine->order_id)
    );
});
