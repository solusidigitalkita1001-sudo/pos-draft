<?php

namespace App\Http\Controllers\Organizations;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionInvoice;
use App\Services\Pdf\SubscriptionInvoicePdfGenerator;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationInvoiceController extends Controller
{
    /**
     * Riwayat invoice — semua subscription_invoices milik organization
     * user saat ini, terbaru dulu.
     */
    public function index(Request $request): Response
    {
        $organization = $request->user()->currentOrganization;

        abort_unless($organization, 403, 'Anda belum memiliki organization aktif.');

        $invoices = $organization->subscriptionInvoices()
            ->with('plan')
            ->latest()
            ->get()
            ->map(fn ($invoice) => [
                'orderId' => $invoice->order_id,
                'planName' => $invoice->plan->name,
                'amount' => $invoice->amount,
                'billingPeriod' => $invoice->billing_period->value,
                'billingPeriodLabel' => $invoice->billing_period->label(),
                'status' => $invoice->status->value,
                'statusLabel' => $invoice->status->label(),
                'paidAt' => $invoice->paid_at?->toISOString(),
                'createdAt' => $invoice->created_at->toISOString(),
            ]);

        return Inertia::render('organizations/invoices', [
            'invoices' => $invoices,
        ]);
    }

    /**
     * Download invoice history as CSV — mirrors ReportController's
     * streamDownload pattern used for POS reports.
     *
     * Named "download", not "export" — Wayfinder generates a JS
     * function literally named after this method, and `export` is a
     * reserved word in JavaScript that would break the generated file.
     */
    public function download(Request $request)
    {
        $organization = $request->user()->currentOrganization;

        abort_unless($organization, 403, 'Anda belum memiliki organization aktif.');

        $rows = $organization->subscriptionInvoices()
            ->with('plan')
            ->latest()
            ->get();

        $filename = 'invoice-'.$organization->slug.'-'.now()->format('YmdHis').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Order ID', 'Paket', 'Periode', 'Jumlah', 'Status', 'Dibayar Pada', 'Dibuat Pada']);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row->order_id,
                    $row->plan->name,
                    $row->billing_period->label(),
                    $row->amount,
                    $row->status->label(),
                    $row->paid_at?->format('Y-m-d H:i') ?? '-',
                    $row->created_at->format('Y-m-d H:i'),
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * Download a single invoice as a PDF (kuitansi/invoice document).
     * Scoped to the current user's organization — an invoice belonging
     * to another organization returns 404, never a permission error
     * that would leak whether the order_id exists at all.
     */
    public function pdf(Request $request, SubscriptionInvoice $invoice, SubscriptionInvoicePdfGenerator $generator)
    {
        $organization = $request->user()->currentOrganization;

        abort_unless($organization && $invoice->organization_id === $organization->id, 404);

        $pdf = $generator->generate($invoice);

        $filename = "{$invoice->order_id}.pdf";

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
