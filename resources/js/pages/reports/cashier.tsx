import { Head, router } from '@inertiajs/react';
import { Download, UserCheck } from 'lucide-react';
import { useMemo, useState } from 'react';

interface CashierRow {
    id: number;
    name: string;
    total_transactions: number;
    total_revenue: string;
    average_order_value: number;
    last_transaction_at: string | null;
}

interface CashierReportProps {
    cashiers: CashierRow[];
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

function formatDate(value: string | null): string {
    if (!value) return '-';

    return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

function buildUrl(teamSlug: string, path: string): string {
    return `/${teamSlug}${path}`;
}

export default function CashierReport({ cashiers, filters, teamSlug, canExport }: CashierReportProps) {
    const [range, setRange] = useState(filters);

    const totalRevenue = useMemo(
        () => cashiers.reduce((sum, c) => sum + Number(c.total_revenue), 0),
        [cashiers],
    );

    const applyRange = () => {
        router.get(buildUrl(teamSlug, '/reports/cashier'), range, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const exportHref = useMemo(() => {
        const params = new URLSearchParams({ type: 'cashier', ...range });
        return `${buildUrl(teamSlug, '/reports/export')}?${params.toString()}`;
    }, [range, teamSlug]);

    return (
        <>
            <Head title="Laporan Kasir" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <p className="text-sm font-medium text-slate-500">Modul laporan</p>
                        <h1 className="text-2xl font-semibold text-slate-900">Laporan Kasir</h1>
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

                {/* Summary */}
                <div className="grid gap-3 md:grid-cols-2">
                    <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                        <p className="text-sm text-slate-500">Total kasir aktif</p>
                        <p className="mt-3 text-2xl font-semibold text-slate-900">{cashiers.length}</p>
                    </div>
                    <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                        <p className="text-sm text-slate-500">Total pendapatan tim</p>
                        <p className="mt-3 text-2xl font-semibold text-emerald-600">{currency(totalRevenue)}</p>
                    </div>
                </div>

                {/* Cashier performance table */}
                <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="flex items-center gap-2 border-b border-slate-200 px-4 py-3">
                        <UserCheck className="h-4 w-4 text-slate-500" />
                        <h2 className="text-sm font-semibold text-slate-900">Performa per Kasir</h2>
                    </div>

                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b border-slate-100 text-left text-slate-500">
                                <th className="px-4 py-2 font-medium">Kasir</th>
                                <th className="px-4 py-2 text-right font-medium">Jumlah Transaksi</th>
                                <th className="px-4 py-2 text-right font-medium">Total Pendapatan</th>
                                <th className="px-4 py-2 text-right font-medium">Rata-rata / Transaksi</th>
                                <th className="px-4 py-2 text-right font-medium">Transaksi Terakhir</th>
                            </tr>
                        </thead>
                        <tbody>
                            {cashiers.length === 0 ? (
                                <tr>
                                    <td colSpan={5} className="px-4 py-8 text-center text-slate-500">
                                        Tidak ada transaksi pada rentang tanggal ini.
                                    </td>
                                </tr>
                            ) : (
                                cashiers.map((cashier, index) => (
                                    <tr key={cashier.id} className="border-b border-slate-50 last:border-0">
                                        <td className="px-4 py-2.5 font-medium text-slate-900">
                                            {index === 0 && (
                                                <span className="mr-2 inline-flex h-5 w-5 items-center justify-center rounded-full bg-amber-100 text-[10px] font-bold text-amber-700">
                                                    1
                                                </span>
                                            )}
                                            {cashier.name}
                                        </td>
                                        <td className="px-4 py-2.5 text-right text-slate-600">{cashier.total_transactions}</td>
                                        <td className="px-4 py-2.5 text-right font-semibold text-slate-900">
                                            {currency(cashier.total_revenue)}
                                        </td>
                                        <td className="px-4 py-2.5 text-right text-slate-600">
                                            {currency(cashier.average_order_value)}
                                        </td>
                                        <td className="px-4 py-2.5 text-right text-slate-400">
                                            {formatDate(cashier.last_transaction_at)}
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