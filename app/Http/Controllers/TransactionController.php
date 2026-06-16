<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Models\Transaction;
use App\Models\TransactionAudit;
use App\Notifications\TransactionNotification;
use App\Support\DocumentNumberGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $team = $request->user()?->currentTeam;
        $user = $request->user();

        abort_if(! $team, 403, 'Tidak ada tim aktif.');
        abort_unless($team->members()->where('users.id', $user->id)->exists(), 403);

        $filters = $request->only([
            'search',
            'date_from',
            'date_to',
            'status',
            'payment_status',
            'amount_min',
            'amount_max',
        ]);

        $query = $team->transactions()
            ->with('cashier:id,name')
            ->latest('created_at');

        $this->applyFilters($query, $filters);

        $transactions = $query->paginate(10)->withQueryString();

        $summary = [
            'total' => $team->transactions()->count(),
            'completed' => $team->transactions()->where('status', Transaction::STATUS_COMPLETED)->count(),
            'pending' => $team->transactions()->where('status', Transaction::STATUS_PENDING)->count(),
            'void' => $team->transactions()->where('status', Transaction::STATUS_VOID)->count(),
            'revenue' => (string) $team->transactions()->sum('grand_total'),
        ];

        return Inertia::render('transactions/index', [
            'transactions' => $transactions,
            'filters' => array_merge([
                'search' => '',
                'date_from' => '',
                'date_to' => '',
                'status' => '',
                'payment_status' => '',
                'amount_min' => '',
                'amount_max' => '',
            ], $filters),
            'summary' => $summary,
            'teamSlug' => $team->slug,
            'canManage' => $user->ownsTeam($team),
            'canExport' => $user->ownsTeam($team),
            'paymentMethods' => [
                ['value' => 'cash', 'label' => 'Tunai'],
                ['value' => 'card', 'label' => 'Kartu'],
                ['value' => 'transfer', 'label' => 'Transfer'],
                ['value' => 'qris', 'label' => 'QRIS'],
            ],
        ]);
    }

    public function store(Request $request)
    {
        $team = $request->user()?->currentTeam;
        $user = $request->user();

        abort_if(! $team, 403, 'Tidak ada tim aktif.');
        abort_unless($user->ownsTeam($team), 403, 'Hanya owner yang dapat membuat transaksi.');

        $validated = $request->validate([
            'customer_name' => ['required', 'string', 'max:255'],
            'grand_total' => ['required', 'numeric', 'min:0'],
            'transaction_date' => ['nullable', 'date'],
            'payment_method' => ['nullable', 'string', 'in:cash,card,transfer,qris'],
            'payment_status' => ['nullable', 'string', 'in:paid,partial,unpaid'],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $grandTotal = (float) $validated['grand_total'];
        $paidAmount = (float) ($validated['paid_amount'] ?? 0);
        $transactionDate = $validated['transaction_date']
            ? Carbon::parse($validated['transaction_date'])->startOfDay()
            : now();

        $paymentStatus = $this->resolvePaymentStatus($grandTotal, $paidAmount);
        $status = $paymentStatus === Transaction::PAYMENT_STATUS_UNPAID
            ? Transaction::STATUS_PENDING
            : Transaction::STATUS_COMPLETED;

        $transaction = $team->transactions()->create([
            'user_id' => $user->id,
            'invoice_number' => DocumentNumberGenerator::generate('TRX', 'transactions', 'invoice_number', $team->id),
            'customer_name' => $validated['customer_name'],
            'status' => $status,
            'payment_status' => $paymentStatus,
            'payment_method' => $validated['payment_method'] ?? null,
            'subtotal' => $grandTotal,
            'discount_total' => 0,
            'tax_total' => 0,
            'grand_total' => $grandTotal,
            'paid_amount' => $paidAmount,
            'change_amount' => max($paidAmount - $grandTotal, 0),
            'note' => $validated['note'] ?? null,
            'paid_at' => in_array($paymentStatus, [Transaction::PAYMENT_STATUS_PAID, Transaction::PAYMENT_STATUS_PARTIAL], true)
                ? $transactionDate
                : null,
        ]);

        $transaction->forceFill([
            'created_at' => $transactionDate,
            'updated_at' => $transactionDate,
        ])->saveQuietly();

        $this->recordAudit($transaction->fresh(), 'created', ['record' => $transaction->fresh()->toArray()]);
        $this->notifyTeamMembers($transaction->fresh(), 'created');

        return back()->with('success', 'Transaksi berhasil ditambahkan.');
    }

    public function update(Request $request, string $currentTeam, Transaction $transaction)
    {
        $team = $request->user()?->currentTeam;
        $user = $request->user();

        abort_if(! $team, 403, 'Tidak ada tim aktif.');
        abort_unless($user->ownsTeam($team), 403, 'Hanya owner yang dapat mengubah transaksi.');
        abort_unless($transaction->team_id === $team->id, 404);

        $validated = $request->validate([
            'customer_name' => ['required', 'string', 'max:255'],
            'grand_total' => ['required', 'numeric', 'min:0'],
            'transaction_date' => ['nullable', 'date'],
            'payment_method' => ['nullable', 'string', 'in:cash,card,transfer,qris'],
            'payment_status' => ['nullable', 'string', 'in:paid,partial,unpaid'],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $grandTotal = (float) $validated['grand_total'];
        $paidAmount = (float) ($validated['paid_amount'] ?? 0);
        $transactionDate = $validated['transaction_date']
            ? Carbon::parse($validated['transaction_date'])->startOfDay()
            : ($transaction->created_at ?? now());
        $paymentStatus = $this->resolvePaymentStatus($grandTotal, $paidAmount);
        $status = $paymentStatus === Transaction::PAYMENT_STATUS_UNPAID
            ? Transaction::STATUS_PENDING
            : Transaction::STATUS_COMPLETED;

        $original = $transaction->getAttributes();

        $transaction->fill([
            'customer_name' => $validated['customer_name'],
            'status' => $status,
            'payment_status' => $paymentStatus,
            'payment_method' => $validated['payment_method'] ?? null,
            'subtotal' => $grandTotal,
            'discount_total' => 0,
            'tax_total' => 0,
            'grand_total' => $grandTotal,
            'paid_amount' => $paidAmount,
            'change_amount' => max($paidAmount - $grandTotal, 0),
            'note' => $validated['note'] ?? null,
            'paid_at' => in_array($paymentStatus, [Transaction::PAYMENT_STATUS_PAID, Transaction::PAYMENT_STATUS_PARTIAL], true)
                ? $transactionDate
                : null,
        ]);

        $transaction->forceFill([
            'created_at' => $transactionDate,
            'updated_at' => now(),
        ])->save();

        $changes = $this->buildChanges($original, $transaction->fresh()->getAttributes());
        $this->recordAudit($transaction->fresh(), 'updated', $changes);
        $this->notifyTeamMembers($transaction->fresh(), 'updated');

        return back()->with('success', 'Transaksi berhasil diperbarui.');
    }

    public function destroy(Request $request, string $currentTeam, Transaction $transaction)
    {
        $team = $request->user()?->currentTeam;
        $user = $request->user();

        abort_if(! $team, 403, 'Tidak ada tim aktif.');
        abort_unless($user->ownsTeam($team), 403, 'Hanya owner yang dapat menghapus transaksi.');
        abort_unless($transaction->team_id === $team->id, 404);

        $snapshot = $transaction->toArray();

        $this->recordAudit($transaction, 'deleted', ['record' => $snapshot]);
        $transaction->delete();

        return back()->with('success', 'Transaksi berhasil dihapus.');
    }

    public function export(Request $request)
    {
        $team = $request->user()?->currentTeam;
        $user = $request->user();

        abort_if(! $team, 403, 'Tidak ada tim aktif.');
        abort_unless($user->ownsTeam($team), 403, 'Hanya owner yang dapat mengekspor laporan transaksi.');

        $filters = $request->only([
            'search',
            'date_from',
            'date_to',
            'status',
            'payment_status',
            'amount_min',
            'amount_max',
        ]);

        $query = $team->transactions()->latest('created_at');
        $this->applyFilters($query, $filters);

        $filename = 'transaksi-'.$team->slug.'-'.now()->format('YmdHis').'.csv';

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Invoice',
                'Nama Transaksi',
                'Tanggal',
                'Status',
                'Pembayaran',
                'Metode',
                'Grand Total',
                'Dibayar',
                'Kembalian',
                'Catatan',
            ]);

            $query->chunk(200, function ($transactions) use ($handle) {
                foreach ($transactions as $transaction) {
                    fputcsv($handle, [
                        $transaction->invoice_number,
                        $transaction->customer_name,
                        $transaction->created_at->format('Y-m-d H:i:s'),
                        $transaction->status,
                        $transaction->payment_status,
                        $transaction->payment_method,
                        $transaction->grand_total,
                        $transaction->paid_amount,
                        $transaction->change_amount,
                        $transaction->note,
                    ]);
                }
            });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function applyFilters($query, array $filters): void
    {
        $search = trim((string) ($filters['search'] ?? ''));

        if ($search !== '') {
            $query->where(function ($query) use ($search) {
                $query->where('customer_name', 'like', "%{$search}%")
                    ->orWhere('invoice_number', 'like', "%{$search}%")
                    ->orWhere('note', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['payment_status'])) {
            $query->where('payment_status', $filters['payment_status']);
        }

        if (! empty($filters['amount_min'])) {
            $query->where('grand_total', '>=', (float) $filters['amount_min']);
        }

        if (! empty($filters['amount_max'])) {
            $query->where('grand_total', '<=', (float) $filters['amount_max']);
        }
    }

    private function resolvePaymentStatus(float $grandTotal, float $paidAmount): string
    {
        if ($paidAmount <= 0) {
            return Transaction::PAYMENT_STATUS_UNPAID;
        }

        if ($paidAmount < $grandTotal) {
            return Transaction::PAYMENT_STATUS_PARTIAL;
        }

        return Transaction::PAYMENT_STATUS_PAID;
    }

    private function buildChanges(array $original, array $current): array
    {
        $changes = [];

        foreach ($current as $key => $value) {
            if (! array_key_exists($key, $original) || $original[$key] === $value) {
                continue;
            }

            $changes[$key] = [
                'from' => $original[$key] ?? null,
                'to' => $value,
            ];
        }

        return $changes;
    }

    private function recordAudit(Transaction $transaction, string $action, array $changes): void
    {
        TransactionAudit::create([
            'transaction_id' => $transaction->id,
            'team_id' => $transaction->team_id,
            'user_id' => request()->user()?->id,
            'action' => $action,
            'changes' => $changes,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    private function notifyTeamMembers(?Transaction $transaction, string $action): void
    {
        if (! $transaction) {
            return;
        }

        $transaction->loadMissing('team');

        $members = $transaction->team?->members()->get() ?? collect();

        if ($members->isEmpty()) {
            return;
        }

        Notification::send($members, new TransactionNotification($transaction, $action));
    }

}