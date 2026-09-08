<?php

namespace App\Services\Pdf;

use App\Enums\InvoiceStatus;
use App\Models\SubscriptionInvoice;
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Thin wrapper around Dompdf — this is the one feature in the app that
 * genuinely needs a real PDF renderer server-side (unlike Midtrans,
 * there's no HTTP API shortcut for "render HTML to PDF bytes"). Kept
 * as a small, explicit wrapper rather than pulling in the Laravel
 * facade package (`barryvdh/laravel-dompdf`) so there's no extra
 * config surface — just this one class.
 *
 * Requires `composer require dompdf/dompdf` — see docs/25-invoice-pdf-export.md.
 */
class SubscriptionInvoicePdfGenerator
{
    /**
     * Render a subscription invoice as PDF bytes.
     */
    public function generate(SubscriptionInvoice $invoice): string
    {
        $invoice->loadMissing(['plan', 'organization']);

        $html = view('pdf.subscription-invoice', [
            'invoice' => $invoice,
            'organization' => $invoice->organization,
            'title' => $invoice->status === InvoiceStatus::Paid ? 'Kuitansi' : 'Invoice',
            'statusColor' => $this->statusColor($invoice->status),
            'formattedAmount' => $this->formatRupiah((float) $invoice->amount),
        ])->render();

        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'Helvetica');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    private function statusColor(InvoiceStatus $status): string
    {
        return match ($status) {
            InvoiceStatus::Paid => '#16a34a',
            InvoiceStatus::Pending => '#d97706',
            default => '#dc2626',
        };
    }

    private function formatRupiah(float $amount): string
    {
        return 'Rp'.number_format($amount, 0, ',', '.');
    }
}
