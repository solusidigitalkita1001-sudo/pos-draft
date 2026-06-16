import { useMemo, useState } from 'react';
import type { CartItem, PosItem } from '@/types/pos';

export function cartItemKey(item: PosItem): string {
    return `${item.item_type}:${item.item_id}`;
}

export function useCart() {
    const [cart, setCart] = useState<CartItem[]>([]);

    const subtotal = useMemo(
        () => cart.reduce((sum, i) => sum + parseFloat(i.product.price) * i.quantity, 0),
        [cart],
    );

    function addToCart(product: PosItem) {
        setCart((prev) => {
            const key = cartItemKey(product);
            const existing = prev.find((i) => cartItemKey(i.product) === key);

            if (existing) {
                return prev.map((i) =>
                    cartItemKey(i.product) === key
                        ? { ...i, quantity: Math.min(i.quantity + 1, product.stock) }
                        : i,
                );
            }

            return [...prev, { product, quantity: 1 }];
        });
    }

    function setQuantity(product: PosItem, quantity: number) {
        const key = cartItemKey(product);
        setCart((prev) =>
            prev
                .map((i) =>
                    cartItemKey(i.product) === key
                        ? { ...i, quantity: Math.max(1, Math.min(quantity, i.product.stock)) }
                        : i,
                )
                .filter((i) => i.quantity > 0),
        );
    }

    function removeFromCart(product: PosItem) {
        const key = cartItemKey(product);
        setCart((prev) => prev.filter((i) => cartItemKey(i.product) !== key));
    }

    function clearCart() {
        setCart([]);
    }

    return { cart, subtotal, addToCart, setQuantity, removeFromCart, clearCart };
}