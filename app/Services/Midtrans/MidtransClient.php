<?php

namespace App\Services\Midtrans;

use App\Models\SubscriptionInvoice;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin wrapper around Midtrans' Snap API using Laravel's HTTP client —
 * deliberately NOT the `midtrans/midtrans-php` package, so this module
 * has zero extra Composer dependencies to install.
 *
 * Docs: https://docs.midtrans.com/reference/snap-token
 */
class MidtransClient
{
    private readonly string $serverKey;

    private readonly bool $isProduction;

    public function __construct(?string $serverKey = null, ?bool $isProduction = null)
    {
        $this->serverKey = $serverKey ?? (string) config('midtrans.server_key');
        $this->isProduction = $isProduction ?? (bool) config('midtrans.is_production');
    }

    /**
     * Create a Snap transaction for the given invoice and return the
     * snap token + redirect URL.
     *
     * @return array{token: string, redirect_url: string}
     */
    public function createSnapTransaction(SubscriptionInvoice $invoice, string $customerName, string $customerEmail): array
    {
        if (! $this->serverKey) {
            throw new RuntimeException('MIDTRANS_SERVER_KEY belum diset di .env.');
        }

        $response = Http::withBasicAuth($this->serverKey, '')
            ->acceptJson()
            ->post("{$this->snapBaseUrl()}/snap/v1/transactions", [
                'transaction_details' => [
                    'order_id' => $invoice->order_id,
                    'gross_amount' => (int) $invoice->amount,
                ],
                'customer_details' => [
                    'first_name' => $customerName,
                    'email' => $customerEmail,
                ],
                'item_details' => [[
                    'id' => $invoice->plan->code,
                    'price' => (int) $invoice->amount,
                    'quantity' => 1,
                    'name' => "Paket {$invoice->plan->name} ({$invoice->billing_period->label()})",
                ]],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Gagal membuat transaksi Midtrans: '.$response->body());
        }

        return [
            'token' => $response->json('token'),
            'redirect_url' => $response->json('redirect_url'),
        ];
    }

    /**
     * Verify the SHA512 signature Midtrans sends on every webhook
     * notification, so we never process a spoofed payload.
     *
     * signature = SHA512(order_id + status_code + gross_amount + ServerKey)
     */
    public function verifySignature(array $payload): bool
    {
        if (! $this->serverKey) {
            return false;
        }

        $orderId = $payload['order_id'] ?? '';
        $statusCode = $payload['status_code'] ?? '';
        $grossAmount = $payload['gross_amount'] ?? '';
        $signatureKey = $payload['signature_key'] ?? '';

        $expected = hash('sha512', $orderId.$statusCode.$grossAmount.$this->serverKey);

        return hash_equals($expected, (string) $signatureKey);
    }

    private function snapBaseUrl(): string
    {
        return $this->isProduction
            ? 'https://app.midtrans.com'
            : 'https://app.sandbox.midtrans.com';
    }
}
