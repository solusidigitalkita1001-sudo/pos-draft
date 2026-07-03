import { Head, router } from '@inertiajs/react';
import { Download, TrendingUp } from 'lucide-react';
import { useMemo, useState } from 'react';

interface DailyTrendRow {
    date: string;
    transactions: number;
    revenue: string;
}

interface PaymentMethodRow {
    payment_method: string | null;
    transactions: number;
    revenue: string;
}

interface TopProductRow {
    product_name: string;
    total_qty: number;
    total_revenue: string;
}

interface SalesReportProps {
    summary: {
        total_transactions: number;
        total_revenue: number;
        total_discount: number;
        total_tax: number;
        total_items_sold: number;
        average_order_value: number;
    };
    dailyTrend: DailyTrendRow[];
    byPaymentMethod: PaymentMethodRow[];
    topProducts: TopProductRow[];
    filters: { date_from: string; date_to: string };
    teamSlug: string;
    canExport: boolean;
}

function currency(value: string | number | null): string {
    const amount = Number(value ?? 0);

    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(amount);
}

function paymentMethodLabel(method: string | null): string {
    const labels: Record<string, string> = {
        cash: 'Tunai',
        qris: 'QRIS',
        card: 'Kartu',
        transfer: 'Transfer',
    };

    return labels[method ?? ''] ?? method ?? 'Tidak diketahui';
}

function buildUrl(teamSlug: string, path: string): string {
    return `/${teamSlug}${path}`;
}

export default function SalesReport({
    summary,
    dailyTrend,
    byPaymentMethod,
    topProducts,
    filters,
    teamSlug,
    canExport,
}: SalesReportProps) {
    const [range, setRange] = useState(filters);

    const maxDailyRevenue = useMemo(
        () => Math.max(...dailyTrend.map((row) => Number(row.revenue)), 1),
        [dailyTrend],
    );

    const applyRange = () => {
        router.get(buildUrl(teamSlug, '/reports/sales'), range, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const exportHref = useMemo(() => {
        const params = new URLSearchParams({ type: 'sales', ...range });
        return `${buildUrl(teamSlug, '/reports/export')}?${params.toString()}`;
    }, [range, teamSlug]);

    return (
        <>
            <Head title="Laporan Penjualan" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <p className="text-sm font-medium text-slate-500">Modul laporan</p>
                        <h1 className="text-2xl font-semibold text-slate-900">Laporan Penjualan</h1>
                    </div>

                    {canExport && (
                        <a
                            href={exportHref}
                            className="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                        >
                            <Download className="h-4 w-4" />
                            Export CSV
                        </a>
                    )}
                </div>

                {/* Date range filter */}
                <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div className="flex flex-wrap items-end gap-3">
                        <label className="flex min-w-[180px] flex-col gap-2 text-sm text-slate-700">
                            Dari tanggal
                            <input
                                type="date"
                                className="rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none"
                                value={range.date_from}
                                onChange={(e) => setRange((r) => ({ ...r, date_from: e.target.value }))}
                            />
                        </label>
                        <label className="flex min-w-[180px] flex-col gap-2 text-sm text-slate-700">
                            Sampai tanggal
                            <input
                                type="date"
                                className="rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none"
                                value={range.date_to}
                                onChange={(e) => setRange((r) => ({ ...r, date_to: e.target.value }))}
                            />
                        </label>
                        <button
                            type="button"
                            onClick={applyRange}
                            className="rounded-full bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-800"
                        >
                            Terapkan
                        </button>
                    </div>
                </div>

                {/* Summary cards */}
                <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <SummaryCard label="Total transaksi" value={summary.total_transactions.toLocaleString('id-ID')} />
                    <SummaryCard label="Total pendapatan" value={currency(summary.total_revenue)} tone="text-emerald-600" />
                    <SummaryCard label="Rata-rata per transaksi" value={currency(summary.average_order_value)} />
                    <SummaryCard label="Item terjual" value={summary.total_items_sold.toLocaleString('id-ID')} />
                    <SummaryCard label="Total diskon" value={currency(summary.total_discount)} tone="text-amber-600" />
                    <SummaryCard label="Total pajak" value={currency(summary.total_tax)} />
                </div>

                <div className="grid gap-4 lg:grid-cols-3">
                    {/* Daily trend chart (simple bar chart, no external lib) */}
                    <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm lg:col-span-2">
                        <div className="mb-4 flex items-center gap-2">
                            <TrendingUp className="h-4 w-4 text-slate-500" />
                            <h2 className="text-sm font-semibold text-slate-900">Tren Pendapatan Harian</h2>
                        </div>

                        {dailyTrend.length === 0 ? (
                            <p className="py-8 text-center text-sm text-slate-500">
                                Tidak ada transaksi pada rentang tanggal ini.
                            </p>
                        ) : (
                            <div className="flex items-end gap-1.5 overflow-x-auto pb-2" style={{ minHeight: '160px' }}>
                                {dailyTrend.map((row) => {
                                    const heightPct = Math.max((Number(row.revenue) / maxDailyRevenue) * 100, 4);

                                    return (
                                        <div
                                            key={row.date}
                                            className="group relative flex min-w-[28px] flex-1 flex-col items-center justify-end"
                                            style={{ height: '140px' }}
                                        >
                                            <div className="absolute -top-9 hidden whitespace-nowrap rounded-lg bg-slate-900 px-2 py-1 text-xs text-white group-hover:block">
                                                {currency(row.revenue)}
                                            </div>
                                            <div
                                                className="w-full rounded-t-md bg-slate-900/80 transition group-hover:bg-slate-900"
                                                style={{ height: `${heightPct}%` }}
                                            />
                                            <span className="mt-2 text-[10px] text-slate-400">
                                                {new Date(row.date).getDate()}
                                            </span>
                                        </div>
                                    );
                                })}
                            </div>
                        )}
                    </div>

                    {/* Payment method breakdown */}
                    <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                        <h2 className="mb-4 text-sm font-semibold text-slate-900">Berdasarkan Metode Bayar</h2>

                        {byPaymentMethod.length === 0 ? (
                            <p className="py-8 text-center text-sm text-slate-500">Belum ada data.</p>
                        ) : (
                            <div className="space-y-3">
                                {byPaymentMethod.map((row) => (
                                    <div key={row.payment_method ?? 'unknown'} className="flex items-center justify-between text-sm">
                                        <span className="text-slate-600">{paymentMethodLabel(row.payment_method)}</span>
                                        <div className="text-right">
                                            <div className="font-semibold text-slate-900">{currency(row.revenue)}</div>
                                            <div className="text-xs text-slate-400">{row.transactions} transaksi</div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                </div>

                {/* Top products table */}
                <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="border-b border-slate-200 px-4 py-3">
                        <h2 className="text-sm font-semibold text-slate-900">Produk Terlaris</h2>
                    </div>

                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b border-slate-100 text-left text-slate-500">
                                <th className="px-4 py-2 font-medium">Produk</th>
                                <th className="px-4 py-2 text-right font-medium">Qty Terjual</th>
                                <th className="px-4 py-2 text-right font-medium">Total Pendapatan</th>
                            </tr>
                        </thead>
                        <tbody>
                            {topProducts.length === 0 ? (
                                <tr>
                                    <td colSpan={3} className="px-4 py-8 text-center text-slate-500">
                                        Belum ada penjualan pada rentang tanggal ini.
                                    </td>
                                </tr>
                            ) : (
                                topProducts.map((row) => (
                                    <tr key={row.product_name} className="border-b border-slate-50 last:border-0">
                                        <td className="px-4 py-2.5 font-medium text-slate-900">{row.product_name}</td>
                                        <td className="px-4 py-2.5 text-right text-slate-600">{row.total_qty}</td>
                                        <td className="px-4 py-2.5 text-right font-semibold text-slate-900">
                                            {currency(row.total_revenue)}
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

function SummaryCard({ label, value, tone = 'text-slate-900' }: { label: string; value: string; tone?: string }) {
    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <p className="text-sm text-slate-500">{label}</p>
            <p className={`mt-3 text-2xl font-semibold ${tone}`}>{value}</p>
        </div>
    );
}