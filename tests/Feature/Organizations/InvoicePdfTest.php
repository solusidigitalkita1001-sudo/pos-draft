<?php

use App\Enums\InvoiceStatus;
use App\Models\Plan;
use App\Models\SubscriptionInvoice;
use App\Models\User;
use App\Services\Pdf\SubscriptionInvoicePdfGenerator;

test('an invoice can be downloaded as a PDF', function () {
    $user = User::factory()->create();
    $organization = $user->currentOrganization;

    $plan = Plan::factory()->create(['code' => 'premium']);
    $invoice = SubscriptionInvoice::factory()->paid()->create([
        'organization_id' => $organization->id,
        'plan_id' => $plan->id,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('organizations.invoices.pdf', $invoice));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toBe('application/pdf');
    expect($response->headers->get('Content-Disposition'))->toContain($invoice->order_id);
});

test('a user cannot download a PDF for an invoice belonging to another organization', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $plan = Plan::factory()->create();
    $invoice = SubscriptionInvoice::factory()->create([
        'organization_id' => $otherUser->currentOrganization->id,
        'plan_id' => $plan->id,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('organizations.invoices.pdf', $invoice));

    $response->assertNotFound();
});

test('the PDF generator produces a non-empty PDF document', function () {
    $user = User::factory()->create();
    $organization = $user->currentOrganization;

    $plan = Plan::factory()->create(['name' => 'Premium']);
    $invoice = SubscriptionInvoice::factory()->create([
        'organization_id' => $organization->id,
        'plan_id' => $plan->id,
        'status' => InvoiceStatus::Pending,
    ]);

    $pdf = (new SubscriptionInvoicePdfGenerator)->generate($invoice);

    expect($pdf)->toBeString();
    expect(strlen($pdf))->toBeGreaterThan(100);
    expect(str_starts_with($pdf, '%PDF-'))->toBeTrue();
});
