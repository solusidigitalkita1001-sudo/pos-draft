<?php

namespace App\Actions\Organizations;

use App\Enums\InvoiceStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Organization;
use App\Models\PaymentWebhookLog;
use App\Models\SubscriptionInvoice;
use App\Notifications\Organizations\InvoicePaymentFailedNotification;
use App\Services\Midtrans\MidtransClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HandleMidtransNotificationAction
{
    public function __construct(private MidtransClient $midtrans) {}

    /**
     * Process a raw Midtrans webhook payload. Always logs the payload
     * first (audit trail), regardless of whether it turns out to be
     * valid — this method never throws for a bad/unrecognized payload,
     * it just logs and returns, so the webhook controller can always
     * respond 200 and stop Midtrans from retrying forever.
     */
    public function execute(array $payload): void
    {
        $orderId = $payload['order_id'] ?? null;
        $transactionStatus = $payload['transaction_status'] ?? null;
        $signatureValid = $this->midtrans->verifySignature($payload);

        PaymentWebhookLog::create([
            'order_id' => $orderId,
            'transaction_status' => $transactionStatus,
            'signature_valid' => $signatureValid,
            'raw_payload' => $payload,
        ]);

        if (! $signatureValid) {
            Log::warning('Midtrans webhook signature tidak valid.', ['order_id' => $orderId]);

            return;
        }

        if (! $orderId) {
            return;
        }

        $invoice = SubscriptionInvoice::where('order_id', $orderId)->first();

        if (! $invoice) {
            Log::warning('Midtrans webhook untuk order_id yang tidak dikenal.', ['order_id' => $orderId]);

            return;
        }

        // Idempotency: an invoice that's already settled (paid/failed/
        // expired/canceled) is done — re-deliveries of the same webhook
        // event must not be re-applied.
        if ($invoice->status !== InvoiceStatus::Pending) {
            return;
        }

        $fraudStatus = $payload['fraud_status'] ?? null;

        match (true) {
            in_array($transactionStatus, ['capture', 'settlement'], true)
                && ($fraudStatus === null || $fraudStatus === 'accept')
                => $this->markPaid($invoice),

            $transactionStatus === 'pending' => null, // still waiting, nothing to do

            in_array($transactionStatus, ['deny', 'cancel'], true)
                => $this->markFailed($invoice, InvoiceStatus::Failed),

            $transactionStatus === 'expire'
                => $this->markFailed($invoice, InvoiceStatus::Expired),

            default => Log::info('Midtrans transaction_status tidak ditangani.', [
                'order_id' => $orderId,
                'transaction_status' => $transactionStatus,
            ]),
        };
    }

    /**
     * Mark the invoice as failed/expired and let the organization
     * owner(s) know, so they can retry checkout.
     */
    private function markFailed(SubscriptionInvoice $invoice, InvoiceStatus $status): void
    {
        $invoice->update(['status' => $status]);

        $invoice->organization->owners()->each(
            fn ($owner) => $owner->notify(new InvoicePaymentFailedNotification($invoice))
        );
    }

    /**
     * Mark the invoice paid and actually activate the plan it paid for.
     * This is the ONLY place a plan switch takes effect after checkout
     * — never trust the client-side "onSuccess" callback for this.
     */
    private function markPaid(SubscriptionInvoice $invoice): void
    {
        DB::transaction(function () use ($invoice) {
            $organization = Organization::whereKey($invoice->organization_id)->lockForUpdate()->firstOrFail();

            $invoice->update([
                'status' => InvoiceStatus::Paid,
                'paid_at' => now(),
            ]);

            $subscription = $organization->currentSubscription();
            $periodEnd = now()->addMonths($invoice->billing_period->months());

            if ($subscription) {
                $subscription->update([
                    'plan_id' => $invoice->plan_id,
                    'pending_plan_id' => null,
                    'status' => SubscriptionStatus::Active,
                    'max_stores_override' => null,
                    'max_owners_override' => null,
                    'trial_ends_at' => null,
                    'canceled_at' => null,
                    'current_period_start' => now(),
                    'current_period_end' => $periodEnd,
                ]);
            } else {
                $subscription = $organization->subscriptions()->create([
                    'plan_id' => $invoice->plan_id,
                    'status' => SubscriptionStatus::Active,
                    'current_period_start' => now(),
                    'current_period_end' => $periodEnd,
                ]);
            }

            $invoice->update(['subscription_id' => $subscription->id]);
        });
    }
}
