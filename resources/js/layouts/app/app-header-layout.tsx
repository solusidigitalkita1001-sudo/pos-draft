/**
 * resources/js/layouts/app/app-header-layout.tsx
 *
 * Layout dengan header + sidebar statis di desktop (lg+).
 * Di bawah lg, sidebar disembunyikan — navigasi via mobile sheet di AppHeader.
 */
import { AppContent } from '@/components/app-content';
import { AppHeader } from '@/components/app-header';
import { AppShell } from '@/components/app-shell';
// ✅ Import sidebar statis dari file dedicated
import StaticSidebar from '@/components/ui/mobile-sidebar';
import type { AppLayoutProps } from '@/types';

export default function AppHeaderLayout({
    children,
    breadcrumbs,
}: AppLayoutProps) {
    return (
        <AppShell variant="header">
            <AppHeader breadcrumbs={breadcrumbs} />

            {/*
             * Wrapper flex: sidebar kiri (hanya lg+) + konten kanan.
             * `hidden lg:flex` membuat sidebar statis hanya muncul di desktop.
             */}
            <div className="flex flex-1 overflow-hidden">
                {/* Sidebar statis — HANYA tampil di lg ke atas */}
                <div className="hidden lg:block lg:shrink-0">
                    <StaticSidebar
                        style={{
                            height: '100%',
                            position: 'sticky',
                            top: 0,
                        }}
                    />
                </div>

                {/* Main content */}
                <div className="flex-1 overflow-auto">
                    <AppContent variant="header">{children}</AppContent>
                </div>
            </div>
        </AppShell>
    );
}