<?php

namespace App\Support;

use App\Models\Transaction;

/**
 * Menentukan payment_status & status transaksi berdasarkan grand_total
 * vs jumlah yang sudah dibayar. Logic ini awalnya cuma ada di
 * TransactionController (endpoint edit transaksi manual oleh owner) —
 * dipindah ke sini supaya CreatePosTransactionAction dan
 * ProcessTransactionPaymentAction (alur checkout POS yang sebenarnya
 * dipakai kasir) pakai aturan yang SAMA, bukan aturan sendiri yang
 * berbeda (dan sempat malah memblokir partial payment sama sekali).
 */
class PaymentStatusResolver
{
    /**
     * @return array{status: string, paymentStatus: string}
     */
    public static function resolve(float $grandTotal, float $paidAmount): array
    {
        $paymentStatus = match (true) {
            $paidAmount <= 0 => Transaction::PAYMENT_STATUS_UNPAID,
            $paidAmount < $grandTotal => Transaction::PAYMENT_STATUS_PARTIAL,
            default => Transaction::PAYMENT_STATUS_PAID,
        };

        // Transaksi yang belum dibayar sama sekali statusnya PENDING
        // (belum "selesai"). Sudah dibayar sebagian atau lunas dianggap
        // COMPLETED — barang sudah keluar/stok sudah dipotong, transaksi
        // sudah terjadi, cuma piutangnya belum lunas.
        $status = $paymentStatus === Transaction::PAYMENT_STATUS_UNPAID
            ? Transaction::STATUS_PENDING
            : Transaction::STATUS_COMPLETED;

        return ['status' => $status, 'paymentStatus' => $paymentStatus];
    }
}
