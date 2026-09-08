<?php

namespace App\Http\Controllers\Organizations;

use App\Actions\Organizations\CreateCheckoutInvoiceAction;
use App\Enums\BillingPeriod;
use App\Enums\PlanCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizations\CreateCheckoutRequest;
use App\Models\Plan;
use App\Models\SubscriptionInvoice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class CheckoutController extends Controller
{
    /**
     * Create a pending invoice + Snap token, then redirect to the
     * checkout page that actually opens the Midtrans payment popup.
     */
    public function store(CreateCheckoutRequest $request, CreateCheckoutInvoiceAction $createInvoice): RedirectResponse
    {
        $user = $request->user();
        $organization = $user->currentOrganization;

        abort_unless($organization, 403, 'Anda belum memiliki organization aktif.');
        abort_unless($organization->ownedBy($user), 403, 'Hanya owner yang dapat mengubah paket.');

        $plan = Plan::findByCode(PlanCode::from($request->validated('plan_code')));

        abort_unless($plan, 404, 'Paket tidak ditemukan.');

        try {
            $invoice = $createInvoice->execute(
                organization: $organization,
                plan: $plan,
                payer: $user,
                period: BillingPeriod::from($request->validated('billing_period')),
            );
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return to_route('organizations.checkout.show', ['invoice' => $invoice->order_id]);
    }

    /**
     * Checkout page — loads Midtrans Snap.js and opens the payment popup
     * for the given invoice.
     */
    public function show(Request $request, SubscriptionInvoice $invoice): Response
    {
        abort_unless($invoice->organization_id === $request->user()->currentOrganization?->id, 403);

        return Inertia::render('organizations/checkout', [
            'invoice' => [
                'orderId' => $invoice->order_id,
                'status' => $invoice->status->value,
                'snapToken' => $invoice->snap_token,
                'planName' => $invoice->plan->name,
                'amount' => $invoice->amount,
            ],
            'midtrans' => [
                'clientKey' => config('midtrans.client_key'),
                'isProduction' => (bool) config('midtrans.is_production'),
            ],
        ]);
    }
}
