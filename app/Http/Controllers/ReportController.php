<?php

namespace App\Http\Controllers;

use App\Models\ProductStockMovement;
use App\Models\Transaction;
use App\Models\TransactionItem;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    /**
     * Laporan Penjualan — ringkasan revenue, tren harian, breakdown metode
     * bayar, dan produk terlaris dalam rentang tanggal tertentu.
     */
    public function sales(Request $request): Response
    {
        $team = $request->user()->currentTeam;
        $user = $request->user();

        abort_if(! $team, 403, 'Tidak ada tim aktif.');

        setPermissionsTeamId($team->id);

        [$from, $to, $filters] = $this->resolveDateRange($request);

        $base = $team->transactions()
            ->where('status', Transaction::STATUS_COMPLETED)
            ->whereBetween('created_at', [$from, $to]);

        $summary = [
            'total_transactions' => (clone $base)->count(),
            'total_revenue' => (float) (clone $base)->sum('grand_total'),
            'total_discount' => (float) (clone $base)->sum('discount_total'),
            'total_tax' => (float) (clone $base)->sum('tax_total'),
            'total_items_sold' => (int) TransactionItem::query()
                ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
                ->where('transactions.team_id', $team->id)
                ->where('transactions.status', Transaction::STATUS_COMPLETED)
                ->whereBetween('transactions.created_at', [$from, $to])
                ->sum('transaction_items.quantity'),
        ];

        $summary['average_order_value'] = $summary['total_transactions'] > 0
            ? round($summary['total_revenue'] / $summary['total_transactions'], 2)
            : 0.0;

        $dailyTrend = (clone $base)
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as transactions'),
                DB::raw('SUM(grand_total) as revenue'),
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $byPaymentMethod = (clone $base)
            ->select('payment_method', DB::raw('COUNT(*) as transactions'), DB::raw('SUM(grand_total) as revenue'))
            ->groupBy('payment_method')
            ->orderByDesc('revenue')
            ->get();

        $topProducts = $this->buildTopProducts($team, $from, $to);

        return Inertia::render('reports/sales', [
            'summary' => $summary,
            'dailyTrend' => $dailyTrend,
            'byPaymentMethod' => $byPaymentMethod,
            'topProducts' => $topProducts,
            'filters' => $filters,
            'teamSlug' => $team->slug,
            'canExport' => $user->ownsTeam($team) || $user->canOnCurrentTeam('report.export'),
        ]);
    }

    /**
     * Laporan Stok — posisi stok saat ini, nilai inventory, produk yang
     * hampir habis, dan ringkasan pergerakan stok dalam rentang tanggal.
     */
    public function stock(Request $request): Response
    {
        $team = $request->user()->currentTeam;
        $user = $request->user();

        abort_if(! $team, 403, 'Tidak ada tim aktif.');

        setPermissionsTeamId($team->id);

        [$from, $to, $filters] = $this->resolveDateRange($request);

        $activeProducts = $team->products()->where('is_active', true);

        $summary = [
            'total_products' => (clone $activeProducts)->count(),
            'total_stock_units' => (int) (clone $activeProducts)->sum('stock'),
            'total_stock_value' => (float) (clone $activeProducts)
                ->selectRaw('COALESCE(SUM(stock * cost), 0) as value')
                ->value('value'),
            'low_stock_count' => (clone $activeProducts)
                ->whereColumn('stock', '<=', 'min_stock')
                ->where('min_stock', '>', 0)
                ->count(),
        ];

        $lowStockProducts = (clone $activeProducts)
            ->with('category:id,name')
            ->whereColumn('stock', '<=', 'min_stock')
            ->where('min_stock', '>', 0)
            ->orderBy('stock')
            ->limit(50)
            ->get(['id', 'category_id', 'sku', 'name', 'stock', 'min_stock']);

        $movementSummary = ProductStockMovement::query()
            ->where('team_id', $team->id)
            ->whereBetween('created_at', [$from, $to])
            ->select('type', DB::raw('SUM(quantity) as total_quantity'), DB::raw('COUNT(*) as total_movements'))
            ->groupBy('type')
            ->get();

        $topMovingProducts = ProductStockMovement::query()
            ->join('products', 'product_stock_movements.product_id', '=', 'products.id')
            ->where('product_stock_movements.team_id', $team->id)
            ->where('product_stock_movements.type', ProductStockMovement::TYPE_OUT)
            ->whereBetween('product_stock_movements.created_at', [$from, $to])
            ->select('products.id', 'products.name', 'products.sku', DB::raw('SUM(product_stock_movements.quantity) as total_out'))
            ->groupBy('products.id', 'products.name', 'products.sku')
            ->orderByDesc('total_out')
            ->limit(10)
            ->get();

        return Inertia::render('reports/stock', [
            'summary' => $summary,
            'lowStockProducts' => $lowStockProducts,
            'movementSummary' => $movementSummary,
            'topMovingProducts' => $topMovingProducts,
            'filters' => $filters,
            'teamSlug' => $team->slug,
            'canExport' => $user->ownsTeam($team) || $user->canOnCurrentTeam('report.export'),
        ]);
    }

    /**
     * Laporan Kasir — performa penjualan per kasir dalam rentang tanggal.
     */
    public function cashier(Request $request): Response
    {
        $team = $request->user()->currentTeam;
        $user = $request->user();

        abort_if(! $team, 403, 'Tidak ada tim aktif.');

        setPermissionsTeamId($team->id);

        [$from, $to, $filters] = $this->resolveDateRange($request);

        $cashiers = Transaction::query()
            ->join('users', 'transactions.user_id', '=', 'users.id')
            ->where('transactions.team_id', $team->id)
            ->where('transactions.status', Transaction::STATUS_COMPLETED)
            ->whereBetween('transactions.created_at', [$from, $to])
            ->select(
                'users.id',
                'users.name',
                DB::raw('COUNT(*) as total_transactions'),
                DB::raw('SUM(transactions.grand_total) as total_revenue'),
                DB::raw('MAX(transactions.created_at) as last_transaction_at'),
            )
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('total_revenue')
            ->get()
            ->map(function ($row) {
                $row->average_order_value = $row->total_transactions > 0
                    ? round($row->total_revenue / $row->total_transactions, 2)
                    : 0.0;

                return $row;
            });

        return Inertia::render('reports/cashier', [
            'cashiers' => $cashiers,
            'filters' => $filters,
            'teamSlug' => $team->slug,
            'canExport' => $user->ownsTeam($team) || $user->canOnCurrentTeam('report.export'),
        ]);
    }

    /**
     * Export CSV untuk salah satu jenis laporan.
     * Contoh: /reports/export?type=sales&date_from=2026-06-01&date_to=2026-06-28
     */
    public function export(Request $request)
    {
        $team = $request->user()->currentTeam;
        $user = $request->user();

        abort_if(! $team, 403, 'Tidak ada tim aktif.');
        abort_unless(
            $user->ownsTeam($team) || $user->canOnCurrentTeam('report.export'),
            403,
            'Anda tidak memiliki izin untuk mengekspor laporan.',
        );

        $type = $request->string('type', 'sales')->toString();
        [$from, $to] = $this->resolveDateRange($request);

        return match ($type) {
            'stock' => $this->exportStock($team),
            'cashier' => $this->exportCashier($team, $from, $to),
            default => $this->exportSales($team, $from, $to),
        };
    }

    // ─── Export builders ───────────────────────────────────────────────────

    private function exportSales(mixed $team, CarbonInterface $from, CarbonInterface $to)
    {
        $rows = $team->transactions()
            ->where('status', Transaction::STATUS_COMPLETED)
            ->whereBetween('created_at', [$from, $to])
            ->with('cashier:id,name')
            ->orderBy('created_at')
            ->get();

        $filename = 'laporan-penjualan-'.$team->slug.'-'.now()->format('YmdHis').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Invoice', 'Tanggal', 'Kasir', 'Subtotal', 'Diskon', 'Pajak', 'Total', 'Metode Bayar']);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row->invoice_number,
                    $row->created_at->format('Y-m-d H:i'),
                    $row->cashier?->name ?? '-',
                    $row->subtotal,
                    $row->discount_total,
                    $row->tax_total,
                    $row->grand_total,
                    $row->payment_method,
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function exportStock(mixed $team)
    {
        $rows = $team->products()
            ->where('is_active', true)
            ->with('category:id,name')
            ->orderBy('name')
            ->get();

        $filename = 'laporan-stok-'.$team->slug.'-'.now()->format('YmdHis').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['SKU', 'Nama Produk', 'Kategori', 'Stok', 'Stok Minimum', 'Harga Jual', 'Harga Modal', 'Nilai Stok']);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row->sku,
                    $row->name,
                    $row->category?->name ?? '-',
                    $row->stock,
                    $row->min_stock,
                    $row->price,
                    $row->cost,
                    $row->stock * $row->cost,
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function exportCashier(mixed $team, CarbonInterface $from, CarbonInterface $to)
    {
        $rows = Transaction::query()
            ->join('users', 'transactions.user_id', '=', 'users.id')
            ->where('transactions.team_id', $team->id)
            ->where('transactions.status', Transaction::STATUS_COMPLETED)
            ->whereBetween('transactions.created_at', [$from, $to])
            ->select(
                'users.name',
                DB::raw('COUNT(*) as total_transactions'),
                DB::raw('SUM(transactions.grand_total) as total_revenue'),
            )
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('total_revenue')
            ->get();

        $filename = 'laporan-kasir-'.$team->slug.'-'.now()->format('YmdHis').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Kasir', 'Jumlah Transaksi', 'Total Pendapatan']);

            foreach ($rows as $row) {
                fputcsv($handle, [$row->name, $row->total_transactions, $row->total_revenue]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    // ─── Helpers ────────────────────────────────────────────────────────────

    private function buildTopProducts(mixed $team, CarbonInterface $from, CarbonInterface $to): \Illuminate\Support\Collection
    {
        return TransactionItem::query()
            ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
            ->where('transactions.team_id', $team->id)
            ->where('transactions.status', Transaction::STATUS_COMPLETED)
            ->whereBetween('transactions.created_at', [$from, $to])
            ->select(
                'transaction_items.product_name',
                DB::raw('SUM(transaction_items.quantity) as total_qty'),
                DB::raw('SUM(transaction_items.line_total) as total_revenue'),
            )
            ->groupBy('transaction_items.product_name')
            ->orderByDesc('total_qty')
            ->limit(10)
            ->get();
    }

    /**
     * Resolve rentang tanggal dari query string, default: bulan ini.
     * Mengembalikan [CarbonInterface $from, CarbonInterface $to, array $filtersForFrontend].
     */
    private function resolveDateRange(Request $request): array
    {
        $dateFrom = $request->string('date_from')->toString();
        $dateTo = $request->string('date_to')->toString();

        $from = $dateFrom !== '' ? Carbon::parse($dateFrom)->startOfDay() : now()->startOfMonth();
        $to = $dateTo !== '' ? Carbon::parse($dateTo)->endOfDay() : now()->endOfDay();

        return [
            $from,
            $to,
            [
                'date_from' => $from->format('Y-m-d'),
                'date_to' => $to->format('Y-m-d'),
            ],
        ];
    }
}
