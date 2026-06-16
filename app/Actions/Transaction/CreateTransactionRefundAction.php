<?php

namespace App\Actions\Transaction;

use App\Models\Team;
use App\Models\Transaction;
use App\Models\TransactionRefund;
use App\Models\User;
use App\Support\DocumentNumberGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateTransactionRefundAction
{
    public function execute(Team $team, User $user, array $data): TransactionRefund
    {
        return DB::transaction(function () use ($team, $user, $data) {
            $transaction = Transaction::where('team_id', $team->id)
                ->whereKey($data['transaction_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if ($transaction->status !== Transaction::STATUS_COMPLETED) {
                throw ValidationException::withMessages([
                    'transaction_id' => 'Refund hanya dapat dibuat untuk transaksi selesai.',
                ]);
            }

            $amount = (float) $data['amount'];
            $refunded = (float) $transaction->refunds()
                ->where('status', TransactionRefund::STATUS_APPROVED)
                ->sum('amount');
            $remaining = max((float) $transaction->grand_total - $refunded, 0);

            if ($amount > $remaining) {
                throw ValidationException::withMessages([
                    'amount' => 'Nominal refund melebihi sisa transaksi yang dapat direfund.',
                ]);
            }

            $status = $data['status'] ?? TransactionRefund::STATUS_APPROVED;

            return TransactionRefund::create([
                'team_id' => $team->id,
                'transaction_id' => $transaction->id,
                'user_id' => $user->id,
                'refund_number' => DocumentNumberGenerator::generate('RFN', 'transaction_refunds', 'refund_number', $team->id),
                'amount' => $amount,
                'method' => $data['method'],
                'status' => $status,
                'reason' => $data['reason'] ?? null,
                'refunded_at' => $status === TransactionRefund::STATUS_APPROVED ? now() : null,
            ])->load(['transaction:id,invoice_number,customer_name,grand_total', 'user:id,name']);
        });
    }

}