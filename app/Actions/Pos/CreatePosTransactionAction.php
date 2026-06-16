<?php

namespace App\Actions\Pos;

use App\Actions\ProductStock\AdjustProductStockAction;
use App\Models\Product;
use App\Models\ProductPackage;
use App\Models\ProductPromotion;
use App\Models\ProductStockMovement;
use App\Models\Team;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;
use App\Models\Voucher;
use App\Support\DocumentNumberGenerator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class CreatePosTransactionAction
{
    public function __construct(
        private AdjustProductStockAction $adjustProductStockAction,
    ) {}

    public function execute(Team $team, User $cashier, array $data): Transaction
    {
        return DB::transaction(function () use ($team, $cashier, $data) {
            $cartItems = collect($data['items'])
                ->map(fn (array $item) => [
                    'item_type' => $item['item_type'] ?? TransactionItem::ITEM_TYPE_PRODUCT,
                    'item_id' => (int) ($item['item_id'] ?? $item['product_id']),
                    'quantity' => (int) $item['quantity'],
                ])
                ->groupBy(fn (array $item) => "{$item['item_type']}:{$item['item_id']}")
                ->map(fn (Collection $items) => [
                    'item_type' => $items->first()['item_type'],
                    'item_id' => $items->first()['item_id'],
                    'quantity' => $items->sum('quantity'),
                ])
                ->values();

            $subtotal = 0.0;
            $items = [];
            $stockRequirements = [];

            $products = $this->resolveProducts($team, $cartItems);
            $packages = $this->resolvePackages($team, $cartItems);
            $promotions = $this->resolvePromotions($team, $cartItems);

            foreach ($cartItems as $item) {
                $saleItem = match ($item['item_type']) {
                    TransactionItem::ITEM_TYPE_PRODUCT => $this->buildProductLine($products, $item),
                    TransactionItem::ITEM_TYPE_PACKAGE => $this->buildPackageLine($packages, $item),
                    TransactionItem::ITEM_TYPE_PROMOTION => $this->buildPromotionLine($promotions, $item),
                    default => throw ValidationException::withMessages([
                        'items' => 'Jenis item transaksi tidak valid.',
                    ]),
                };

                foreach ($saleItem['stock_requirements'] as $productId => $quantity) {
                    $stockRequirements[$productId] = ($stockRequirements[$productId] ?? 0) + $quantity;
                }

                $lineTotal = $saleItem['unit_price'] * $item['quantity'];
                $subtotal += $lineTotal;

                $items[] = [
                    'item_type' => $item['item_type'],
                    'item_id' => $item['item_id'],
                    'product_id' => $saleItem['product_id'],
                    'name' => $saleItem['name'],
                    'sku' => $saleItem['sku'],
                    'unit_price' => $saleItem['unit_price'],
                    'quantity' => $item['quantity'],
                    'line_total' => $lineTotal,
                ];
            }

            $stockProducts = $this->lockAndValidateStock($team, $stockRequirements);
            $voucher = $this->resolveVoucher($team, $data['voucher_code'] ?? null, $subtotal);
            $discountTotal = $voucher?->discountFor($subtotal) ?? 0.0;
            $grandTotal = max($subtotal - $discountTotal, 0);
            $paidAmount = (float) $data['paid_amount'];
            $this->validatePaidAmount($paidAmount, $grandTotal);

            $changeAmount = max($paidAmount - $grandTotal, 0);

            $transaction = Transaction::create([
                'team_id' => $team->id,
                'user_id' => $cashier->id,
                'voucher_id' => $voucher?->id,
                'invoice_number' => DocumentNumberGenerator::generate('POS', 'transactions', 'invoice_number', $team->id),
                'customer_name' => $data['customer_name'] ?? null,
                'status' => Transaction::STATUS_COMPLETED,
                'payment_status' => Transaction::PAYMENT_STATUS_PAID,
                'payment_method' => $data['payment_method'],
                'subtotal' => $subtotal,
                'discount_total' => $discountTotal,
                'tax_total' => 0,
                'grand_total' => $grandTotal,
                'paid_amount' => $paidAmount,
                'change_amount' => $changeAmount,
                'note' => $data['note'] ?? null,
                'paid_at' => now(),
            ]);

            foreach ($items as $item) {
                $transaction->items()->create($this->transactionItemPayload($item));
            }

            foreach ($stockRequirements as $productId => $quantity) {
                $product = $stockProducts->get($productId);
                $this->adjustProductStockAction->execute($product, $cashier, [
                    'type' => ProductStockMovement::TYPE_OUT,
                    'quantity' => $quantity,
                    'note' => "Penjualan POS {$transaction->invoice_number}",
                    'reference_type' => Transaction::class,
                    'reference_id' => $transaction->id,
                ]);
            }

            if ($voucher) {
                $voucher->increment('used_count');
            }

            return $transaction->load(['items', 'cashier:id,name', 'voucher:id,code,name']);
        });
    }

    private function resolveProducts(Team $team, Collection $cartItems): Collection
    {
        $ids = $cartItems
            ->where('item_type', TransactionItem::ITEM_TYPE_PRODUCT)
            ->pluck('item_id');

        return Product::where('team_id', $team->id)
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');
    }

    private function validatePaidAmount(float $paidAmount, float $grandTotal): void
    {
        if ($paidAmount <= 0) {
            throw ValidationException::withMessages([
                'paid_amount' => 'Jumlah bayar wajib diisi.',
            ]);
        }

        if ($paidAmount < $grandTotal) {
            throw ValidationException::withMessages([
                'paid_amount' => 'Nominal pembayaran kurang dari total transaksi.',
            ]);
        }
    }

    private function transactionItemPayload(array $item): array
    {
        $payload = [
            'product_id' => $item['product_id'],
            'product_name' => $item['name'],
            'product_sku' => $item['sku'],
            'unit_price' => $item['unit_price'],
            'quantity' => $item['quantity'],
            'discount_total' => 0,
            'line_total' => $item['line_total'],
        ];

        if ($this->transactionItemsHaveSaleItemColumns()) {
            $payload['item_type'] = $item['item_type'];
            $payload['item_reference_id'] = $item['item_id'];
        }

        return $payload;
    }

    private function transactionItemsHaveSaleItemColumns(): bool
    {
        static $hasColumns = null;

        if ($hasColumns === null) {
            $hasColumns = Schema::hasColumn('transaction_items', 'item_type')
                && Schema::hasColumn('transaction_items', 'item_reference_id');
        }

        return $hasColumns;
    }

    private function resolvePackages(Team $team, Collection $cartItems): Collection
    {
        $ids = $cartItems
            ->where('item_type', TransactionItem::ITEM_TYPE_PACKAGE)
            ->pluck('item_id');

        return ProductPackage::where('team_id', $team->id)
            ->whereIn('id', $ids)
            ->with('items.product:id,team_id,name,sku,is_active')
            ->get()
            ->keyBy('id');
    }

    private function resolvePromotions(Team $team, Collection $cartItems): Collection
    {
        $ids = $cartItems
            ->where('item_type', TransactionItem::ITEM_TYPE_PROMOTION)
            ->pluck('item_id');

        return ProductPromotion::where('team_id', $team->id)
            ->whereIn('id', $ids)
            ->with([
                'triggers.product:id,team_id,name,sku,price,is_active',
                'rewards.product:id,team_id,name,sku,is_active',
            ])
            ->get()
            ->keyBy('id');
    }

    private function buildProductLine(Collection $products, array $item): array
    {
        /** @var Product|null $product */
        $product = $products->get($item['item_id']);

        if (! $product) {
            throw ValidationException::withMessages([
                'items' => 'Sebagian produk tidak ditemukan pada tim ini.',
            ]);
        }

        if (! $product->is_active) {
            throw ValidationException::withMessages([
                'items' => "Produk '{$product->name}' sedang tidak aktif.",
            ]);
        }

        return [
            'product_id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'unit_price' => (float) $product->price,
            'stock_requirements' => [$product->id => $item['quantity']],
        ];
    }

    private function buildPackageLine(Collection $packages, array $item): array
    {
        /** @var ProductPackage|null $package */
        $package = $packages->get($item['item_id']);

        if (! $package) {
            throw ValidationException::withMessages([
                'items' => 'Sebagian paket produk tidak ditemukan pada tim ini.',
            ]);
        }

        if (! $package->is_active) {
            throw ValidationException::withMessages([
                'items' => "Paket '{$package->name}' sedang tidak aktif.",
            ]);
        }

        if ($package->items->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => "Paket '{$package->name}' belum memiliki komponen produk.",
            ]);
        }

        $requirements = [];
        foreach ($package->items as $packageItem) {
            if (! $packageItem->product?->is_active) {
                throw ValidationException::withMessages([
                    'items' => "Komponen paket '{$package->name}' tidak aktif atau tidak ditemukan.",
                ]);
            }

            $requirements[$packageItem->product_id] = ($requirements[$packageItem->product_id] ?? 0)
                + ((int) $packageItem->quantity * $item['quantity']);
        }

        return [
            'product_id' => null,
            'name' => $package->name,
            'sku' => $package->sku,
            'unit_price' => (float) $package->base_price,
            'stock_requirements' => $requirements,
        ];
    }

    private function buildPromotionLine(Collection $promotions, array $item): array
    {
        /** @var ProductPromotion|null $promotion */
        $promotion = $promotions->get($item['item_id']);

        if (! $promotion) {
            throw ValidationException::withMessages([
                'items' => 'Sebagian promosi produk tidak ditemukan pada tim ini.',
            ]);
        }

        if (! $promotion->is_active) {
            throw ValidationException::withMessages([
                'items' => "Promosi '{$promotion->name}' sedang tidak aktif.",
            ]);
        }

        if ($promotion->triggers->isEmpty() || $promotion->rewards->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => "Promosi '{$promotion->name}' belum memiliki syarat atau reward.",
            ]);
        }

        $requirements = [];
        $unitPrice = 0.0;

        foreach ($promotion->triggers as $trigger) {
            if (! $trigger->product?->is_active) {
                throw ValidationException::withMessages([
                    'items' => "Produk syarat promosi '{$promotion->name}' tidak aktif atau tidak ditemukan.",
                ]);
            }

            $requirements[$trigger->product_id] = ($requirements[$trigger->product_id] ?? 0)
                + ((int) $trigger->min_quantity * $item['quantity']);
            $unitPrice += (float) $trigger->product->price * (int) $trigger->min_quantity;
        }

        foreach ($promotion->rewards as $reward) {
            if (! $reward->product?->is_active) {
                throw ValidationException::withMessages([
                    'items' => "Produk reward promosi '{$promotion->name}' tidak aktif atau tidak ditemukan.",
                ]);
            }

            $requirements[$reward->product_id] = ($requirements[$reward->product_id] ?? 0)
                + ((int) $reward->quantity * $item['quantity']);
            $unitPrice += (float) $reward->extra_charge * (int) $reward->quantity;
        }

        return [
            'product_id' => null,
            'name' => $promotion->name,
            'sku' => 'PROMO-'.$promotion->id,
            'unit_price' => $unitPrice,
            'stock_requirements' => $requirements,
        ];
    }

    private function lockAndValidateStock(Team $team, array $stockRequirements): Collection
    {
        if ($stockRequirements === []) {
            return collect();
        }

        $products = Product::where('team_id', $team->id)
            ->whereIn('id', array_keys($stockRequirements))
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        if ($products->count() !== count($stockRequirements)) {
            throw ValidationException::withMessages([
                'items' => 'Sebagian produk komponen transaksi tidak ditemukan pada tim ini.',
            ]);
        }

        foreach ($stockRequirements as $productId => $quantity) {
            /** @var Product $product */
            $product = $products->get($productId);

            if (! $product->is_active) {
                throw ValidationException::withMessages([
                    'items' => "Produk '{$product->name}' sedang tidak aktif.",
                ]);
            }

            if ($product->stock < $quantity) {
                throw ValidationException::withMessages([
                    'items' => "Stok '{$product->name}' tidak mencukupi.",
                ]);
            }
        }

        return $products;
    }

    private function resolveVoucher(Team $team, ?string $code, float $subtotal): ?Voucher
    {
        if (! $code) {
            return null;
        }

        $voucher = Voucher::where('team_id', $team->id)
            ->where('code', strtoupper($code))
            ->first();

        if (! $voucher || ! $voucher->isUsableFor($subtotal)) {
            throw ValidationException::withMessages([
                'voucher_code' => 'Voucher tidak valid atau tidak memenuhi syarat transaksi.',
            ]);
        }

        return $voucher;
    }


}