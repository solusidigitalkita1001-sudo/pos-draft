import { Head, router } from '@inertiajs/react';
import { Receipt } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { useCart } from '@/hooks/use-cart';
import { useProductSearch } from '@/hooks/use-product-search';
import type { AppliedVoucher, AvailableVoucher, PaymentMethod, PosItem, RecentTransaction } from '@/types/pos';
import { CartPanel } from './components/cart-panel';
import { ProductGrid } from './components/product-grid';
import { RecentTransactions } from './components/recent-transactions';
import { parseNumberInput } from './pos-utils';

interface Props {
    products: PosItem[];
    recentTransactions: RecentTransaction[];
    vouchers: AvailableVoucher[];
    teamSlug: string;
    paymentMethods: PaymentMethod[];
    canApplyVoucher: boolean;
}

export default function PosIndex({ products, recentTransactions, vouchers, teamSlug, paymentMethods, canApplyVoucher }: Props) {
    const defaultPaymentMethod = paymentMethods[0]?.value ?? 'cash';

    // ── Hooks ──────────────────────────────────────────────────────────────────
    const { cart, subtotal, addToCart, setQuantity, removeFromCart, clearCart } = useCart();
    const { search, setSearch, filteredProducts, loading } = useProductSearch(teamSlug, products);

    // ── Checkout form state ────────────────────────────────────────────────────
    const [customerName,   setCustomerName]   = useState('');
    const [voucherCode,    setVoucherCode]     = useState('');
    const [paymentMethod,  setPaymentMethod]   = useState(defaultPaymentMethod);
    const [paidAmount,     setPaidAmount]      = useState('');
    const [note,           setNote]            = useState('');
    const [processing,     setProcessing]      = useState(false);
    const [errors,         setErrors]          = useState<Record<string, string>>({});
    const [appliedVoucher, setAppliedVoucher]  = useState<AppliedVoucher | null>(null);
    const [voucherMessage, setVoucherMessage]  = useState<string | null>(null);
    const [voucherChecking, setVoucherChecking] = useState(false);

    const activeVoucher = voucherCode.trim() !== ''
        && subtotal > 0
        && cart.length > 0
        && appliedVoucher?.code === voucherCode.trim()
        ? appliedVoucher
        : null;
    const activeVoucherMessage = voucherCode.trim() !== '' && !activeVoucher ? voucherMessage : null;
    const discountTotal = activeVoucher?.discount_total ?? 0;
    const grandTotal = useMemo(() => Math.max(subtotal - discountTotal, 0), [discountTotal, subtotal]);

    useEffect(() => {
        if (!canApplyVoucher) {
            return;
        }

        const code = voucherCode.trim();

        if (!code || subtotal <= 0 || cart.length === 0) {
            return;
        }

        const controller = new AbortController();
        const timeout = window.setTimeout(() => {
            setVoucherChecking(true);

            fetch(`/${teamSlug}/pos/voucher/validate`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...csrfHeaders(),
                },
                body: JSON.stringify({ voucher_code: code, subtotal }),
                signal: controller.signal,
            })
                .then(async (response) => {
                    const data = (await response.json()) as ValidateVoucherResponse;

                    if (!response.ok || !data.valid) {
                        throw new Error(data.message ?? 'Voucher tidak valid atau tidak memenuhi syarat transaksi.');
                    }

                    setAppliedVoucher({
                        ...data.voucher,
                        discount_total: Number(data.discount_total ?? 0),
                    });
                    setVoucherMessage(data.message ?? null);
                })
                .catch((error: unknown) => {
                    if (error instanceof DOMException && error.name === 'AbortError') {
                        return;
                    }

                    setAppliedVoucher(null);
                    setVoucherMessage(error instanceof Error ? error.message : 'Voucher tidak valid.');
                })
                .finally(() => setVoucherChecking(false));
        }, 320);

        return () => {
            window.clearTimeout(timeout);
            controller.abort();
        };
    }, [canApplyVoucher, cart.length, subtotal, teamSlug, voucherCode]);

    // ── Validation ────────────────────────────────────────────────────────────
    function validateCheckout(): string | null {
        const paid = parseNumberInput(paidAmount);

        if (cart.length === 0) {
            return 'Keranjang transaksi masih kosong.';
        }

        if (!paymentMethod) {
            return 'Metode pembayaran wajib dipilih.';
        }

        if (!paidAmount.trim()) {
            return 'Jumlah bayar wajib diisi.';
        }

        if (!Number.isFinite(paid) || paid <= 0) {
            return 'Jumlah bayar wajib lebih dari 0.';
        }

        if (paid < grandTotal) {
            return 'Nominal pembayaran kurang dari total transaksi.';
        }

        return null;
    }

    // ── Submit ────────────────────────────────────────────────────────────────
    function submitTransaction() {
        setErrors({});
        const error = validateCheckout();

        if (error) {
            setErrors({ paid_amount: error });

            return;
        }

        setProcessing(true);

        router.post(
            `/${teamSlug}/pos/transaction`,
            {
                customer_name:  customerName  || null,
                voucher_code:   voucherCode   || null,
                payment_method: paymentMethod,
                paid_amount:    String(parseNumberInput(paidAmount)),
                note:           note          || null,
                items: cart.map((item) => ({
                    item_type: item.product.item_type,
                    item_id:   item.product.item_id,
                    quantity:  item.quantity,
                })),
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    clearCart();
                    setCustomerName('');
                    setVoucherCode('');
                    setAppliedVoucher(null);
                    setVoucherMessage(null);
                    setPaidAmount('');
                    setNote('');
                },
                onError:  (e) => setErrors(e),
                onFinish: ()  => setProcessing(false),
            },
        );
    }

    return (
        <>
            <Head title="POS Kasir" />

            <div style={{ display: 'flex', flexDirection: 'column', gap: '20px' }}>
                {/* Page header */}
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: '16px' }}>
                    <div>
                        <h1 style={{ margin: '0 0 4px', color: 'var(--foreground)', fontSize: '24px', fontWeight: 800 }}>
                            POS Kasir
                        </h1>
                        <p style={{ margin: 0, color: 'var(--muted-foreground)', fontSize: '13px' }}>
                            Buat transaksi penjualan dan stok produk otomatis berkurang.
                        </p>
                    </div>
                    <div style={{ display: 'inline-flex', alignItems: 'center', gap: '8px', color: 'var(--muted-foreground)', fontSize: '13px' }}>
                        <Receipt size={16} />
                        {cart.length} item
                    </div>
                </div>

                {/* Main two-column layout */}
                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: 'minmax(0, 1fr) 420px',
                        gap: '20px',
                        alignItems: 'start',
                    }}
                >
                    {/* Left: product catalogue + recent transactions */}
                    <div style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>
                        <ProductGrid
                            search={search}
                            onSearchChange={setSearch}
                            products={filteredProducts}
                            loading={loading}
                            onSelectProduct={addToCart}
                        />
                        <RecentTransactions
                            transactions={recentTransactions}
                            paymentMethods={paymentMethods}
                            defaultPaymentMethod={defaultPaymentMethod}
                            teamSlug={teamSlug}
                        />
                    </div>

                    {/* Right: cart + checkout */}
                    <CartPanel
                        cart={cart}
                        subtotal={subtotal}
                        paymentMethods={paymentMethods}
                        vouchers={vouchers}
                        canApplyVoucher={canApplyVoucher}
                        appliedVoucher={activeVoucher}
                        voucherMessage={activeVoucherMessage}
                        voucherChecking={voucherChecking}
                        customerName={customerName}
                        voucherCode={voucherCode}
                        paymentMethod={paymentMethod}
                        paidAmount={paidAmount}
                        note={note}
                        processing={processing}
                        errors={errors}
                        onSetQuantity={setQuantity}
                        onRemoveItem={removeFromCart}
                        onClearCart={clearCart}
                        onSetCustomerName={setCustomerName}
                        onSetVoucherCode={(value) => {
                            setVoucherCode(value);
                            setVoucherMessage(null);
                        }}
                        onSetPaymentMethod={setPaymentMethod}
                        onSetPaidAmount={setPaidAmount}
                        onClearPaidAmountError={() => setErrors((e) => {
                            const n = { ...e };
                            delete n.paid_amount;

                            return n;
                        })}
                        onSetNote={setNote}
                        onSubmit={submitTransaction}
                    />
                </div>
            </div>
        </>
    );
}

interface ValidateVoucherResponse {
    valid: boolean;
    voucher: Omit<AppliedVoucher, 'discount_total'>;
    discount_total: number | string;
    message?: string;
}

function csrfHeaders(): Record<string, string> {
    const token = document.cookie
        .split('; ')
        .find((row) => row.startsWith('XSRF-TOKEN='))
        ?.split('=')[1];

    return token ? { 'X-XSRF-TOKEN': decodeURIComponent(token) } : {};
}
