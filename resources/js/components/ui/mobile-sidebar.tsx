/**
 * resources/js/components/ui/mobile-sidebar.tsx
 *
 * Komponen sidebar khusus untuk mobile sheet dan standalone use.
 * Dipisah dari ui/sidebar.tsx agar tidak konflik dengan Shadcn Sidebar exports.
 */

import { Link, usePage, router } from '@inertiajs/react';
import { useState } from 'react';
import { cn } from '@/lib/utils';
import {
    ShoppingCart, Store, User, LogOut, ChevronsUpDown, Check,
    ChevronDown, ChevronRight, LayoutDashboard, Users, Package,
    Receipt, BarChart2, Settings,
} from 'lucide-react';

// ─── Types ───────────────────────────────────────────────
interface NavChild {
    name: string;
    label: string;
    route: string;
    icon?: string;
}

interface NavItem {
    name: string;
    label: string;
    route: string | null;
    icon: string;
    module: string | null;
    children: NavChild[];
}

interface CurrentTeam {
    id: number;
    name: string;
    slug: string;
    role_label: string | null;
    is_owner: boolean;
}

// ─── Helper: build URL dari route name + team slug ───────
function buildUrl(routeName: string, teamSlug: string): string {
    const routeMap: Record<string, string> = {
        'dashboard':                `/${teamSlug}/dashboard`,
        'users.index':              `/${teamSlug}/users`,
        'invitations.index':        `/${teamSlug}/invitations`,
        'roles.index':              `/${teamSlug}/roles`,
        'permissions.index':        `/${teamSlug}/permissions`,
        'menus.index':              `/${teamSlug}/menus`,
        'products.index':           `/${teamSlug}/products`,
        'product-categories.index': `/${teamSlug}/product-categories`,
        'product-stocks.index':     `/${teamSlug}/product-stocks`,
        'product-packages.index':   `/${teamSlug}/product-packages`,
        'product-promotions.index': `/${teamSlug}/product-promotions`,
        'pos.index':                `/${teamSlug}/pos`,
        'transactions.index':       `/${teamSlug}/transactions`,
        'transactions.refunds':     `/${teamSlug}/transactions/refunds`,
        'transactions.returns':     `/${teamSlug}/transactions/returns`,
        'vouchers.index':           `/${teamSlug}/vouchers`,
        'reports.sales':            `/${teamSlug}/reports/sales`,
        'reports.stock':            `/${teamSlug}/reports/stock`,
        'reports.cashier':          `/${teamSlug}/reports/cashier`,
        'settings.system':          `/${teamSlug}/settings/system`,
        'settings.membership':      `/${teamSlug}/settings/membership`,
    };
    return routeMap[routeName] ?? `/${teamSlug}/dashboard`;
}

// ─── Icon Map ─────────────────────────────────────────────
const iconMap: Record<string, React.ElementType> = {
    LayoutDashboard, Users, Package, ShoppingCart, Receipt,
    BarChart2, Settings, Store, User,
    Box: Package,
    Tag: Package,
    Warehouse: Package,
    ListOrdered: Receipt,
    RotateCcw: Receipt,
    PackageOpen: Package,
    Ticket: Receipt,
    TrendingUp: BarChart2,
    Archive: Package,
    UserCheck: Users,
    Server: Settings,
    CreditCard: Settings,
    Menu: Settings,
    Shield: Settings,
    Mail: Users,
    UserList: Users,
};

// ─── NavLink ──────────────────────────────────────────────
function NavLink({
    item,
    teamSlug,
    currentPath,
    onNavigate,
}: {
    item: NavChild;
    teamSlug: string;
    currentPath: string;
    onNavigate?: () => void;
}) {
    const Icon = iconMap[item.icon ?? ''];
    const href = buildUrl(item.route, teamSlug);
    const isActive = currentPath === href || currentPath.startsWith(href + '/');

    const base: React.CSSProperties = {
        display: 'flex', alignItems: 'center', gap: '10px',
        borderRadius: '8px', padding: '7px 12px',
        fontSize: '14px', textDecoration: 'none',
        transition: 'all 0.15s', cursor: 'pointer',
    };

    return (
        <Link
            href={href}
            onClick={onNavigate}
            style={
                isActive
                    ? { ...base, backgroundColor: 'var(--sidebar-accent)', color: 'var(--sidebar-accent-foreground)', fontWeight: 500 }
                    : { ...base, color: 'var(--sidebar-foreground)', opacity: 0.8 }
            }
            onMouseEnter={e => {
                if (!isActive) {
                    (e.currentTarget as HTMLElement).style.backgroundColor = 'var(--sidebar-accent)';
                    (e.currentTarget as HTMLElement).style.opacity = '1';
                }
            }}
            onMouseLeave={e => {
                if (!isActive) {
                    (e.currentTarget as HTMLElement).style.backgroundColor = 'transparent';
                    (e.currentTarget as HTMLElement).style.opacity = '0.8';
                }
            }}
        >
            {Icon && <Icon size={15} style={{ flexShrink: 0 }} />}
            <span>{item.label}</span>
        </Link>
    );
}

// ─── NavGroup ─────────────────────────────────────────────
function NavGroup({
    item,
    teamSlug,
    currentPath,
    onNavigate,
}: {
    item: NavItem;
    teamSlug: string;
    currentPath: string;
    onNavigate?: () => void;
}) {
    const Icon = iconMap[item.icon];
    const href = item.route ? buildUrl(item.route, teamSlug) : null;
    const hasActiveChild = item.children.some(c => {
        const childHref = buildUrl(c.route, teamSlug);
        return currentPath === childHref || currentPath.startsWith(childHref + '/');
    });
    const isDirectActive = href ? currentPath === href : false;
    const [open, setOpen] = useState(hasActiveChild || isDirectActive);

    // Leaf node — tidak punya children
    if (item.children.length === 0 && href) {
        return (
            <NavLink
                item={{ name: item.name, label: item.label, route: item.route!, icon: item.icon }}
                teamSlug={teamSlug}
                currentPath={currentPath}
                onNavigate={onNavigate}
            />
        );
    }

    return (
        <div>
            <button
                onClick={() => setOpen(o => !o)}
                style={{
                    width: '100%', display: 'flex', alignItems: 'center',
                    justifyContent: 'space-between', gap: '10px',
                    borderRadius: '8px', padding: '7px 12px', fontSize: '14px',
                    color: hasActiveChild ? 'var(--sidebar-accent-foreground)' : 'var(--sidebar-foreground)',
                    opacity: hasActiveChild ? 1 : 0.8,
                    fontWeight: hasActiveChild ? 500 : 400,
                    background: 'none', border: 'none', cursor: 'pointer',
                    transition: 'all 0.15s',
                }}
                onMouseEnter={e => {
                    e.currentTarget.style.backgroundColor = 'var(--sidebar-accent)';
                    e.currentTarget.style.opacity = '1';
                }}
                onMouseLeave={e => {
                    e.currentTarget.style.backgroundColor = 'transparent';
                    e.currentTarget.style.opacity = hasActiveChild ? '1' : '0.8';
                }}
            >
                <span style={{ display: 'flex', alignItems: 'center', gap: '10px' }}>
                    {Icon && <Icon size={15} style={{ flexShrink: 0 }} />}
                    {item.label}
                </span>
                {open
                    ? <ChevronDown size={13} style={{ opacity: 0.5 }} />
                    : <ChevronRight size={13} style={{ opacity: 0.5 }} />
                }
            </button>

            {open && (
                <div style={{
                    marginTop: '2px', marginLeft: '16px',
                    paddingLeft: '12px',
                    borderLeft: '1px solid var(--sidebar-border)',
                    display: 'flex', flexDirection: 'column', gap: '2px',
                }}>
                    {item.children.map(child => (
                        <NavLink
                            key={child.name}
                            item={child}
                            teamSlug={teamSlug}
                            currentPath={currentPath}
                            onNavigate={onNavigate}
                        />
                    ))}
                </div>
            )}
        </div>
    );
}

// ─── TeamSwitcherWidget ───────────────────────────────────
function TeamSwitcherWidget({ current, teams }: {
    current: CurrentTeam | null;
    teams: Array<{ id: number; name: string; slug: string; role_label: string | null; is_current: boolean }>;
}) {
    const [open, setOpen] = useState(false);
    if (!teams || teams.length <= 1) return null;

    return (
        <div style={{ position: 'relative' }}>
            <button
                onClick={() => setOpen(o => !o)}
                style={{
                    width: '100%', display: 'flex', alignItems: 'center', gap: '10px',
                    borderRadius: '8px', padding: '7px 12px',
                    background: 'none', border: 'none', cursor: 'pointer',
                    transition: 'background-color 0.15s', textAlign: 'left',
                }}
                onMouseEnter={e => (e.currentTarget.style.backgroundColor = 'var(--sidebar-accent)')}
                onMouseLeave={e => (e.currentTarget.style.backgroundColor = 'transparent')}
            >
                <div style={{
                    height: '28px', width: '28px', borderRadius: '8px',
                    backgroundColor: 'var(--sidebar-primary)', opacity: 0.15,
                    display: 'flex', alignItems: 'center', justifyContent: 'center', flexShrink: 0,
                    position: 'relative',
                }}>
                    <Store size={14} style={{ color: 'var(--sidebar-primary-foreground)', position: 'absolute' }} />
                </div>
                <div style={{ flex: 1, minWidth: 0 }}>
                    <div style={{ fontSize: '13px', fontWeight: 600, color: 'var(--sidebar-foreground)', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                        {current?.name ?? 'Pilih Tim'}
                    </div>
                    <div style={{ fontSize: '11px', color: 'var(--sidebar-foreground)', opacity: 0.6 }}>
                        {current?.role_label ?? ''}
                    </div>
                </div>
                <ChevronsUpDown size={13} style={{ color: 'var(--sidebar-foreground)', opacity: 0.5, flexShrink: 0 }} />
            </button>

            {open && (
                <>
                    <div style={{ position: 'fixed', inset: 0, zIndex: 10 }} onClick={() => setOpen(false)} />
                    <div style={{
                        position: 'absolute', left: 0, top: '100%', marginTop: '6px',
                        width: '236px', zIndex: 50, borderRadius: '12px',
                        backgroundColor: 'var(--popover)',
                        color: 'var(--popover-foreground)',
                        border: '1px solid var(--border)',
                        boxShadow: '0 4px 6px -1px rgba(0,0,0,0.1), 0 10px 15px -3px rgba(0,0,0,0.15)',
                        overflow: 'hidden',
                    }}>
                        <div style={{ padding: '10px 12px 8px', borderBottom: '1px solid var(--border)' }}>
                            <p style={{ fontSize: '11px', fontWeight: 600, color: 'var(--muted-foreground)', textTransform: 'uppercase', letterSpacing: '0.06em', margin: 0 }}>
                                Pilih Tim
                            </p>
                        </div>
                        <div style={{ padding: '4px' }}>
                            {teams.map(team => (
                                <button
                                    key={team.id}
                                    onClick={() => {
                                        setOpen(false);
                                        router.post(`/current-team/switch/${team.slug}`, {}, {
                                            onSuccess: () => { window.location.href = `/${team.slug}/dashboard`; },
                                        });
                                    }}
                                    style={{
                                        width: '100%', display: 'flex', alignItems: 'center', gap: '10px',
                                        padding: '7px 10px', fontSize: '13px', borderRadius: '8px',
                                        backgroundColor: team.is_current ? 'var(--accent)' : 'transparent',
                                        border: 'none', cursor: 'pointer',
                                        transition: 'background-color 0.15s', textAlign: 'left',
                                    }}
                                    onMouseEnter={e => (e.currentTarget.style.backgroundColor = 'var(--accent)')}
                                    onMouseLeave={e => (e.currentTarget.style.backgroundColor = team.is_current ? 'var(--accent)' : 'transparent')}
                                >
                                    <div style={{
                                        height: '32px', width: '32px', borderRadius: '8px',
                                        backgroundColor: 'var(--primary)',
                                        display: 'flex', alignItems: 'center', justifyContent: 'center',
                                        flexShrink: 0, fontSize: '14px', fontWeight: 700,
                                        color: 'var(--primary-foreground)',
                                    }}>
                                        {team.name.charAt(0).toUpperCase()}
                                    </div>
                                    <div style={{ flex: 1, minWidth: 0 }}>
                                        <div style={{ fontWeight: 500, fontSize: '13px', color: 'var(--popover-foreground)', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                                            {team.name}
                                        </div>
                                        <div style={{ fontSize: '11px', color: 'var(--muted-foreground)', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                                            {team.role_label ?? 'Member'}
                                        </div>
                                    </div>
                                    {team.is_current && (
                                        <div style={{
                                            height: '20px', width: '20px', borderRadius: '50%',
                                            backgroundColor: 'var(--primary)',
                                            display: 'flex', alignItems: 'center', justifyContent: 'center', flexShrink: 0,
                                        }}>
                                            <Check size={11} style={{ color: 'var(--primary-foreground)' }} />
                                        </div>
                                    )}
                                </button>
                            ))}
                        </div>
                    </div>
                </>
            )}
        </div>
    );
}

// ─── MobileSidebar (default export) ──────────────────────
export default function MobileSidebar({
    className,
    style,
    onNavigate,
}: {
    className?: string;
    style?: React.CSSProperties;
    onNavigate?: () => void;
}) {
    const page = usePage();
    const { auth, navigation } = page.props as any;
    const navItems: NavItem[] = navigation ?? [];
    const currentTeam: CurrentTeam | null = auth?.user?.current_team ?? null;
    const teamSlug = currentTeam?.slug ?? '';
    const currentPath = page.url.split('?')[0];
    const dashboardUrl = teamSlug ? `/${teamSlug}/dashboard` : '/';

    return (
        <aside
            className={cn('flex h-full w-64 shrink-0 flex-col', className)}
            style={{
                borderRight: '1px solid var(--sidebar-border)',
                backgroundColor: 'var(--sidebar-background)',
                ...style,
            }}
        >
            {/* Logo */}
            <div style={{ padding: '16px', borderBottom: '1px solid var(--sidebar-border)' }}>
                <Link
                    href={dashboardUrl}
                    onClick={onNavigate}
                    style={{ display: 'flex', alignItems: 'center', gap: '10px', textDecoration: 'none' }}
                >
                    <div style={{
                        height: '32px', width: '32px', borderRadius: '8px',
                        backgroundColor: 'var(--sidebar-primary)',
                        display: 'flex', alignItems: 'center', justifyContent: 'center',
                    }}>
                        <ShoppingCart size={16} style={{ color: 'var(--sidebar-primary-foreground)' }} />
                    </div>
                    <div>
                        <div style={{ fontSize: '14px', fontWeight: 600, color: 'var(--sidebar-foreground)' }}>
                            POS System
                        </div>
                        <div style={{ fontSize: '11px', color: 'var(--sidebar-foreground)', opacity: 0.5 }}>
                            Point of Sale
                        </div>
                    </div>
                </Link>
            </div>

            {/* Team Switcher */}
            <div style={{ padding: '8px' }}>
                <TeamSwitcherWidget
                    current={currentTeam}
                    teams={auth?.user?.teams ?? []}
                />
            </div>

            {/* Navigation */}
            <nav style={{
                flex: 1, overflowY: 'auto', padding: '8px',
                display: 'flex', flexDirection: 'column', gap: '2px',
            }}>
                {navItems.length === 0 && (
                    <div style={{ padding: '12px', fontSize: '13px', color: 'var(--sidebar-foreground)', opacity: 0.4, textAlign: 'center' }}>
                        Tidak ada menu tersedia
                    </div>
                )}
                {navItems.map(item => (
                    <NavGroup
                        key={item.name}
                        item={item}
                        teamSlug={teamSlug}
                        currentPath={currentPath}
                        onNavigate={onNavigate}
                    />
                ))}
            </nav>

            {/* User Footer */}
            <div style={{ padding: '8px', borderTop: '1px solid var(--sidebar-border)' }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: '10px', padding: '8px 12px' }}>
                    <div style={{
                        height: '28px', width: '28px', borderRadius: '50%',
                        backgroundColor: 'var(--sidebar-accent)',
                        display: 'flex', alignItems: 'center', justifyContent: 'center', flexShrink: 0,
                    }}>
                        <User size={14} style={{ color: 'var(--sidebar-foreground)' }} />
                    </div>
                    <div style={{ flex: 1, minWidth: 0 }}>
                        <div style={{ fontSize: '13px', fontWeight: 500, color: 'var(--sidebar-foreground)', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                            {auth?.user?.name}
                        </div>
                        <div style={{ fontSize: '11px', opacity: 0.5, color: 'var(--sidebar-foreground)', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                            {auth?.user?.email}
                        </div>
                    </div>
                    <Link
                        href="/logout"
                        method="post"
                        as="button"
                        onClick={onNavigate}
                        style={{
                            color: 'var(--sidebar-foreground)', opacity: 0.4,
                            background: 'none', border: 'none', cursor: 'pointer',
                            padding: '4px', borderRadius: '6px', transition: 'all 0.15s',
                            display: 'flex', alignItems: 'center', justifyContent: 'center',
                        }}
                        onMouseEnter={e => {
                            (e.currentTarget as HTMLElement).style.opacity = '1';
                            (e.currentTarget as HTMLElement).style.color = '#ef4444';
                        }}
                        onMouseLeave={e => {
                            (e.currentTarget as HTMLElement).style.opacity = '0.4';
                            (e.currentTarget as HTMLElement).style.color = 'var(--sidebar-foreground)';
                        }}
                        title="Logout"
                    >
                        <LogOut size={14} />
                    </Link>
                </div>
            </div>
        </aside>
    );
}