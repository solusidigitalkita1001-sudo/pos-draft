import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowUpRight, BarChart3, ChevronRight, CircleDollarSign, Package, Plus, Receipt, Search, ShoppingCart, Store, Users } from 'lucide-react';
import ThemeToggle from '@/components/theme-toggle';
import { dashboard, login, register } from '@/routes';

export default function Welcome({ canRegister = true }: { canRegister?: boolean }) {
    const { auth, currentTeam } = usePage().props;
    const dashboardUrl = currentTeam ? dashboard(currentTeam.slug) : '/';
    const teamName = currentTeam?.name ?? 'Toko Anda';

    return (
        <>
            <Head title="KasirPro">
                <link rel="preconnect" href="https://fonts.bunny.net" />
                <link href="https://fonts.bunny.net/css?family=dm-sans:400,500,600,700|space-grotesk:500,600,700" rel="stylesheet" />
            </Head>
            <div className="min-h-screen overflow-hidden bg-[#f5f7f4] font-[family-name:var(--font-body)] text-[#17201a] transition-colors dark:bg-[#101714] dark:text-[#edf4ed]">
                <header className="relative z-10 mx-auto flex w-full max-w-7xl items-center justify-between px-6 py-5 lg:px-10">
                    <Link href={dashboardUrl} className="flex items-center gap-3"><span className="flex h-10 w-10 items-center justify-center rounded-xl bg-[#174b3a] text-[#d8f36a] shadow-lg shadow-[#174b3a]/15 dark:bg-[#d8f36a] dark:text-[#174b3a]"><ShoppingCart className="h-5 w-5" strokeWidth={2.5} /></span><span className="font-[family-name:var(--font-display)] text-xl font-bold tracking-tight">Kasir<span className="text-[#e85b35]">Pro</span></span></Link>
                    <nav className="flex items-center gap-2 text-sm font-semibold sm:gap-5">
                        <span className="hidden items-center gap-2 text-[#6c776f] dark:text-[#91a297] sm:flex"><span className="h-2 w-2 rounded-full bg-[#72b66a]" /> Sistem siap digunakan</span>
                        <ThemeToggle compact className="bg-[#e7ece6] dark:bg-[#24342b]" />
                        {auth.user ? <Link href={dashboardUrl} className="rounded-lg bg-[#174b3a] px-4 py-2.5 text-white transition hover:bg-[#0f382b] dark:bg-[#d8f36a] dark:text-[#173226] dark:hover:bg-[#c5e34d]">Buka dashboard</Link> : <><Link href={login()} className="px-2 py-2.5 text-[#445149] transition hover:text-[#174b3a] dark:text-[#c4d0c7] dark:hover:text-[#d8f36a]">Masuk</Link>{canRegister && <Link href={register()} className="rounded-lg border border-[#cbd4cb] bg-white px-4 py-2.5 transition hover:border-[#174b3a] dark:border-[#3b5144] dark:bg-[#19261f] dark:hover:border-[#d8f36a]">Daftar</Link>}</>}
                    </nav>
                </header>
                <main className="relative mx-auto grid w-full max-w-7xl gap-14 px-6 pb-14 pt-10 lg:grid-cols-[0.85fr_1.15fr] lg:items-center lg:px-10 lg:pb-20 lg:pt-20">
                    <div className="relative z-10 max-w-xl">
                        <div className="mb-6 inline-flex items-center gap-2 rounded-full border border-[#c9d9c7] bg-[#e8f2e5] px-3 py-1.5 text-xs font-bold uppercase tracking-[0.14em] text-[#397047] dark:border-[#3a6049] dark:bg-[#193126] dark:text-[#b8dc9e]"><Store className="h-3.5 w-3.5" /> Operasional toko lebih rapi</div>
                        <h1 className="font-[family-name:var(--font-display)] text-5xl font-bold leading-[0.98] tracking-[-0.045em] text-[#174b3a] sm:text-6xl lg:text-7xl dark:text-[#dcebdc]">Semua transaksi,<br /><span className="text-[#e85b35]">satu kendali.</span></h1>
                        <p className="mt-7 max-w-md text-base leading-7 text-[#657168] dark:text-[#a6b5aa]">Kelola penjualan, stok, produk, voucher, dan laporan toko dari satu sistem POS yang dibuat untuk bergerak cepat.</p>
                        <div className="mt-9 flex flex-wrap items-center gap-3"><Link href={auth.user ? dashboardUrl : register()} className="group inline-flex items-center gap-2 rounded-xl bg-[#e85b35] px-5 py-3.5 font-bold text-white shadow-xl shadow-[#e85b35]/20 transition hover:-translate-y-0.5 hover:bg-[#d94e2b]">Mulai kelola toko <ArrowUpRight className="h-4 w-4 transition group-hover:translate-x-0.5 group-hover:-translate-y-0.5" /></Link><Link href={login()} className="inline-flex items-center gap-1 rounded-xl px-4 py-3.5 font-bold text-[#174b3a] hover:bg-white dark:text-[#d8f36a] dark:hover:bg-[#1b2a22]">Sudah punya akun <ChevronRight className="h-4 w-4" /></Link></div>
                        <div className="mt-12 flex items-center gap-3 text-sm text-[#78837b] dark:text-[#91a297]"><div className="flex -space-x-2"><span className="flex h-8 w-8 items-center justify-center rounded-full border-2 border-[#f5f7f4] bg-[#e4a46c] text-xs font-bold text-white dark:border-[#101714]">AR</span><span className="flex h-8 w-8 items-center justify-center rounded-full border-2 border-[#f5f7f4] bg-[#608b75] text-xs font-bold text-white dark:border-[#101714]">NS</span><span className="flex h-8 w-8 items-center justify-center rounded-full border-2 border-[#f5f7f4] bg-[#e85b35] text-xs font-bold text-white dark:border-[#101714]">+</span></div><span>Dirancang untuk tim toko modern</span></div>
                    </div>
                    <div className="relative lg:pl-4"><div className="absolute -right-20 -top-20 h-72 w-72 rounded-full bg-[#d8f36a]/50 blur-3xl dark:bg-[#668b3a]/20" /><div className="relative rounded-[2rem] border border-[#d7dfd5] bg-white p-3 shadow-[0_30px_80px_rgba(40,70,50,0.14)] dark:border-[#2e4337] dark:bg-[#17231d] dark:shadow-[0_30px_80px_rgba(0,0,0,0.28)] sm:p-5"><div className="rounded-[1.35rem] bg-[#f7f9f6] p-4 dark:bg-[#1d2b23] sm:p-6">
                        <div className="mb-6 flex items-center justify-between"><div><p className="text-xs font-semibold uppercase tracking-[0.14em] text-[#859087] dark:text-[#94a79a]">Ringkasan hari ini</p><h2 className="mt-1 font-[family-name:var(--font-display)] text-xl font-bold text-[#174b3a] dark:text-[#dcebdc]">{teamName}</h2></div><div className="rounded-lg bg-white p-2.5 text-[#174b3a] shadow-sm dark:bg-[#26392e] dark:text-[#d8f36a]"><BarChart3 className="h-5 w-5" /></div></div>
                        <div className="grid grid-cols-2 gap-3 sm:grid-cols-4"><Metric icon={CircleDollarSign} label="Penjualan" value="Rp 8,42 jt" accent="orange" /><Metric icon={Receipt} label="Transaksi" value="128" accent="green" /><Metric icon={Package} label="Produk" value="246" accent="lime" /><Metric icon={Users} label="Pelanggan" value="84" accent="blue" /></div>
                        <div className="mt-5 grid gap-4 sm:grid-cols-[1.25fr_0.75fr]"><div className="rounded-xl border border-[#e4e9e3] bg-white p-4 dark:border-[#304238] dark:bg-[#233329]"><div className="mb-5 flex items-center justify-between"><span className="text-sm font-bold text-[#2c3930] dark:text-[#d6e5d8]">Penjualan minggu ini</span><span className="text-xs font-bold text-[#65a15a]">+18,4%</span></div><div className="flex h-32 items-end gap-2 sm:gap-3">{[40, 58, 48, 76, 64, 88, 70].map((height, index) => <div key={index} className="flex flex-1 flex-col items-center gap-2"><div className={`w-full rounded-t-md ${index === 5 ? 'bg-[#e85b35]' : 'bg-[#b8d8ad] dark:bg-[#668d67]'}`} style={{ height: `${height}%` }} /><span className="text-[10px] text-[#9aa49c] dark:text-[#91a297]">{['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'][index]}</span></div>)}</div></div><div className="rounded-xl bg-[#174b3a] p-4 text-white dark:bg-[#0e382a]"><div className="flex items-center justify-between"><span className="text-sm font-bold">Transaksi terbaru</span><Receipt className="h-4 w-4 text-[#d8f36a]" /></div><div className="mt-5 space-y-4"><Transaction name="Kopi Susu Gula Aren" price="Rp 32.000" /><Transaction name="Paket Hemat 1" price="Rp 58.500" /><Transaction name="Roti Bakar Coklat" price="Rp 24.000" /></div><div className="mt-5 flex items-center gap-1 text-xs font-bold text-[#d8f36a]">Lihat semua transaksi <ArrowUpRight className="h-3 w-3" /></div></div></div>
                        <div className="mt-4 flex items-center justify-between rounded-xl border border-dashed border-[#cbd8c9] bg-[#eff7eb] px-4 py-3 dark:border-[#44624d] dark:bg-[#243a2b]"><div className="flex items-center gap-3"><div className="rounded-lg bg-white p-2 text-[#65a15a] dark:bg-[#2e4935]"><Plus className="h-4 w-4" /></div><span className="text-xs font-semibold text-[#527057] dark:text-[#b5d1b3]">Pintasan kasir siap dipakai</span></div><Search className="h-4 w-4 text-[#82a087] dark:text-[#9bbda0]" /></div>
                    </div></div></div>
                </main>
                <footer className="mx-auto flex w-full max-w-7xl items-center justify-between border-t border-[#dce3da] px-6 py-5 text-xs text-[#879188] dark:border-[#293b30] dark:text-[#829589] lg:px-10"><span>© 2026 KasirPro</span><span>Penjualan lebih sederhana, bisnis lebih terarah.</span></footer>
            </div>
        </>
    );
}

function Metric({ icon: Icon, label, value, accent }: { icon: typeof CircleDollarSign; label: string; value: string; accent: 'orange' | 'green' | 'lime' | 'blue' }) {
    const colors = { orange: 'bg-[#fff0e9] text-[#e85b35]', green: 'bg-[#e6f2e5] text-[#4f9255]', lime: 'bg-[#f0f6d8] text-[#789632]', blue: 'bg-[#e5f0f3] text-[#4d8190]' };
    return <div className="rounded-xl border border-[#e4e9e3] bg-white p-3 dark:border-[#304238] dark:bg-[#233329]"><div className={`mb-3 flex h-7 w-7 items-center justify-center rounded-lg ${colors[accent]}`}><Icon className="h-3.5 w-3.5" /></div><p className="text-[10px] font-semibold text-[#8a958c] dark:text-[#9aad9e]">{label}</p><p className="mt-0.5 text-sm font-bold text-[#26362b] dark:text-[#d6e5d8]">{value}</p></div>;
}

function Transaction({ name, price }: { name: string; price: string }) {
    return <div className="flex items-center gap-2"><span className="h-2 w-2 rounded-full bg-[#e85b35]" /><div className="min-w-0 flex-1"><p className="truncate text-xs font-semibold text-[#e6f0e6]">{name}</p><p className="text-[10px] text-[#8eb09a]">Lunas</p></div><span className="text-xs font-bold text-[#d8f36a]">{price}</span></div>;
}