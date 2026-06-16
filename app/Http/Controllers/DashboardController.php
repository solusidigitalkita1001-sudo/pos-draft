<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\TransactionItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $team = $user->currentTeam;

        $stats = [];
        $recentTransactions = [];
        $topProducts = [];

        if ($team) {
            setPermissionsTeamId($team->id);

            if ($user->canOnCurrentTeam('transaction.view')) {
                $stats = array_merge($stats, $this->buildTransactionStats($team));

                $recentTransactions = $team->transactions()
                    ->with('cashier:id,name')
                    ->latest()
                    ->limit(5)
                    ->get(['id', 'invoice_number', 'customer_name', 'status', 'payment_status', 'grand_total', 'created_at', 'user_id']);
            }

            if ($user->canOnCurrentTeam('product.view')) {
                $stats = array_merge($stats, $this->buildProductStats($team));

                if ($user->canOnCurrentTeam('transaction.view')) {
                    $topProducts = $this->buildTopProducts($team);
                }
            }

            if ($user->canOnCurrentTeam('user.view')) {
                $stats['total_members'] = $team->members()->count();
            }
        }

        return Inertia::render('dashboard', [
            'stats'               => $stats,
            'isOwner'             => $team ? $user->ownsTeam($team) : false,
            'recentTransactions'  => $recentTransactions,
            'topProducts'         => $topProducts,
        ]);
    }

    private function buildTransactionStats(mixed $team): array
    {
        $base = $team->transactions()->where('status', Transaction::STATUS_COMPLETED);

        return [
            'transactions_today' => (clone $base)
                ->whereDate('created_at', today())
                ->count(),

            'revenue_today' => (float) (clone $base)
                ->whereDate('created_at', today())
                ->where('payment_status', Transaction::PAYMENT_STATUS_PAID)
                ->sum('grand_total'),

            'transactions_month' => (clone $base)
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count(),

            'revenue_month' => (float) (clone $base)
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->where('payment_status', Transaction::PAYMENT_STATUS_PAID)
                ->sum('grand_total'),

            'pending_transactions' => $team->transactions()
                ->where('status', Transaction::STATUS_PENDING)
                ->count(),
        ];
    }

    private function buildProductStats(mixed $team): array
    {
        return [
            'total_products' => $team->products()
                ->where('is_active', true)
                ->count(),

            'low_stock_products' => $team->products()
                ->where('is_active', true)
                ->whereColumn('stock', '<=', 'min_stock')
                ->where('min_stock', '>', 0)
                ->count(),
        ];
    }

    private function buildTopProducts(mixed $team): \Illuminate\Support\Collection
    {
        return TransactionItem::query()
            ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
            ->where('transactions.team_id', $team->id)
            ->where('transactions.status', Transaction::STATUS_COMPLETED)
            ->whereMonth('transactions.created_at', now()->month)
            ->whereYear('transactions.created_at', now()->year)
            ->whereNotNull('transaction_items.product_id')
            ->select(
                'transaction_items.product_name',
                DB::raw('SUM(transaction_items.quantity) as total_qty'),
                DB::raw('SUM(transaction_items.line_total) as total_revenue'),
            )
            ->groupBy('transaction_items.product_name')
            ->orderByDesc('total_qty')
            ->limit(5)
            ->get();
    }
}