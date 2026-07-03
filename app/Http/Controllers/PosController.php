<?php

namespace App\Http\Controllers;

use App\Actions\Pos\CreatePosTransactionAction;
use App\Actions\Pos\ProcessTransactionPaymentAction;
use App\Http\Requests\Pos\CreatePosTransactionRequest;
use App\Http\Requests\Pos\ProcessPaymentRequest;
use App\Http\Requests\Pos\SearchProductsRequest;
use App\Http\Requests\Pos\ValidateVoucherRequest;
use App\Models\Product;
use App\Models\ProductPackage;
use App\Models\ProductPromotion;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\Voucher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class PosController extends Controller
{
    public function __construct(
        private CreatePosTransactionAction $createPosTransactionAction,
        private ProcessTransactionPaymentAction $processTransactionPaymentAction,
    ) {}

    public function index(Request $request): Response
    {
        $team = $request->user()->currentTeam;
        $authUser = $request->user();

        abort_unless($team->members()->where('users.id', $authUser->id)->exists(), 403);

        setPermissionsTeamId($team->id);

        $products = $team->products()
            ->with('category:id,name')
            ->where('is_active', true)
            ->where('stock', '>', 0)
            ->orderBy('name')
            ->limit(40)
            ->get(['id', 'category_id', 'sku', 'name', 'price', 'stock', 'min_stock']);

        $packages = $team->productPackages()
            ->with(['category:id,name', 'items.product:id,name,sku,stock,is_active'])
            ->where('is_active', true)
            ->orderBy('name')
            ->limit(40)
            ->get(['id', 'team_id', 'category_id', 'sku', 'name', 'base_price', 'is_active']);

        $promotions = $team->productPromotions()
            ->where('is_active', true)
            ->with([
                'triggers.product:id,name,sku,price,stock,is_active',
                'rewards.product:id,name,sku,stock,is_active',
            ])
            ->orderBy('name')
            ->limit(40)
            ->get(['id', 'team_id', 'name', 'type', 'is_active', 'starts_at', 'ends_at']);

        $recentTransactions = $team->transactions()
            ->with([
                'cashier:id,name',
                'items:id,transaction_id,product_name,product_sku,unit_price,quantity,discount_total,line_total',
                'voucher:id,code,name,type,value',
            ])
            ->latest()
            ->limit(8)
            ->get([
                'id',
                'voucher_id',
                'invoice_number',
                'customer_name',
                'status',
                'payment_status',
                'payment_method',
                'subtotal',
                'discount_total',
                'grand_total',
                'paid_amount',
                'change_amount',
                'created_at',
                'user_id',
            ]);

        $vouchers = $team->vouchers()
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>=', now());
            })
            ->where(function ($query) {
                $query->whereNull('usage_limit')
                    ->orWhereColumn('used_count', '<', 'usage_limit');
            })
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type', 'value', 'min_purchase', 'max_discount']);

        return Inertia::render('pos/index', [
            'products' => $this->formatSaleItems($products, $packages, $promotions)->take(40)->values(),
            'recentTransactions' => $recentTransactions,
            'vouchers' => $vouchers,
            'teamSlug' => $team->slug,
            'paymentMethods' => [
                ['value' => 'cash', 'label' => 'Tunai'],
                ['value' => 'qris', 'label' => 'QRIS'],
                ['value' => 'card', 'label' => 'Kartu'],
                ['value' => 'transfer', 'label' => 'Transfer'],
            ],
            'canApplyVoucher' => $authUser->canOnCurrentTeam('voucher.apply'),
        ]);
    }

    public function searchProducts(SearchProductsRequest $request): JsonResponse
    {
        $team = $request->user()->currentTeam;
        $search = trim((string) $request->validated('search', ''));

        $products = Product::where('team_id', $team->id)
            ->with('category:id,name')
            ->where('is_active', true)
            ->where('stock', '>', 0)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->limit(30)
            ->get(['id', 'category_id', 'sku', 'name', 'price', 'stock', 'min_stock']);

        $packages = ProductPackage::where('team_id', $team->id)
            ->with(['category:id,name', 'items.product:id,name,sku,stock,is_active'])
            ->where('is_active', true)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%")
                        ->orWhereHas('category', fn ($query) => $query->where('name', 'like', "%{$search}%"));
                });
            })
            ->orderBy('name')
            ->limit(30)
            ->get(['id', 'team_id', 'category_id', 'sku', 'name', 'base_price', 'is_active']);

        $promotions = ProductPromotion::where('team_id', $team->id)
            ->where('is_active', true)
            ->with([
                'triggers.product:id,name,sku,price,stock,is_active',
                'rewards.product:id,name,sku,stock,is_active',
            ])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('type', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->limit(30)
            ->get(['id', 'team_id', 'name', 'type', 'is_active', 'starts_at', 'ends_at']);

        return response()->json([
            'products' => $this->formatSaleItems($products, $packages, $promotions)->take(30)->values(),
        ]);
    }

    public function validateVoucher(ValidateVoucherRequest $request): JsonResponse
    {
        $team = $request->user()->currentTeam;
        $validated = $request->validated();
        $subtotal = (float) $validated['subtotal'];

        $voucher = Voucher::where('team_id', $team->id)
            ->where('code', strtoupper($validated['voucher_code']))
            ->first();

        if (! $voucher || ! $voucher->isUsableFor($subtotal)) {
            return response()->json([
                'valid' => false,
                'message' => 'Voucher tidak valid atau tidak memenuhi syarat transaksi.',
            ], 422);
        }

        return response()->json([
            'valid' => true,
            'voucher' => $voucher->only(['id', 'code', 'name', 'type', 'value']),
            'discount_total' => $voucher->discountFor($subtotal),
            'message' => "Voucher {$voucher->code} berhasil digunakan.",
        ]);
    }

    public function createTransaction(CreatePosTransactionRequest $request)
    {
        $team = $request->user()->currentTeam;
        $authUser = $request->user();

        abort_unless($team->members()->where('users.id', $authUser->id)->exists(), 403);

        setPermissionsTeamId($team->id);

        $transaction = $this->createPosTransactionAction->execute($team, $authUser, $request->validated());

        Inertia::flash('success', "Transaksi {$transaction->invoice_number} berhasil dibuat.");

        return back();
    }

    public function processPayment(ProcessPaymentRequest $request)
    {
        $team = $request->user()->currentTeam;
        $transaction = Transaction::where('team_id', $team->id)
            ->where('id', $request->route('transaction'))
            ->firstOrFail();

        $this->processTransactionPaymentAction->execute($team, $transaction, $request->validated());

        Inertia::flash('success', "Pembayaran {$transaction->invoice_number} berhasil diperbarui.");

        return back();
    }

    private function formatSaleItems(Collection $products, Collection $packages, Collection $promotions): Collection
    {
        return $products
            ->map(fn (Product $product) => $this->formatProductForPos($product))
            ->concat($packages->map(fn (ProductPackage $package) => $this->formatPackageForPos($package)))
            ->concat($promotions->map(fn (ProductPromotion $promotion) => $this->formatPromotionForPos($promotion)))
            ->filter(fn (array $item) => $item['stock'] > 0)
            ->sortBy([
                ['item_type', 'asc'],
                ['name', 'asc'],
            ])
            ->values();
    }

    private function formatProductForPos(Product $product): array
    {
        return [
            'id' => $product->id,
            'item_id' => $product->id,
            'item_type' => TransactionItem::ITEM_TYPE_PRODUCT,
            'category_id' => $product->category_id,
            'sku' => $product->sku,
            'name' => $product->name,
            'price' => (string) $product->price,
            'stock' => (int) $product->stock,
            'min_stock' => (int) $product->min_stock,
            'category' => $product->category,
        ];
    }

    private function formatPackageForPos(ProductPackage $package): array
    {
        return [
            'id' => $package->id,
            'item_id' => $package->id,
            'item_type' => TransactionItem::ITEM_TYPE_PACKAGE,
            'category_id' => $package->category_id,
            'sku' => $package->sku,
            'name' => $package->name,
            'price' => (string) $package->base_price,
            'stock' => $this->availablePackageStock($package),
            'min_stock' => 0,
            'category' => $package->category,
        ];
    }

    private function formatPromotionForPos(ProductPromotion $promotion): array
    {
        return [
            'id' => $promotion->id,
            'item_id' => $promotion->id,
            'item_type' => TransactionItem::ITEM_TYPE_PROMOTION,
            'category_id' => null,
            'sku' => 'PROMO-'.$promotion->id,
            'name' => $promotion->name,
            'price' => (string) $this->promotionUnitPrice($promotion),
            'stock' => $this->availablePromotionStock($promotion),
            'min_stock' => 0,
            'category' => ['id' => null, 'name' => 'Promosi'],
        ];
    }

    private function availablePackageStock(ProductPackage $package): int
    {
        if ($package->items->isEmpty()) {
            return 0;
        }

        if ($package->items->contains(fn ($item) => ! $item->product?->is_active || $item->quantity <= 0)) {
            return 0;
        }

        return (int) $package->items
            ->groupBy('product_id')
            ->map(function (Collection $items) {
                $product = $items->first()->product;
                $quantity = $items->sum(fn ($item) => (int) $item->quantity);

                return intdiv((int) $product->stock, $quantity);
            })
            ->min();
    }

    private function availablePromotionStock(ProductPromotion $promotion): int
    {
        $requirements = $promotion->triggers
            ->map(fn ($trigger) => ['product' => $trigger->product, 'quantity' => (int) $trigger->min_quantity])
            ->concat($promotion->rewards->map(fn ($reward) => ['product' => $reward->product, 'quantity' => (int) $reward->quantity]));

        if ($requirements->isEmpty()) {
            return 0;
        }

        if ($requirements->contains(fn (array $requirement) => ! $requirement['product']?->is_active || $requirement['quantity'] <= 0)) {
            return 0;
        }

        return (int) $requirements
            ->groupBy(fn (array $requirement) => $requirement['product']->id)
            ->map(function (Collection $requirements) {
                $product = $requirements->first()['product'];
                $quantity = $requirements->sum('quantity');

                return intdiv((int) $product->stock, $quantity);
            })
            ->min();
    }

    private function promotionUnitPrice(ProductPromotion $promotion): float
    {
        $triggerTotal = $promotion->triggers->sum(
            fn ($trigger) => (float) $trigger->product->price * (int) $trigger->min_quantity
        );
        $rewardTotal = $promotion->rewards->sum(
            fn ($reward) => (float) $reward->extra_charge * (int) $reward->quantity
        );

        return $triggerTotal + $rewardTotal;
    }
}
