<?php

namespace App\Actions\Pos;

use App\Actions\ProductStock\AdjustProductStockAction;
use App\Models\Product;
use App\Models\ProductStockMovement;
use App\Models\Team;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VoidTransactionAction
{
    public function __construct(private AdjustProductStockAction $adjustProductStockAction) {}

    /**
     * Void a transaction and return every product it deducted stock for
     * back to inventory.
     *
     * Stock is reversed by replaying the EXACT `ProductStockMovement`
     * rows this transaction created at checkout time (matched via
     * reference_type/reference_id), rather than recomputing quantities
     * from `transaction_items` — line items for packages/promotions
     * don't carry a single product_id (one line can touch several
     * products), so the stock movement log is the only place that has
     * the precise per-product breakdown.
     */
    public function execute(Team $team, Transaction $transaction, User $user, ?string $reason = null): Transaction
    {
        if ($transaction->team_id !== $team->id) {
            abort(404);
        }

        if ($transaction->status === Transaction::STATUS_VOID) {
            throw ValidationException::withMessages([
                'transaction' => 'Transaksi ini sudah dibatalkan sebelumnya.',
            ]);
        }

        return DB::transaction(function () use ($team, $transaction, $user, $reason) {
            $outboundMovements = ProductStockMovement::where('reference_type', Transaction::class)
                ->where('reference_id', $transaction->id)
                ->where('team_id', $team->id)
                ->where('type', ProductStockMovement::TYPE_OUT)
                ->get();

            foreach ($outboundMovements as $movement) {
                $product = Product::where('team_id', $team->id)->find($movement->product_id);

                // Produk mungkin sudah dihapus sejak transaksi dibuat —
                // lewati saja, tidak ada stok untuk dikembalikan.
                if (! $product) {
                    continue;
                }

                $this->adjustProductStockAction->execute($product, $user, [
                    'type' => ProductStockMovement::TYPE_IN,
                    'quantity' => $movement->quantity,
                    'note' => "Pembatalan transaksi {$transaction->invoice_number}",
                    'reference_type' => Transaction::class,
                    'reference_id' => $transaction->id,
                ]);
            }

            $transaction->update([
                'status' => Transaction::STATUS_VOID,
                'void_reason' => $reason,
                'voided_at' => now(),
                'voided_by' => $user->id,
            ]);

            return $transaction->fresh();
        });
    }
}
