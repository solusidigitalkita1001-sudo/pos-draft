<?php

namespace App\Actions\Transaction;

use App\Actions\ProductStock\AdjustProductStockAction;
use App\Models\ProductStockMovement;
use App\Models\Team;
use App\Models\TransactionItem;
use App\Models\TransactionReturn;
use App\Models\User;
use App\Support\DocumentNumberGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateTransactionReturnAction
{
    public function __construct(
        private AdjustProductStockAction $adjustProductStockAction,
    ) {}

    public function execute(Team $team, User $user, array $data): TransactionReturn
    {
        return DB::transaction(function () use ($team, $user, $data) {
            $item = TransactionItem::query()
                ->whereKey($data['transaction_item_id'])
                ->whereHas('transaction', fn ($query) => $query->where('team_id', $team->id))
                ->with(['transaction:id,team_id,invoice_number,customer_name', 'product'])
                ->lockForUpdate()
                ->firstOrFail();

            if (! $item->product_id) {
                throw ValidationException::withMessages([
                    'transaction_item_id' => 'Return stok hanya tersedia untuk item produk.',
                ]);
            }

            $returnedQuantity = (int) $item->returns()
                ->where('status', '!=', TransactionReturn::STATUS_REJECTED)
                ->sum('quantity');
            $remainingQuantity = max($item->quantity - $returnedQuantity, 0);
            $quantity = (int) $data['quantity'];

            if ($quantity > $remainingQuantity) {
                throw ValidationException::withMessages([
                    'quantity' => 'Jumlah return melebihi sisa item yang dapat direturn.',
                ]);
            }

            $status = $data['status'] ?? TransactionReturn::STATUS_APPROVED;
            $refundAmount = isset($data['refund_amount'])
                ? (float) $data['refund_amount']
                : $this->defaultRefundAmount($item, $quantity);

            $return = TransactionReturn::create([
                'team_id' => $team->id,
                'transaction_id' => $item->transaction_id,
                'transaction_item_id' => $item->id,
                'product_id' => $item->product_id,
                'user_id' => $user->id,
                'return_number' => DocumentNumberGenerator::generate('RTN', 'transaction_returns', 'return_number', $team->id),
                'quantity' => $quantity,
                'refund_amount' => $refundAmount,
                'restock' => (bool) ($data['restock'] ?? true),
                'status' => $status,
                'reason' => $data['reason'] ?? null,
                'returned_at' => $status === TransactionReturn::STATUS_APPROVED ? now() : null,
            ]);

            if ($return->restock && $return->status === TransactionReturn::STATUS_APPROVED && $item->product) {
                $this->adjustProductStockAction->execute($item->product, $user, [
                    'type' => ProductStockMovement::TYPE_IN,
                    'quantity' => $return->quantity,
                    'note' => "Return barang {$return->return_number}",
                    'reference_type' => TransactionReturn::class,
                    'reference_id' => $return->id,
                ]);
            }

            return $return->load([
                'transaction:id,invoice_number,customer_name',
                'transactionItem:id,product_name,product_sku,unit_price,quantity',
                'product:id,name,sku,stock',
                'user:id,name',
            ]);
        });
    }

    private function defaultRefundAmount(TransactionItem $item, int $quantity): float
    {
        $lineTotal = (float) $item->line_total;
        $unitNet = $item->quantity > 0 ? $lineTotal / $item->quantity : 0;

        return $unitNet * $quantity;
    }

}