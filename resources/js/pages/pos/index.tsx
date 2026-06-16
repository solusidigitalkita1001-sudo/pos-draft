import { Head, router } from '@inertiajs/react';
import { Receipt } from 'lucide-react';
import { useState } from 'react';
import type { PaymentMethod, PosItem, RecentTransaction } from '@/types/pos';
import { useCart } from '@/hooks/use-cart';
import { useProductSearch } from '@/hooks/use-product-search';
import { CartPanel } from './components/cart-panel';
import { ProductGrid } from './components/product-grid';
import { RecentTransactions } from './components/recent-transactions';

interface Props {
    products: PosItem[];
    recentTransactions: RecentTransaction[];
    teamSlug: string;
    paymentMethods: PaymentMethod[];
    canApplyVoucher: boolean;
}

export default function PosIndex({ products, recentTransactions, teamSlug, paymentMethods, canApplyVoucher }: Props) {
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

    // ── Validation ────────────────────────────────────────────────────────────
    function validateCheckout(): string | null {
        const paid = parseFloat(paidAmount || '0');
        if (cart.length === 0)                       return 'Keranjang transaksi masih kosong.';
        if (!paymentMethod)                          return 'Metode pembayaran wajib dipilih.';
        if (!paidAmount.trim())                      return 'Jumlah bayar wajib diisi.';
        if (!Number.isFinite(paid) || paid <= 0)     return 'Jumlah bayar wajib lebih dari 0.';
        if (paid < subtotal)                         return 'Nominal pembayaran kurang dari total transaksi.';
        return null;
    }

    // ── Submit ────────────────────────────────────────────────────────────────
    function submitTransaction() {
        setErrors({});
        const error = validateCheckout();
        if (error) { setErrors({ paid_amount: error }); return; }

        setProcessing(true);

        router.post(
            `/${teamSlug}/pos/transaction`,
            {
                customer_name:  customerName  || null,
                voucher_code:   voucherCode   || null,
                payment_method: paymentMethod,
                paid_amount:    paidAmount    || '0',
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
                        canApplyVoucher={canApplyVoucher}
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
                        onSetVoucherCode={setVoucherCode}
                        onSetPaymentMethod={setPaymentMethod}
                        onSetPaidAmount={setPaidAmount}
                        onClearPaidAmountError={() => setErrors((e) => { const n = { ...e }; delete n.paid_amount; return n; })}
                        onSetNote={setNote}
                        onSubmit={submitTransaction}
                    />
                </div>
            </div>
        </>
    );
}