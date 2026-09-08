<?php

namespace App\Actions\Organizations;

use App\Enums\BillingPeriod;
use App\Enums\InvoiceStatus;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\SubscriptionInvoice;
use App\Models\User;
use App\Services\Midtrans\MidtransClient;
use App\Support\DocumentNumberGenerator;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CreateCheckoutInvoiceAction
{
    public function __construct(private MidtransClient $midtrans) {}

    /**
     * Create a pending invoice for the given plan/period and obtain a
     * Midtrans Snap token to collect payment. Refuses plans that are
     * marked custom (those go through RequestCustomPlanAction instead)
     * and plans whose store quota can't fit the organization's current
     * store count.
     */
    public function execute(Organization $organization, Plan $plan, User $payer, BillingPeriod $period): SubscriptionInvoice
    {
        if ($plan->is_custom) {
            throw new RuntimeException('Plan custom tidak bisa di-checkout otomatis — gunakan alur request custom plan.');
        }

        $amount = $period === BillingPeriod::Yearly ? $plan->price_yearly : $plan->price_monthly;

        if ($amount === null) {
            throw new RuntimeException("Plan {$plan->code} tidak punya harga untuk periode {$period->value}.");
        }

        $currentStoreCount = $organization->teams()->count();

        if ($plan->max_stores !== null && $currentStoreCount > $plan->max_stores) {
            throw new RuntimeException('Jumlah toko saat ini melebihi kuota plan tujuan.');
        }

        $invoice = DB::transaction(function () use ($organization, $plan, $period, $amount) {
            $orderId = DocumentNumberGenerator::generate(
                prefix: 'SUB',
                table: 'subscription_invoices',
                column: 'order_id',
                scopeId: $organization->id,
                scopeColumn: 'organization_id',
            );

            return SubscriptionInvoice::create([
                'organization_id' => $organization->id,
                'subscription_id' => $organization->currentSubscription()?->id,
                'plan_id' => $plan->id,
                'order_id' => $orderId,
                'amount' => $amount,
                'billing_period' => $period,
                'status' => InvoiceStatus::Pending,
                'expires_at' => now()->addHours(24),
            ]);
        });

        // Deliberately OUTSIDE the transaction above — an outbound HTTP
        // call to Midtrans shouldn't hold a DB transaction open. If this
        // call fails, the invoice row simply stays without a snap_token;
        // the checkout page/controller can retry generating one.
        $snap = $this->midtrans->createSnapTransaction($invoice, $payer->name, $payer->email);

        $invoice->update(['snap_token' => $snap['token']]);

        return $invoice;
    }
}
