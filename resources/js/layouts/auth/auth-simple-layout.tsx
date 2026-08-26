import { Link } from '@inertiajs/react';
import { BarChart3, CircleDollarSign, Package, ShoppingCart, Store } from 'lucide-react';
import ThemeToggle from '@/components/theme-toggle';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    return (
        <div className="min-h-svh bg-[#f5f7f4] p-4 text-[#17201a] transition-colors dark:bg-[#101714] dark:text-[#edf4ed] sm:p-6 lg:p-8">
            <div className="mx-auto grid min-h-[calc(100svh-2rem)] max-w-6xl overflow-hidden rounded-[2rem] border border-[#dbe4d9] bg-white shadow-[0_24px_80px_rgba(40,70,50,0.12)] dark:border-[#2d4235] dark:bg-[#17231d] dark:shadow-[0_24px_80px_rgba(0,0,0,0.28)] sm:min-h-[calc(100svh-3rem)] lg:grid-cols-[0.9fr_1.1fr]">
                <aside className="relative hidden overflow-hidden bg-[#174b3a] p-10 text-white lg:flex lg:flex-col lg:justify-between xl:p-14">
                    <div className="absolute -right-24 -top-24 h-80 w-80 rounded-full bg-[#d8f36a]/15 blur-3xl" />
                    <div className="relative">
                        <Link href={home()} className="flex items-center gap-3"><span className="flex h-10 w-10 items-center justify-center rounded-xl bg-[#d8f36a] text-[#174b3a]"><ShoppingCart className="h-5 w-5" strokeWidth={2.5} /></span><span className="font-[family-name:var(--font-display)] text-xl font-bold">Kasir<span className="text-[#f18a67]">Pro</span></span></Link>
                        <div className="mt-24 max-w-sm"><p className="text-xs font-bold uppercase tracking-[0.18em] text-[#b8d99e]">Sistem POS untuk toko modern</p><h2 className="mt-5 font-[family-name:var(--font-display)] text-5xl font-bold leading-[1.02] tracking-[-0.04em]">Kelola toko,<br /><span className="text-[#d8f36a]">lebih percaya diri.</span></h2><p className="mt-6 text-sm leading-6 text-[#c2d5c7]">Satu tempat untuk transaksi, stok, produk, dan laporan bisnis Anda.</p></div>
                    </div>
                    <div className="relative grid grid-cols-3 gap-3"><Feature icon={CircleDollarSign} label="Penjualan" /><Feature icon={Package} label="Stok produk" /><Feature icon={BarChart3} label="Laporan" /></div>
                </aside>
                <main className="flex flex-col justify-center px-6 py-8 sm:px-12 lg:px-16 xl:px-24">
                    <div className="mb-8 flex items-center justify-between lg:justify-end"><Link href={home()} className="flex items-center gap-2 lg:hidden"><span className="flex h-9 w-9 items-center justify-center rounded-lg bg-[#174b3a] text-[#d8f36a] dark:bg-[#d8f36a] dark:text-[#174b3a]"><ShoppingCart className="h-4 w-4" /></span><span className="font-[family-name:var(--font-display)] font-bold">Kasir<span className="text-[#e85b35]">Pro</span></span></Link><ThemeToggle compact className="bg-[#e7ece6] dark:bg-[#24342b]" /></div>
                    <div className="mx-auto w-full max-w-sm"><div className="mb-8 space-y-2"><h1 className="font-[family-name:var(--font-display)] text-3xl font-bold tracking-tight text-[#174b3a] dark:text-[#dcebdc]">{title}</h1><p className="text-sm leading-6 text-[#718077] dark:text-[#9bad9f]">{description}</p></div>{children}</div>
                    <p className="mt-10 text-center text-xs text-[#8a978e] dark:text-[#819388]"><Store className="mr-1 inline h-3.5 w-3.5" /> Operasional toko lebih rapi bersama KasirPro</p>
                </main>
            </div>
        </div>
    );
}

function Feature({ icon: Icon, label }: { icon: typeof CircleDollarSign; label: string }) {
    return <div className="rounded-xl border border-white/15 bg-white/10 p-3"><Icon className="mb-5 h-4 w-4 text-[#d8f36a]" /><p className="text-xs font-semibold text-[#d5e3d5]">{label}</p></div>;
}
