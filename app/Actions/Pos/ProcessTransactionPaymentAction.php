<?php

namespace App\Actions\Pos;

use App\Models\Team;
use App\Models\Transaction;
use App\Support\PaymentStatusResolver;
use Illuminate\Validation\ValidationException;

class ProcessTransactionPaymentAction
{
    /**
     * Tambah pembayaran ke transaksi yang sudah ada (melunasi transaksi
     * yang statusnya partial/unpaid). Menerima top-up SEBAGIAN juga —
     * tidak wajib langsung melunasi semua sisa tagihan dalam satu kali
     * bayar, konsisten dengan alur checkout awal yang sekarang juga
     * menerima partial payment.
     */
    public function execute(Team $team, Transaction $transaction, array $data): Transaction
    {
        if ($transaction->team_id !== $team->id) {
            abort(404);
        }

        if ($transaction->status === Transaction::STATUS_VOID) {
            throw ValidationException::withMessages([
                'transaction' => 'Transaksi yang dibatalkan tidak dapat dibayar.',
            ]);
        }

        $receivedAmount = (float) $data['paid_amount'];
        $existingPaidAmount = (float) $transaction->paid_amount;
        $grandTotal = (float) $transaction->grand_total;
        $remainingAmount = max($grandTotal - $existingPaidAmount, 0);

        $this->validateReceivedAmount($receivedAmount, $remainingAmount);

        $newPaidAmount = $existingPaidAmount + $receivedAmount;
        ['status' => $status, 'paymentStatus' => $paymentStatus] = PaymentStatusResolver::resolve($grandTotal, $newPaidAmount);

        $transaction->update([
            'status' => $status,
            'payment_status' => $paymentStatus,
            'payment_method' => $data['payment_method'],
            'paid_amount' => $newPaidAmount,
            'change_amount' => max($receivedAmount - $remainingAmount, 0),
            'paid_at' => now(),
        ]);

        return $transaction->refresh();
    }

    private function validateReceivedAmount(float $receivedAmount, float $remainingAmount): void
    {
        if ($receivedAmount <= 0) {
            throw ValidationException::withMessages([
                'paid_amount' => 'Jumlah bayar wajib diisi.',
            ]);
        }

        if ($remainingAmount <= 0) {
            throw ValidationException::withMessages([
                'paid_amount' => 'Transaksi ini sudah lunas.',
            ]);
        }
    }
}
