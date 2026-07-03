import { Banknote, Minus, Plus, ShoppingCart, Trash2 } from 'lucide-react';
import { useMemo } from 'react';
import type { AppliedVoucher, AvailableVoucher, CartItem, PaymentMethod, PosItem } from '@/types/pos';
import {
    formatNumberInput,
    formatCurrency,
    inputStyle,
    itemTypeLabel,
    parseNumberInput,
    PosBadge,
    SearchableSelect,
    SummaryRow,
} from '../pos-utils';
import type { SearchableSelectOption } from '../pos-utils';

interface Props {
    cart: CartItem[];
    subtotal: number;
    paymentMethods: PaymentMethod[];
    vouchers: AvailableVoucher[];
    canApplyVoucher: boolean;
    appliedVoucher: AppliedVoucher | null;
    voucherMessage: string | null;
    voucherChecking: boolean;

    // Form state
    customerName: string;
    voucherCode: string;
    paymentMethod: string;
    paidAmount: string;
    note: string;
    processing: boolean;
    errors: Record<string, string>;

    // Callbacks
    onSetQuantity: (product: PosItem, qty: number) => void;
    onRemoveItem: (product: PosItem) => void;
    onClearCart: () => void;
    onSetCustomerName: (v: string) => void;
    onSetVoucherCode: (v: string) => void;
    onSetPaymentMethod: (v: string) => void;
    onSetPaidAmount: (v: string) => void;
    onClearPaidAmountError: () => void;
    onSetNote: (v: string) => void;
    onSubmit: () => void;
}

export function CartPanel({
    cart,
    subtotal,
    paymentMethods,
    vouchers,
    canApplyVoucher,
    appliedVoucher,
    voucherMessage,
    voucherChecking,
    customerName,
    voucherCode,
    paymentMethod,
    paidAmount,
    note,
    processing,
    errors,
    onSetQuantity,
    onRemoveItem,
    onClearCart,
    onSetCustomerName,
    onSetVoucherCode,
    onSetPaymentMethod,
    onSetPaidAmount,
    onClearPaidAmountError,
    onSetNote,
    onSubmit,
}: Props) {
    const paid = parseNumberInput(paidAmount);
    const discountTotal = appliedVoucher?.discount_total ?? 0;
    const grandTotal = Math.max(subtotal - discountTotal, 0);
    const changeAmount = Math.max(paid - grandTotal, 0);
    const paymentOptions = useMemo<SearchableSelectOption[]>(
        () => paymentMethods.map((method) => ({ value: method.value, label: method.label })),
        [paymentMethods],
    );
    const voucherOptions = useMemo<SearchableSelectOption[]>(
        () => [
            { value: '', label: 'Tanpa voucher' },
            ...vouchers.map((voucher) => ({
                value: voucher.code,
                label: `${voucher.code} - ${voucher.name}`,
                description: voucherDescription(voucher),
            })),
        ],
        [vouchers],
    );

    return (
        <div
            style={{
                position: 'sticky',
                top: '20px',
                border: '1px solid var(--border)',
                borderRadius: '8px',
                backgroundColor: 'var(--card)',
                overflow: 'hidden',
            }}
        >
            {/* Header */}
            <div
                style={{
                    padding: '14px 16px',
                    borderBottom: '1px solid var(--border)',
                    display: 'flex',
                    justifyContent: 'space-between',
                    alignItems: 'center',
                }}
            >
                <strong style={{ display: 'inline-flex', alignItems: 'center', gap: '8px' }}>
                    <ShoppingCart size={16} />
                    Keranjang
                </strong>

                {cart.length > 0 && (
                    <button
                        onClick={onClearCart}
                        style={{
                            border: 'none',
                            background: 'none',
                            color: 'hsl(0 72% 50%)',
                            cursor: 'pointer',
                            fontSize: '12px',
                            fontWeight: 700,
                        }}
                    >
                        Kosongkan
                    </button>
                )}
            </div>

            <div style={{ padding: '16px', display: 'flex', flexDirection: 'column', gap: '14px' }}>

                {/* Cart items */}
                {cart.length === 0 ? (
                    <div
                        style={{
                            padding: '40px 16px',
                            textAlign: 'center',
                            color: 'var(--muted-foreground)',
                            fontSize: '13px',
                        }}
                    >
                        Pilih item untuk memulai transaksi.
                    </div>
                ) : (
                    cart.map((item) => (
                        <CartItemRow
                            key={`${item.product.item_type}:${item.product.item_id}`}
                            item={item}
                            onSetQuantity={onSetQuantity}
                            onRemove={onRemoveItem}
                        />
                    ))
                )}

                {/* Checkout form */}
                <div style={{ display: 'flex', flexDirection: 'column', gap: '10px' }}>
                    <input
                        value={customerName}
                        onChange={(e) => onSetCustomerName(e.target.value)}
                        placeholder="Nama pelanggan (opsional)"
                        style={inputStyle}
                    />

                    {canApplyVoucher && (
                        <div style={{ display: 'grid', gap: '6px' }}>
                            <SearchableSelect
                                value={voucherCode}
                                options={voucherOptions}
                                placeholder="Kode voucher (opsional)"
                                searchPlaceholder="Cari kode atau nama voucher..."
                                emptyText="Voucher tidak ditemukan."
                                onChange={onSetVoucherCode}
                            />

                            {voucherChecking && (
                                <div style={{ color: 'var(--muted-foreground)', fontSize: '12px' }}>
                                    Mengecek voucher...
                                </div>
                            )}

                            {!voucherChecking && appliedVoucher && (
                                <div style={{ color: 'hsl(142 70% 32%)', fontSize: '12px', fontWeight: 700 }}>
                                    Voucher {appliedVoucher.code}: -{formatCurrency(appliedVoucher.discount_total)}
                                </div>
                            )}

                            {!voucherChecking && voucherMessage && !appliedVoucher && (
                                <div style={{ color: 'hsl(0 72% 40%)', fontSize: '12px' }}>
                                    {voucherMessage}
                                </div>
                            )}
                        </div>
                    )}

                    <SearchableSelect
                        value={paymentMethod}
                        options={paymentOptions}
                        placeholder="Pilih metode pembayaran"
                        searchPlaceholder="Cari metode pembayaran..."
                        onChange={onSetPaymentMethod}
                    />

                    <input
                        type="text"
                        inputMode="numeric"
                        value={paidAmount}
                        onChange={(e) => {
                            onSetPaidAmount(formatNumberInput(e.target.value));
                            onClearPaidAmountError();
                        }}
                        placeholder="Jumlah bayar"
                        style={inputStyle}
                    />

                    <textarea
                        value={note}
                        onChange={(e) => onSetNote(e.target.value)}
                        placeholder="Catatan (opsional)"
                        style={{ ...inputStyle, height: '68px', paddingTop: '10px', resize: 'vertical' }}
                    />
                </div>

                {/* Validation error */}
                {Object.keys(errors).length > 0 && (
                    <div
                        style={{
                            border: '1px solid hsl(0 72% 80%)',
                            borderRadius: '8px',
                            backgroundColor: 'hsl(0 72% 96%)',
                            color: 'hsl(0 72% 36%)',
                            padding: '10px 12px',
                            fontSize: '12px',
                        }}
                    >
                        {Object.values(errors)[0]}
                    </div>
                )}

                {/* Summary */}
                <div
                    style={{
                        display: 'flex',
                        flexDirection: 'column',
                        gap: '8px',
                        borderTop: '1px solid var(--border)',
                        paddingTop: '14px',
                    }}
                >
                    <SummaryRow label="Subtotal"  value={formatCurrency(subtotal)} />
                    {discountTotal > 0 && (
                        <SummaryRow
                            label={`Voucher ${appliedVoucher?.code ?? ''}`.trim()}
                            value={`-${formatCurrency(discountTotal)}`}
                        />
                    )}
                    <SummaryRow label="Total"     value={formatCurrency(grandTotal)} strong />
                    <SummaryRow label="Bayar"     value={formatCurrency(paid)} />
                    <SummaryRow label="Kembalian" value={formatCurrency(changeAmount)} strong />
                </div>

                {/* Submit button */}
                <button
                    onClick={onSubmit}
                    disabled={processing || cart.length === 0}
                    style={{
                        height: '44px',
                        borderRadius: '8px',
                        border: 'none',
                        backgroundColor: processing || cart.length === 0 ? 'var(--muted)' : 'hsl(142 70% 36%)',
                        color: processing || cart.length === 0 ? 'var(--muted-foreground)' : 'white',
                        cursor: processing || cart.length === 0 ? 'not-allowed' : 'pointer',
                        fontWeight: 800,
                        display: 'inline-flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        gap: '8px',
                    }}
                >
                    <Banknote size={16} />
                    {processing ? 'Memproses...' : 'Simpan Transaksi'}
                </button>
            </div>
        </div>
    );
}

// ─── Cart Item Row ────────────────────────────────────────────────────────────

function CartItemRow({
    item,
    onSetQuantity,
    onRemove,
}: {
    item: CartItem;
    onSetQuantity: (product: PosItem, qty: number) => void;
    onRemove: (product: PosItem) => void;
}) {
    const lineTotal = parseFloat(item.product.price) * item.quantity;

    return (
        <div
            style={{
                display: 'grid',
                gridTemplateColumns: '1fr auto',
                gap: '12px',
                borderBottom: '1px solid var(--border)',
                paddingBottom: '12px',
            }}
        >
            <div>
                <div style={{ fontWeight: 700, fontSize: '13px' }}>{item.product.name}</div>
                <div style={{ color: 'var(--muted-foreground)', fontSize: '12px', marginTop: '2px' }}>
                    {formatCurrency(item.product.price)}{' '}
                    <PosBadge color="blue">{itemTypeLabel(item.product.item_type)}</PosBadge>
                </div>

                {/* Qty stepper */}
                <div style={{ display: 'inline-flex', alignItems: 'center', gap: '8px', marginTop: '10px' }}>
                    <QtyButton onClick={() => onSetQuantity(item.product, item.quantity - 1)}>
                        <Minus size={14} />
                    </QtyButton>
                    <input
                        type="number"
                        value={item.quantity}
                        onChange={(e) => onSetQuantity(item.product, Number(e.target.value))}
                        style={{
                            width: '48px',
                            height: '28px',
                            borderRadius: '6px',
                            border: '1px solid var(--border)',
                            textAlign: 'center',
                            backgroundColor: 'var(--background)',
                            color: 'var(--foreground)',
                            fontSize: '13px',
                        }}
                    />
                    <QtyButton onClick={() => onSetQuantity(item.product, item.quantity + 1)}>
                        <Plus size={14} />
                    </QtyButton>
                </div>
            </div>

            <div style={{ textAlign: 'right' }}>
                <button
                    onClick={() => onRemove(item.product)}
                    style={{ border: 'none', background: 'none', color: 'hsl(0 72% 50%)', cursor: 'pointer', padding: '2px' }}
                >
                    <Trash2 size={15} />
                </button>
                <div style={{ marginTop: '24px', fontWeight: 800 }}>{formatCurrency(lineTotal)}</div>
            </div>
        </div>
    );
}

function QtyButton({ onClick, children }: { onClick: () => void; children: React.ReactNode }) {
    return (
        <button
            onClick={onClick}
            style={{
                width: '28px',
                height: '28px',
                borderRadius: '6px',
                border: '1px solid var(--border)',
                backgroundColor: 'var(--background)',
                cursor: 'pointer',
                display: 'inline-flex',
                alignItems: 'center',
                justifyContent: 'center',
            }}
        >
            {children}
        </button>
    );
}

function voucherDescription(voucher: AvailableVoucher): string {
    const discount = voucher.type === 'percent'
        ? `${Number(voucher.value)}%`
        : formatCurrency(voucher.value);
    const minPurchase = parseFloat(voucher.min_purchase || '0');
    const maxDiscount = voucher.max_discount ? parseFloat(voucher.max_discount) : 0;

    return [
        `Diskon ${discount}`,
        minPurchase > 0 ? `min. ${formatCurrency(minPurchase)}` : null,
        maxDiscount > 0 ? `maks. ${formatCurrency(maxDiscount)}` : null,
    ].filter(Boolean).join(' | ');
}
