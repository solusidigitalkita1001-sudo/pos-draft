import { Link, usePage } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import {
    LayoutGrid,
    Users,
    Package,
    ShoppingCart,
    Receipt,
    BarChart2,
    Settings,
    Store,
    Shield,
    Tag,
    Warehouse,
    ListOrdered,
    Ticket,
    TrendingUp,
    Archive,
    UserCheck,
    Server,
    CreditCard,
    Menu,
    Mail,
    Box,
    RotateCcw,
    PackageOpen,
    BookOpen,
    FolderGit2,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { TeamSwitcher } from '@/components/team-switcher';
import Sidebar, {
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import type { NavItem } from '@/types';

// Map icon name (string dari backend) ke Lucide component
const iconMap: Record<string, LucideIcon> = {
    LayoutDashboard: LayoutGrid,
    LayoutGrid,
    Users,
    Package,
    ShoppingCart,
    Receipt,
    BarChart2,
    Settings,
    Store,
    Shield,
    Tag,
    Warehouse,
    ListOrdered,
    Ticket,
    TrendingUp,
    Archive,
    UserCheck,
    Server,
    CreditCard,
    Menu,
    Mail,
    Box,
    RotateCcw,
    PackageOpen,
    // Fallback
    default: LayoutGrid,
};

// Build URL dari route name + team slug
// Tidak menggunakan Ziggy karena starter kit ini pakai Wayfinder
function buildUrl(routeName: string, teamSlug: string): string {
    const routeMap: Record<string, string> = {
        dashboard: `/${teamSlug}/dashboard`,
        'users.index': `/${teamSlug}/users`,
        'invitations.index': `/${teamSlug}/invitations`,
        'roles.index': `/${teamSlug}/roles`,
        'permissions.index': `/${teamSlug}/permissions`,
        'menus.index': `/${teamSlug}/menus`,
        'products.index': `/${teamSlug}/products`,
        'product-categories.index': `/${teamSlug}/product-categories`,
        'product-stocks.index': `/${teamSlug}/product-stocks`,
        'pos.index': `/${teamSlug}/pos`,
        'transactions.index': `/${teamSlug}/transactions`,
        'transactions.refunds': `/${teamSlug}/transactions/refunds`,
        'transactions.returns': `/${teamSlug}/transactions/returns`,
        'vouchers.index': `/${teamSlug}/vouchers`,
        'reports.sales': `/${teamSlug}/reports/sales`,
        'reports.stock': `/${teamSlug}/reports/stock`,
        'reports.cashier': `/${teamSlug}/reports/cashier`,
        'settings.system': `/${teamSlug}/settings/system`,
        'settings.membership': `/${teamSlug}/settings/membership`,
    };
    return routeMap[routeName] ?? `/${teamSlug}/dashboard`;
}

interface NavItemFromServer {
    name: string;
    label: string;
    route: string | null;
    icon: string;
    module: string | null;
    children: Array<{
        name: string;
        label: string;
        route: string;
        icon?: string;
    }>;
}

export function AppSidebar() {
    const { auth, navigation } = usePage().props as any;
    const currentTeam = auth?.user?.current_team;
    const teamSlug: string = currentTeam?.slug ?? '';
    const navItemsFromServer: NavItemFromServer[] = navigation ?? [];
    const dashboardUrl = teamSlug ? `/${teamSlug}/dashboard` : '/';

    // Convert server nav items ke format NavItem yang dipakai NavMain
    const mainNavItems: NavItem[] = navItemsFromServer.map((item) => {
        const Icon = iconMap[item.icon] ?? iconMap.default;
        const href = item.route ? buildUrl(item.route, teamSlug) : '#';

        return {
            title: item.label,
            href,
            icon: Icon,
            // Jika punya children, tambahkan sebagai items
            items:
                item.children.length > 0
                    ? item.children.map((child) => ({
                          title: child.label,
                          href: buildUrl(child.route, teamSlug),
                      }))
                    : undefined,
        };
    });

    const footerNavItems: NavItem[] = [
        {
            title: 'Repository',
            href: 'https://github.com/laravel/react-starter-kit',
            icon: FolderGit2,
        },
        {
            title: 'Documentation',
            href: 'https://laravel.com/docs/starter-kits',
            icon: BookOpen,
        },
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboardUrl} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
                {/* Team Switcher — hanya tampil jika user punya lebih dari 1 team */}
                {auth?.user?.teams?.length > 1 && (
                    <SidebarMenu>
                        <SidebarMenuItem>
                            <TeamSwitcher />
                        </SidebarMenuItem>
                    </SidebarMenu>
                )}
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
