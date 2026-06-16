import React, { useState } from 'react';
import { Banknote, ChevronDown } from 'lucide-react';
import { router } from '@inertiajs/react';
import type { PaymentMethod, RecentTransaction } from '@/types/pos';
import {
    formatCurrency,
    formatDate,
    inputStyle,
    paymentStatusLabel,
    PosBadge,
    remainingPayment,
    SummaryRow,
} from '../pos-utils';

interface SettlementForm {
    payment_method: string;
    paid_amount: string;
}

interface Props {
    transactions: RecentTransaction[];
    paymentMethods: PaymentMethod[];
    defaultPaymentMethod: string;
    teamSlug: string;
}

export function RecentTransactions({ transactions, paymentMethods, defaultPaymentMethod, teamSlug }: Props) {
    const [expandedId, setExpandedId]       = useState<number | null>(null);
    const [processingId, setProcessingId]   = useState<number | null>(null);
    const [forms, setForms]                 = useState<Record<number, SettlementForm>>({});
    const [errors, setErrors]               = useState<Record<string, string>>({});

    function getForm(tx: RecentTransaction): SettlementForm {
        return forms[tx.id] ?? {
            payment_method: tx.payment_method ?? defaultPaymentMethod,
            paid_amount: '',
        };
    }

    function updateForm(txId: number, values: Partial<SettlementForm>) {
        setForms((prev) => ({
            ...prev,
            [txId]: { ...getForm({ id: txId } as RecentTransaction), ...values },
        }));
        setErrors((prev) => {
            const next = { ...prev };
            delete next.paid_amount;
            return next;
        });
    }

    function settlePaidAmount(tx: RecentTransaction): number {
        return parseFloat(getForm(tx).paid_amount || '0');
    }

    function settleChangeAmount(tx: RecentTransaction): number {
        return Math.max(settlePaidAmount(tx) - remainingPayment(tx), 0);
    }

    function validate(tx: RecentTransaction): string | null {
        const form  = getForm(tx);
        const paid  = settlePaidAmount(tx);
        if (!form.payment_method)              return 'Metode pembayaran wajib dipilih.';
        if (!form.paid_amount.trim())          return 'Jumlah bayar wajib diisi.';
        if (!Number.isFinite(paid) || paid <= 0) return 'Jumlah bayar wajib lebih dari 0.';
        if (paid < remainingPayment(tx))       return 'Nominal pembayaran kurang dari sisa tagihan.';
        return null;
    }

    function settle(tx: RecentTransaction) {
        const err = validate(tx);
        if (err) { setErrors({ paid_amount: err }); return; }

        const form = getForm(tx);
        setProcessingId(tx.id);

        router.post(
            `/${teamSlug}/pos/transaction/${tx.id}/payment`,
            { payment_method: form.payment_method, paid_amount: form.paid_amount },
            {
                preserveScroll: true,
                onError:   (e)  => setErrors(e),
                onSuccess: ()   => setForms((prev) => { const n = { ...prev }; delete n[tx.id]; return n; }),
                onFinish:  ()   => setProcessingId(null),
            },
        );
    }

    return (
        <div style={{ border: '1px solid var(--border)', borderRadius: '8px', backgroundColor: 'var(--card)', overflow: 'hidden' }}>
            <div style={{ padding: '14px 16px', borderBottom: '1px solid var(--border)' }}>
                <strong style={{ fontSize: '14px' }}>Transaksi Terbaru</strong>
            </div>

            <div style={{ overflowX: 'auto' }}>
                <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: '13px' }}>
                    <tbody>
                        {transactions.length === 0 ? (
                            <tr>
                                <td style={{ padding: '24px 16px', textAlign: 'center', color: 'var(--muted-foreground)' }}>
                                    Belum ada transaksi.
                                </td>
                            </tr>
                        ) : (
                            transactions.map((tx) => {
                                const isExpanded = expandedId === tx.id;

                                return (
                                    <React.Fragment key={tx.id}>
                                        {/* Summary row */}
                                        <tr
                                            onClick={() => setExpandedId(isExpanded ? null : tx.id)}
                                            style={{
                                                borderBottom: isExpanded ? 'none' : '1px solid var(--border)',
                                                cursor: 'pointer',
                                            }}
                                        >
                                            <td style={{ padding: '12px 16px' }}>
                                                <div style={{ display: 'flex', alignItems: 'center', gap: '8px', fontWeight: 700 }}>
                                                    <ChevronDown
                                                        size={15}
                                                        style={{
                                                            color: 'var(--muted-foreground)',
                                                            transform: isExpanded ? 'rotate(0deg)' : 'rotate(-90deg)',
                                                            transition: 'transform 140ms ease',
                                                        }}
                                                    />
                                                    {tx.invoice_number}
                                                </div>
                                                <div style={{ color: 'var(--muted-foreground)', fontSize: '12px', marginLeft: '23px' }}>
                                                    {formatDate(tx.created_at)}
                                                </div>
                                            </td>
                                            <td style={{ padding: '12px 16px' }}>
                                                {tx.customer_name ?? 'Umum'}
                                            </td>
                                            <td style={{ padding: '12px 16px' }}>
                                                <PosBadge color={tx.payment_status === 'paid' ? 'green' : 'amber'}>
                                                    {paymentStatusLabel(tx.payment_status)}
                                                </PosBadge>
                                            </td>
                                            <td style={{ padding: '12px 16px', textAlign: 'right', fontWeight: 800 }}>
                                                {formatCurrency(tx.grand_total)}
                                            </td>
                                        </tr>

                                        {/* Expanded detail row */}
                                        {isExpanded && (
                                            <tr style={{ borderBottom: '1px solid var(--border)' }}>
                                                <td colSpan={4} style={{ padding: '0 16px 14px 39px' }}>
                                                    <div style={{ borderTop: '1px solid var(--border)', paddingTop: '12px', display: 'grid', gap: '10px' }}>

                                                        {/* Item lines */}
                                                        {tx.items.length === 0 ? (
                                                            <div style={{ color: 'var(--muted-foreground)', fontSize: '12px' }}>
                                                                Detail pesanan tidak tersedia.
                                                            </div>
                                                        ) : (
                                                            tx.items.map((item) => (
                                                                <div
                                                                    key={item.id}
                                                                    style={{
                                                                        display: 'grid',
                                                                        gridTemplateColumns: 'minmax(0, 1fr) 88px 120px',
                                                                        gap: '12px',
                                                                        alignItems: 'center',
                                                                        fontSize: '12px',
                                                                    }}
                                                                >
                                                                    <div>
                                                                        <div style={{ color: 'var(--foreground)', fontWeight: 700 }}>
                                                                            {item.product_name}
                                                                        </div>
                                                                        <div style={{ color: 'var(--muted-foreground)', marginTop: '2px' }}>
                                                                            {item.product_sku ?? 'Tanpa SKU'} x{item.quantity} @ {formatCurrency(item.unit_price)}
                                                                        </div>
                                                                    </div>
                                                                    <div style={{ color: 'var(--muted-foreground)', textAlign: 'right' }}>
                                                                        Qty {item.quantity}
                                                                    </div>
                                                                    <div style={{ color: 'var(--foreground)', fontWeight: 800, textAlign: 'right' }}>
                                                                        {formatCurrency(item.line_total)}
                                                                    </div>
                                                                </div>
                                                            ))
                                                        )}

                                                        {/* Payment summary + settlement form */}
                                                        <div style={{ borderTop: '1px solid var(--border)', paddingTop: '10px', display: 'grid', gap: '6px', maxWidth: '280px', marginLeft: 'auto' }}>
                                                            <SummaryRow label="Total" value={formatCurrency(tx.grand_total)} strong />
                                                            <SummaryRow label="Bayar" value={formatCurrency(tx.paid_amount)} />

                                                            {tx.payment_status !== 'paid' && (
                                                                <div style={{ display: 'grid', gap: '8px', marginTop: '4px' }}>
                                                                    <select
                                                                        value={getForm(tx).payment_method}
                                                                        onChange={(e) => updateForm(tx.id, { payment_method: e.target.value })}
                                                                        style={{ ...inputStyle, minHeight: '34px', fontSize: '12px' }}
                                                                    >
                                                                        {paymentMethods.map((m) => (
                                                                            <option key={m.value} value={m.value}>{m.label}</option>
                                                                        ))}
                                                                    </select>
                                                                    <input
                                                                        type="number"
                                                                        min={1}
                                                                        value={getForm(tx).paid_amount}
                                                                        onChange={(e) => updateForm(tx.id, { paid_amount: e.target.value })}
                                                                        placeholder="Jumlah bayar pelunasan"
                                                                        style={{ ...inputStyle, minHeight: '34px', fontSize: '12px' }}
                                                                    />
                                                                </div>
                                                            )}

                                                            {tx.payment_status !== 'paid' && (
                                                                <SummaryRow label="Sisa" value={formatCurrency(remainingPayment(tx))} strong />
                                                            )}

                                                            <SummaryRow
                                                                label="Kembalian"
                                                                value={formatCurrency(
                                                                    tx.payment_status === 'paid'
                                                                        ? tx.change_amount
                                                                        : settleChangeAmount(tx),
                                                                )}
                                                            />

                                                            {errors.paid_amount && (
                                                                <div style={{ color: 'hsl(0 72% 40%)', fontSize: '11px' }}>
                                                                    {errors.paid_amount}
                                                                </div>
                                                            )}

                                                            {tx.payment_status !== 'paid' && (
                                                                <button
                                                                    onClick={() => settle(tx)}
                                                                    disabled={processingId === tx.id}
                                                                    style={{
                                                                        minHeight: '36px',
                                                                        borderRadius: '8px',
                                                                        border: 'none',
                                                                        backgroundColor: processingId === tx.id ? 'var(--muted)' : 'hsl(142 70% 36%)',
                                                                        color: processingId === tx.id ? 'var(--muted-foreground)' : 'white',
                                                                        cursor: processingId === tx.id ? 'not-allowed' : 'pointer',
                                                                        fontWeight: 800,
                                                                        display: 'inline-flex',
                                                                        alignItems: 'center',
                                                                        justifyContent: 'center',
                                                                        gap: '8px',
                                                                    }}
                                                                >
                                                                    <Banknote size={15} />
                                                                    {processingId === tx.id ? 'Memproses...' : 'Lunasi'}
                                                                </button>
                                                            )}
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        )}
                                    </React.Fragment>
                                );
                            })
                        )}
                    </tbody>
                </table>
            </div>
        </div>
    );
}