import React from 'react';
import type { PosItem, RecentTransaction } from '@/types/pos';

// ─── Formatters ───────────────────────────────────────────────────────────────

export function formatCurrency(value: string | number): string {
    const amount = typeof value === 'number' ? value : parseFloat(value || '0');

    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(amount);
}

export function formatDate(value: string): string {
    return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

export function paymentStatusLabel(status: RecentTransaction['payment_status']): string {
    const labels: Record<RecentTransaction['payment_status'], string> = {
        paid: 'Lunas',
        partial: 'Sebagian',
        unpaid: 'Belum Bayar',
    };

    return labels[status] ?? status;
}

export function itemTypeLabel(type: PosItem['item_type']): string {
    const labels: Record<PosItem['item_type'], string> = {
        package: 'Paket',
        promotion: 'Promosi',
        product: 'Produk',
    };

    return labels[type] ?? type;
}

export function remainingPayment(transaction: RecentTransaction): number {
    return Math.max(
        parseFloat(transaction.grand_total || '0') - parseFloat(transaction.paid_amount || '0'),
        0,
    );
}

// ─── Shared UI Atoms ──────────────────────────────────────────────────────────

type BadgeColor = 'default' | 'green' | 'amber' | 'red' | 'blue';

const BADGE_COLORS: Record<BadgeColor, { bg: string; text: string }> = {
    default: { bg: 'var(--muted)',         text: 'var(--muted-foreground)' },
    green:   { bg: 'hsl(142 76% 92%)',     text: 'hsl(142 76% 30%)' },
    amber:   { bg: 'hsl(43 96% 92%)',      text: 'hsl(43 96% 30%)' },
    red:     { bg: 'hsl(0 72% 94%)',       text: 'hsl(0 72% 40%)' },
    blue:    { bg: 'hsl(214 100% 95%)',    text: 'hsl(214 100% 40%)' },
};

export function PosBadge({
    children,
    color = 'default',
}: {
    children: React.ReactNode;
    color?: BadgeColor;
}) {
    const { bg, text } = BADGE_COLORS[color];

    return (
        <span
            style={{
                display: 'inline-flex',
                alignItems: 'center',
                padding: '2px 8px',
                borderRadius: '999px',
                backgroundColor: bg,
                color: text,
                fontSize: '11px',
                fontWeight: 700,
            }}
        >
            {children}
        </span>
    );
}

export function SummaryRow({
    label,
    value,
    strong = false,
}: {
    label: string;
    value: string;
    strong?: boolean;
}) {
    return (
        <div
            style={{
                display: 'flex',
                justifyContent: 'space-between',
                color: strong ? 'var(--foreground)' : 'var(--muted-foreground)',
                fontSize: strong ? '15px' : '13px',
                fontWeight: strong ? 800 : 500,
            }}
        >
            <span>{label}</span>
            <span>{value}</span>
        </div>
    );
}

export const inputStyle: React.CSSProperties = {
    width: '100%',
    minHeight: '38px',
    borderRadius: '8px',
    border: '1px solid var(--border)',
    backgroundColor: 'var(--background)',
    color: 'var(--foreground)',
    fontSize: '13px',
    padding: '0 12px',
    outline: 'none',
    boxSizing: 'border-box',
};