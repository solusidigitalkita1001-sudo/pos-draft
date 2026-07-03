import { Head, router } from '@inertiajs/react';
import { AlertTriangle, Download } from 'lucide-react';
import { useMemo, useState } from 'react';

interface LowStockProduct {
    id: number;
    sku: string;
    name: string;
    stock: number;
    min_stock: number;
    category?: { id: number; name: string } | null;
}

interface MovementSummaryRow {
    type: 'in' | 'out' | 'adjustment';
    total_quantity: number;
    total_movements: number;
}

interface TopMovingProduct {
    id: number;
    name: string;
    sku: string;
    total_out: number;
}

interface StockReportProps {
    summary: {
        total_products: number;
        total_stock_units: number;
        total_stock_value: number;
        low_stock_count: number;
    };
    lowStockProducts: LowStockProduct[];
    movementSummary: MovementSummaryRow[];
    topMovingProducts: TopMovingProduct[];
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

function movementTypeLabel(type: string): string {
    const labels: Record<string, string> = {
        in: 'Stok Masuk',
        out: 'Stok Keluar',
        adjustment: 'Penyesuaian',
    };

    return labels[type] ?? type;
}

function buildUrl(teamSlug: string, path: string): string {
    return `/${teamSlug}${path}`;
}

export default function StockReport({
    summary,
    lowStockProducts,
    movementSummary,
    topMovingProducts,
    filters,
    teamSlug,
    canExport,
}: StockReportProps) {
    const [range, setRange] = useState(filters);

    const movementByType = useMemo(() => {
        const map: Record<string, MovementSummaryRow> = {};
        movementSummary.forEach((row) => { map[row.type] = row; });
        return map;
    }, [movementSummary]);

    const applyRange = () => {
        router.get(buildUrl(teamSlug, '/reports/stock'), range, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const exportHref = useMemo(() => {
        const params = new URLSearchParams({ type: 'stock', ...range });
        return `${buildUrl(teamSlug, '/reports/export')}?${params.toString()}`;
    }, [range, teamSlug]);

    return (
        <>
            <Head title="Laporan Stok" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <p className="text-sm font-medium text-slate-500">Modul laporan</p>
                        <h1 className="text-2xl font-semibold text-slate-900">Laporan Stok</h1>
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

                {/* Position summary (point-in-time, no date filter needed) */}
                <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <SummaryCard label="Total produk aktif" value={summary.total_products.toLocaleString('id-ID')} />
                    <SummaryCard label="Total unit stok" value={summary.total_stock_units.toLocaleString('id-ID')} />
                    <SummaryCard label="Nilai inventory" value={currency(summary.total_stock_value)} tone="text-emerald-600" />
                    <SummaryCard
                        label="Stok menipis"
                        value={summary.low_stock_count.toLocaleString('id-ID')}
                        tone={summary.low_stock_count > 0 ? 'text-red-600' : 'text-slate-900'}
                    />
                </div>

                {/* Date range filter for movement summary */}
                <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p className="mb-3 text-sm font-medium text-slate-500">
                        Rentang tanggal untuk ringkasan pergerakan stok di bawah
                    </p>
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

                <div className="grid gap-4 lg:grid-cols-3">
                    {/* Movement summary */}
                    <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                        <h2 className="mb-4 text-sm font-semibold text-slate-900">Ringkasan Pergerakan Stok</h2>
                        <div className="space-y-3">
                            <MovementRow label="Stok Masuk" row={movementByType.in} tone="text-emerald-600" />
                            <MovementRow label="Stok Keluar" row={movementByType.out} tone="text-red-600" />
                            <MovementRow label="Penyesuaian" row={movementByType.adjustment} tone="text-amber-600" />
                        </div>
                    </div>

                    {/* Top moving products */}
                    <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm lg:col-span-2">
                        <h2 className="mb-4 text-sm font-semibold text-slate-900">Produk Paling Banyak Keluar</h2>

                        {topMovingProducts.length === 0 ? (
                            <p className="py-8 text-center text-sm text-slate-500">Belum ada pergerakan stok keluar.</p>
                        ) : (
                            <div className="space-y-2">
                                {topMovingProducts.map((product) => (
                                    <div key={product.id} className="flex items-center justify-between text-sm">
                                        <div>
                                            <div className="font-medium text-slate-900">{product.name}</div>
                                            <div className="text-xs text-slate-400">{product.sku}</div>
                                        </div>
                                        <span className="font-semibold text-slate-900">{product.total_out} unit</span>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                </div>

                {/* Low stock table */}
                <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="flex items-center gap-2 border-b border-slate-200 px-4 py-3">
                        <AlertTriangle className="h-4 w-4 text-amber-500" />
                        <h2 className="text-sm font-semibold text-slate-900">Produk Stok Menipis</h2>
                    </div>

                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b border-slate-100 text-left text-slate-500">
                                <th className="px-4 py-2 font-medium">SKU</th>
                                <th className="px-4 py-2 font-medium">Produk</th>
                                <th className="px-4 py-2 font-medium">Kategori</th>
                                <th className="px-4 py-2 text-right font-medium">Stok</th>
                                <th className="px-4 py-2 text-right font-medium">Stok Minimum</th>
                            </tr>
                        </thead>
                        <tbody>
                            {lowStockProducts.length === 0 ? (
                                <tr>
                                    <td colSpan={5} className="px-4 py-8 text-center text-slate-500">
                                        Semua produk dalam kondisi stok aman.
                                    </td>
                                </tr>
                            ) : (
                                lowStockProducts.map((product) => (
                                    <tr key={product.id} className="border-b border-slate-50 last:border-0">
                                        <td className="px-4 py-2.5 text-slate-500">{product.sku}</td>
                                        <td className="px-4 py-2.5 font-medium text-slate-900">{product.name}</td>
                                        <td className="px-4 py-2.5 text-slate-600">{product.category?.name ?? '-'}</td>
                                        <td className="px-4 py-2.5 text-right font-semibold text-red-600">{product.stock}</td>
                                        <td className="px-4 py-2.5 text-right text-slate-500">{product.min_stock}</td>
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

function MovementRow({ label, row, tone }: { label: string; row?: MovementSummaryRow; tone: string }) {
    return (
        <div className="flex items-center justify-between text-sm">
            <span className="text-slate-600">{label}</span>
            <div className="text-right">
                <div className={`font-semibold ${tone}`}>{(row?.total_quantity ?? 0).toLocaleString('id-ID')} unit</div>
                <div className="text-xs text-slate-400">{row?.total_movements ?? 0} transaksi</div>
            </div>
        </div>
    );
}