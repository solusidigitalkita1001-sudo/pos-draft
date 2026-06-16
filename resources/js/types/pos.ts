// ─── Catalogue ────────────────────────────────────────────────────────────────

export interface PosCategory {
    id: number;
    name: string;
}

export interface PosItem {
    id: number;
    item_id: number;
    item_type: 'product' | 'package' | 'promotion';
    sku: string;
    name: string;
    price: string;
    stock: number;
    min_stock: number;
    category?: PosCategory | null;
}

// ─── Cart ─────────────────────────────────────────────────────────────────────

export interface CartItem {
    product: PosItem;
    quantity: number;
}

// ─── Voucher ──────────────────────────────────────────────────────────────────

export interface AppliedVoucher {
    id: number;
    code: string;
    name: string;
    type: 'fixed' | 'percent';
    value: string;
    discount_total: number;
}

// ─── Payment ──────────────────────────────────────────────────────────────────

export interface PaymentMethod {
    value: string;
    label: string;
}

// ─── Recent Transactions ──────────────────────────────────────────────────────

export interface RecentTransactionItem {
    id: number;
    product_name: string;
    product_sku: string | null;
    unit_price: string;
    quantity: number;
    discount_total: string;
    line_total: string;
}

export interface RecentTransaction {
    id: number;
    invoice_number: string;
    customer_name: string | null;
    status: 'pending' | 'completed' | 'void';
    payment_status: 'unpaid' | 'partial' | 'paid';
    payment_method: string | null;
    grand_total: string;
    paid_amount: string;
    change_amount: string;
    created_at: string;
    cashier?: { id: number; name: string } | null;
    items: RecentTransactionItem[];
}

// ─── Dashboard ────────────────────────────────────────────────────────────────

export interface DashboardStats {
    transactions_today?: number;
    revenue_today?: number;
    transactions_month?: number;
    revenue_month?: number;
    pending_transactions?: number;
    total_products?: number;
    low_stock_products?: number;
    total_members?: number;
}

export interface DashboardTopProduct {
    product_name: string;
    total_qty: number;
    total_revenue: number;
}