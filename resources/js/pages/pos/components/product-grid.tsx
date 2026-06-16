import { Search } from 'lucide-react';
import type { PosItem } from '@/types/pos';
import { formatCurrency, itemTypeLabel, PosBadge } from '../pos-utils';

interface Props {
    search: string;
    onSearchChange: (value: string) => void;
    products: PosItem[];
    loading: boolean;
    onSelectProduct: (product: PosItem) => void;
}

export function ProductGrid({ search, onSearchChange, products, loading, onSelectProduct }: Props) {
    return (
        <div style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>
            {/* Search bar */}
            <div style={{ position: 'relative' }}>
                <Search
                    size={16}
                    style={{
                        position: 'absolute',
                        left: '12px',
                        top: '50%',
                        transform: 'translateY(-50%)',
                        color: 'var(--muted-foreground)',
                    }}
                />
                <input
                    value={search}
                    onChange={(e) => onSearchChange(e.target.value)}
                    placeholder="Cari produk, paket, promosi, SKU, atau kategori..."
                    style={{
                        width: '100%',
                        height: '40px',
                        paddingLeft: '40px',
                        paddingRight: '12px',
                        borderRadius: '8px',
                        border: '1px solid var(--border)',
                        backgroundColor: 'var(--background)',
                        color: 'var(--foreground)',
                        fontSize: '13px',
                        outline: 'none',
                        boxSizing: 'border-box',
                    }}
                />
            </div>

            {/* Product catalogue */}
            <div
                style={{
                    border: '1px solid var(--border)',
                    borderRadius: '8px',
                    backgroundColor: 'var(--card)',
                    overflow: 'hidden',
                }}
            >
                <div
                    style={{
                        padding: '14px 16px',
                        borderBottom: '1px solid var(--border)',
                        display: 'flex',
                        justifyContent: 'space-between',
                        alignItems: 'center',
                    }}
                >
                    <strong style={{ fontSize: '14px' }}>Item Tersedia</strong>
                    <span style={{ color: 'var(--muted-foreground)', fontSize: '12px' }}>
                        {loading ? 'Memuat...' : `${products.length} item`}
                    </span>
                </div>

                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: 'repeat(auto-fill, minmax(180px, 1fr))',
                        gap: '12px',
                        padding: '16px',
                    }}
                >
                    {products.length === 0 ? (
                        <div
                            style={{
                                gridColumn: '1 / -1',
                                padding: '32px 16px',
                                textAlign: 'center',
                                color: 'var(--muted-foreground)',
                                fontSize: '13px',
                            }}
                        >
                            {loading ? 'Memuat produk...' : 'Item tidak ditemukan.'}
                        </div>
                    ) : (
                        products.map((product) => (
                            <ProductCard
                                key={`${product.item_type}:${product.item_id}`}
                                product={product}
                                onSelect={onSelectProduct}
                            />
                        ))
                    )}
                </div>
            </div>
        </div>
    );
}

function ProductCard({ product, onSelect }: { product: PosItem; onSelect: (p: PosItem) => void }) {
    const isLowStock = product.stock <= product.min_stock;

    return (
        <button
            onClick={() => onSelect(product)}
            disabled={product.stock === 0}
            style={{
                textAlign: 'left',
                border: '1px solid var(--border)',
                backgroundColor: product.stock === 0 ? 'var(--muted)' : 'var(--background)',
                borderRadius: '8px',
                padding: '12px',
                cursor: product.stock === 0 ? 'not-allowed' : 'pointer',
                minHeight: '128px',
                display: 'flex',
                flexDirection: 'column',
                justifyContent: 'space-between',
                opacity: product.stock === 0 ? 0.6 : 1,
            }}
        >
            <div>
                <div style={{ display: 'flex', justifyContent: 'space-between', gap: '4px', marginBottom: '8px', flexWrap: 'wrap' }}>
                    <PosBadge color="blue">{itemTypeLabel(product.item_type)}</PosBadge>
                    <PosBadge>{product.sku}</PosBadge>
                    <PosBadge color={product.stock === 0 ? 'red' : isLowStock ? 'amber' : 'green'}>
                        {product.stock === 0 ? 'Habis' : product.stock}
                    </PosBadge>
                </div>

                <div style={{ color: 'var(--foreground)', fontSize: '14px', fontWeight: 700 }}>
                    {product.name}
                </div>
                <div style={{ color: 'var(--muted-foreground)', fontSize: '12px', marginTop: '4px' }}>
                    {product.category?.name ?? 'Tanpa kategori'}
                </div>
            </div>

            <div style={{ color: 'var(--foreground)', fontWeight: 800, marginTop: '12px' }}>
                {formatCurrency(product.price)}
            </div>
        </button>
    );
}