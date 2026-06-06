import { Link, usePage, router } from '@inertiajs/react';
import { Slot } from 'radix-ui';
import { createContext, useContext, useState, type ReactNode } from 'react';
import { cn } from '@/lib/utils';
import {
    ShoppingCart, Store, User, LogOut, ChevronsUpDown, Check,
    ChevronDown, ChevronRight, LayoutDashboard, Users, Package,
    Receipt, BarChart2, Settings, Menu,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';

// ─── Types ───────────────────────────────────────────────
interface NavChild {
    name: string;
    label: string;
    route: string;   // nama route Laravel, e.g. 'products.index'
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
// Karena starter kit pakai Wayfinder bukan Ziggy global,
// kita build URL manual sesuai pola /{team}/{resource}
function buildUrl(routeName: string, teamSlug: string): string {
    // Map route name → path segment
    const routeMap: Record<string, string> = {
        'dashboard':               `/${teamSlug}/dashboard`,
        'users.index':             `/${teamSlug}/users`,
        'invitations.index':       `/${teamSlug}/invitations`,
        'roles.index':             `/${teamSlug}/roles`,
        'permissions.index':       `/${teamSlug}/permissions`,
        'menus.index':             `/${teamSlug}/menus`,
        'products.index':          `/${teamSlug}/products`,
        'product-categories.index':`/${teamSlug}/product-categories`,
        'product-stocks.index':    `/${teamSlug}/product-stocks`,
        'product-packages.index':  `/${teamSlug}/product-packages`,
        'product-promotions.index': `/${teamSlug}/product-promotions`,
        'pos.index':               `/${teamSlug}/pos`,
        'transactions.index':      `/${teamSlug}/transactions`,
        'transactions.refunds':    `/${teamSlug}/transactions/refunds`,
        'transactions.returns':    `/${teamSlug}/transactions/returns`,
        'vouchers.index':          `/${teamSlug}/vouchers`,
        'reports.sales':           `/${teamSlug}/reports/sales`,
        'reports.stock':           `/${teamSlug}/reports/stock`,
        'reports.cashier':         `/${teamSlug}/reports/cashier`,
        'settings.system':         `/${teamSlug}/settings/system`,
        'settings.membership':     `/${teamSlug}/settings/membership`,
    };
    return routeMap[routeName] ?? `/${teamSlug}/dashboard`;
}

// ─── Icon Map ────────────────────────────────────────────
const iconMap: Record<string, LucideIcon> = {
    LayoutDashboard, Users, Package, ShoppingCart, Receipt,
    BarChart2, Settings, Store, User,
    // aliases dari MenuSeeder
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

// ─── Sidebar UI primitives ───────────────────────────────
interface SidebarContextValue {
    open: boolean;
    setOpen: React.Dispatch<React.SetStateAction<boolean>>;
}

const SidebarContext = createContext<SidebarContextValue | undefined>(undefined);

function useSidebarContext() {
    const context = useContext(SidebarContext);
    if (!context) {
        throw new Error('useSidebar must be used within SidebarProvider');
    }
    return context;
}

export function SidebarProvider({
    defaultOpen = true,
    children,
}: {
    defaultOpen?: boolean;
    children: ReactNode;
}) {
    const [open, setOpen] = useState(defaultOpen);

    return (
        <SidebarContext.Provider value={{ open, setOpen }}>
            <div
                className="sidebar-wrapper group flex min-h-screen"
                data-collapsible={open ? undefined : 'icon'}
            >
                {children}
            </div>
        </SidebarContext.Provider>
    );
}

export function useSidebar() {
    const context = useSidebarContext();
    return {
        ...context,
        state: context.open ? 'expanded' : 'collapsed',
    };
}

export function SidebarTrigger({
    className,
    ...props
}: React.ComponentProps<'button'>) {
    const { open, setOpen } = useSidebar();

    return (
        <button
            type="button"
            className={cn(
                'inline-flex h-9 w-9 items-center justify-center rounded-md text-sidebar-foreground hover:bg-sidebar-accent hover:text-sidebar-accent-foreground',
                className,
            )}
            aria-expanded={open}
            onClick={() => setOpen((value) => !value)}
            {...props}
        >
            <Menu size={18} />
        </button>
    );
}

export function SidebarInset({
    children,
    className,
    ...props
}: React.ComponentProps<'main'>) {
    return (
        <main className={cn('flex min-h-screen', className)} {...props}>
            {children}
        </main>
    );
}

export function SidebarGroup({
    className,
    ...props
}: React.ComponentProps<'div'>) {
    return <div data-slot="sidebar-group" className={cn(className)} {...props} />;
}

export function SidebarGroupContent({
    className,
    ...props
}: React.ComponentProps<'div'>) {
    return (
        <div data-slot="sidebar-group-content" className={cn(className)} {...props} />
    );
}

export function SidebarGroupLabel({
    className,
    ...props
}: React.ComponentProps<'div'>) {
    return (
        <div
            data-slot="sidebar-group-label"
            className={cn('px-2 py-2 text-xs uppercase tracking-wide text-muted-foreground', className)}
            {...props}
        />
    );
}

export function SidebarHeader({
    className,
    ...props
}: React.ComponentProps<'div'>) {
    return <div data-slot="sidebar-header" className={cn(className)} {...props} />;
}

export function SidebarContent({
    className,
    ...props
}: React.ComponentProps<'div'>) {
    return <div data-slot="sidebar-content" className={cn(className)} {...props} />;
}

export function SidebarFooter({
    className,
    ...props
}: React.ComponentProps<'div'>) {
    return <div data-slot="sidebar-footer" className={cn(className)} {...props} />;
}

export function SidebarMenu({
    className,
    ...props
}: React.ComponentProps<'div'>) {
    return <div data-slot="sidebar-menu" className={cn(className)} {...props} />;
}

export function SidebarMenuItem({
    className,
    ...props
}: React.ComponentProps<'div'>) {
    return <div data-slot="sidebar-menu-item" className={cn(className)} {...props} />;
}

export function SidebarMenuButton({
    className,
    asChild = false,
    isActive,
    size = 'default',
    tooltip,
    ...props
}: React.ComponentProps<'button'> & {
    asChild?: boolean;
    isActive?: boolean;
    size?: 'default' | 'lg';
    tooltip?: { children: string };
}) {
    const Component = asChild ? Slot.Root : 'button';

    return (
        <Component
            data-slot="sidebar-menu-button"
            data-state={isActive ? 'open' : undefined}
            data-size={size}
            className={cn(
                'inline-flex w-full items-center gap-2 rounded-md px-3 py-2 text-sm font-medium transition-colors',
                isActive
                    ? 'bg-sidebar-accent text-sidebar-accent-foreground'
                    : 'text-sidebar-foreground hover:bg-sidebar-accent hover:text-sidebar-accent-foreground',
                size === 'lg' ? 'h-11' : 'h-9',
                className,
            )}
            {...props}
        />
    );
}

// ─── NavLink ─────────────────────────────────────────────
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
            style={isActive
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

// ─── NavGroup ────────────────────────────────────────────
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
                    color: hasActiveChild
                        ? 'var(--sidebar-accent-foreground)'
                        : 'var(--sidebar-foreground)',
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

// ─── TeamSwitcher ────────────────────────────────────────
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
                    <div style={{
                        fontSize: '13px', fontWeight: 600,
                        color: 'var(--sidebar-foreground)',
                        overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap',
                    }}>
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
                        // Gunakan --popover (selalu solid) bukan --sidebar-background yang bisa transparan
                        backgroundColor: 'var(--popover)',
                        color: 'var(--popover-foreground)',
                        border: '1px solid var(--border)',
                        boxShadow: '0 4px 6px -1px rgba(0,0,0,0.1), 0 10px 15px -3px rgba(0,0,0,0.15)',
                        overflow: 'hidden',
                    }}>
                        {/* Header label */}
                        <div style={{
                            padding: '10px 12px 8px',
                            borderBottom: '1px solid var(--border)',
                        }}>
                            <p style={{
                                fontSize: '11px', fontWeight: 600,
                                color: 'var(--muted-foreground)',
                                textTransform: 'uppercase', letterSpacing: '0.06em',
                                margin: 0,
                            }}>Pilih Tim</p>
                        </div>
                        {/* List tim */}
                        <div style={{ padding: '4px' }}>
                        {teams.map(team => (
                            <button
                                key={team.id}
                                onClick={() => {
                                    setOpen(false);
                                    router.post(`/current-team/switch/${team.slug}`, {}, {
                                        onSuccess: () => {
                                            window.location.href = `/${team.slug}/dashboard`;
                                        },
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
                                {/* Avatar huruf pertama nama tim */}
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
                                    <div style={{
                                        fontWeight: 500, fontSize: '13px',
                                        color: 'var(--popover-foreground)',
                                        overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap',
                                    }}>{team.name}</div>
                                    <div style={{
                                        fontSize: '11px', color: 'var(--muted-foreground)',
                                        overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap',
                                    }}>
                                        {team.role_label ?? 'Member'}
                                    </div>
                                </div>
                                {/* Indicator tim aktif */}
                                {team.is_current && (
                                    <div style={{
                                        height: '20px', width: '20px', borderRadius: '50%',
                                        backgroundColor: 'var(--primary)',
                                        display: 'flex', alignItems: 'center', justifyContent: 'center',
                                        flexShrink: 0,
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

// ─── Main Sidebar Export ──────────────────────────────────
export default function Sidebar({
    className,
    style,
    onNavigate,
    collapsible,
    variant,
    children,
}: {
    className?: string;
    style?: React.CSSProperties;
    onNavigate?: () => void;
    collapsible?: 'icon';
    variant?: 'inset';
    children?: ReactNode;
}) {
    if (children) {
        return (
            <aside
                className={cn('flex min-h-full flex-col overflow-hidden', className)}
                data-collapsible={collapsible}
                data-variant={variant}
                style={style}
            >
                {children}
            </aside>
        );
    }
    const page = usePage();
    const { auth, navigation } = page.props as any;
    const navItems: NavItem[] = navigation ?? [];
    const currentTeam: CurrentTeam | null = auth?.user?.current_team ?? null;
    const teamSlug = currentTeam?.slug ?? '';
    // Gunakan page.url dari Inertia — konsisten di server & client, tidak ada hydration mismatch
    const currentPath = page.url.split('?')[0];
    const dashboardUrl = teamSlug ? `/${teamSlug}/dashboard` : '/';
    const logoutUrl = '/logout';

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
                <div style={{
                    display: 'flex', alignItems: 'center', gap: '10px', padding: '8px 12px',
                }}>
                    <div style={{
                        height: '28px', width: '28px', borderRadius: '50%',
                        backgroundColor: 'var(--sidebar-accent)',
                        display: 'flex', alignItems: 'center', justifyContent: 'center', flexShrink: 0,
                    }}>
                        <User size={14} style={{ color: 'var(--sidebar-foreground)' }} />
                    </div>
                    <div style={{ flex: 1, minWidth: 0 }}>
                        <div style={{
                            fontSize: '13px', fontWeight: 500,
                            color: 'var(--sidebar-foreground)',
                            overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap',
                        }}>
                            {auth?.user?.name}
                        </div>
                        <div style={{
                            fontSize: '11px', opacity: 0.5,
                            color: 'var(--sidebar-foreground)',
                            overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap',
                        }}>
                            {auth?.user?.email}
                        </div>
                    </div>
                    <Link
                        href={logoutUrl}
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
