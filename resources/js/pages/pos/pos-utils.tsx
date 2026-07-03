import { ChevronDown, Search } from 'lucide-react';
import React, { useMemo, useRef, useState } from 'react';
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

export function parseNumberInput(value: string): number {
    const digits = value.replace(/\D/g, '');

    return digits ? Number(digits) : 0;
}

export function formatNumberInput(value: string): string {
    const amount = parseNumberInput(value);

    if (amount <= 0) {
        return '';
    }

    return new Intl.NumberFormat('id-ID', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(amount);
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

export interface SearchableSelectOption {
    value: string;
    label: string;
    description?: string;
}

export function SearchableSelect({
    value,
    options,
    placeholder,
    searchPlaceholder = 'Cari opsi...',
    emptyText = 'Tidak ada opsi.',
    onChange,
    disabled = false,
    style,
}: {
    value: string;
    options: SearchableSelectOption[];
    placeholder: string;
    searchPlaceholder?: string;
    emptyText?: string;
    onChange: (value: string) => void;
    disabled?: boolean;
    style?: React.CSSProperties;
}) {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const rootRef = useRef<HTMLDivElement>(null);
    const selected = options.find((option) => option.value === value);
    const filteredOptions = useMemo(() => {
        const keyword = query.trim().toLowerCase();

        if (!keyword) {
            return options;
        }

        return options.filter((option) => (
            option.label.toLowerCase().includes(keyword)
            || option.description?.toLowerCase().includes(keyword)
            || option.value.toLowerCase().includes(keyword)
        ));
    }, [options, query]);

    return (
        <div
            ref={rootRef}
            onBlur={(event) => {
                if (!rootRef.current?.contains(event.relatedTarget as Node | null)) {
                    setOpen(false);
                }
            }}
            style={{ position: 'relative', ...style }}
        >
            <button
                type="button"
                disabled={disabled}
                onClick={() => setOpen((current) => !current)}
                style={{
                    ...inputStyle,
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'space-between',
                    gap: '8px',
                    textAlign: 'left',
                    cursor: disabled ? 'not-allowed' : 'pointer',
                    color: selected ? 'var(--foreground)' : 'var(--muted-foreground)',
                }}
            >
                <span style={{ overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                    {selected?.label ?? placeholder}
                </span>
                <ChevronDown size={15} style={{ flexShrink: 0, color: 'var(--muted-foreground)' }} />
            </button>

            {open && (
                <div
                    style={{
                        position: 'absolute',
                        zIndex: 30,
                        top: 'calc(100% + 4px)',
                        left: 0,
                        right: 0,
                        border: '1px solid var(--border)',
                        borderRadius: '8px',
                        backgroundColor: 'var(--card)',
                        boxShadow: '0 12px 28px rgb(0 0 0 / 12%)',
                        overflow: 'hidden',
                    }}
                >
                    <div style={{ position: 'relative', padding: '8px' }}>
                        <Search
                            size={14}
                            style={{
                                position: 'absolute',
                                top: '50%',
                                left: '18px',
                                transform: 'translateY(-50%)',
                                color: 'var(--muted-foreground)',
                            }}
                        />
                        <input
                            autoFocus
                            value={query}
                            onChange={(event) => setQuery(event.target.value)}
                            placeholder={searchPlaceholder}
                            style={{ ...inputStyle, minHeight: '34px', paddingLeft: '32px', fontSize: '12px' }}
                        />
                    </div>

                    <div style={{ maxHeight: '190px', overflowY: 'auto', padding: '4px' }}>
                        {filteredOptions.length === 0 ? (
                            <div style={{ padding: '10px 12px', color: 'var(--muted-foreground)', fontSize: '12px' }}>
                                {emptyText}
                            </div>
                        ) : (
                            filteredOptions.map((option) => (
                                <button
                                    key={option.value}
                                    type="button"
                                    onMouseDown={(event) => event.preventDefault()}
                                    onClick={() => {
                                        onChange(option.value);
                                        setQuery('');
                                        setOpen(false);
                                    }}
                                    style={{
                                        width: '100%',
                                        border: 'none',
                                        borderRadius: '6px',
                                        backgroundColor: option.value === value ? 'var(--muted)' : 'transparent',
                                        color: 'var(--foreground)',
                                        cursor: 'pointer',
                                        display: 'grid',
                                        gap: '2px',
                                        padding: '8px',
                                        textAlign: 'left',
                                    }}
                                >
                                    <span style={{ fontSize: '13px', fontWeight: 700 }}>{option.label}</span>
                                    {option.description && (
                                        <span style={{ color: 'var(--muted-foreground)', fontSize: '11px' }}>
                                            {option.description}
                                        </span>
                                    )}
                                </button>
                            ))
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}
