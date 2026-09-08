import { Form, Head, Link, router } from '@inertiajs/react';
import { Check, Plus, Store } from 'lucide-react';
import { useState } from 'react';
import CancelSubscriptionModal from '@/components/cancel-subscription-modal';
import CreateTeamModal from '@/components/create-team-modal';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';
import { edit as editTeam } from '@/routes/teams';
import { store as checkoutStore } from '@/routes/organizations/checkout';
import { create as createCustomPlanRequest } from '@/routes/organizations/custom-plan-request';
import {
    resume as resumeSubscription,
} from '@/routes/organizations/subscription';
import type {
    OrganizationPlan,
    OrganizationQuota,
    OrganizationStore,
    OrganizationSubscription,
    OrganizationSummary,
} from '@/types';

type Props = {
    organization: OrganizationSummary;
    subscription: OrganizationSubscription | null;
    quota: OrganizationQuota;
    stores: OrganizationStore[];
    plans: OrganizationPlan[];
    canManage: boolean;
};

function subscriptionBadgeVariant(status: OrganizationSubscription['status']) {
    switch (status) {
        case 'active':
            return 'default' as const;
        case 'trial':
            return 'secondary' as const;
        case 'past_due':
            return 'outline' as const;
        default:
            return 'destructive' as const;
    }
}

function formatRupiah(value: string | null) {
    if (value === null) return 'Hubungi sales';

    const amount = Number(value);

    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(amount);
}

function formatDate(value: string) {
    return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'long',
    }).format(new Date(value));
}

export default function OrganizationStores({
    organization,
    subscription,
    quota,
    stores,
    plans,
    canManage,
}: Props) {
    const quotaPercent =
        quota.maxStores === null
            ? 0
            : Math.min(100, (quota.storeCount / quota.maxStores) * 100);

    const [billingPeriod, setBillingPeriod] = useState<'monthly' | 'yearly'>('monthly');
    const [cancelModalOpen, setCancelModalOpen] = useState(false);
    const [downgradingCode, setDowngradingCode] = useState<string | null>(null);

    function handleDowngrade(plan: OrganizationPlan) {
        if (
            !window.confirm(
                `Turunkan ke paket ${plan.name}? Perubahan ini baru berlaku mulai akhir periode langganan Anda saat ini — akses tetap normal sampai saat itu.`,
            )
        ) {
            return;
        }

        setDowngradingCode(plan.code);
        router.post(
            '/settings/organization/subscription/downgrade',
            { plan_code: plan.code },
            { preserveScroll: true, onFinish: () => setDowngradingCode(null) },
        );
    }

    return (
        <>
            <Head title="Toko Saya" />

            <div className="flex flex-col space-y-8">
                <div className="flex items-center justify-between">
                    <Heading
                        variant="small"
                        title="Toko Saya"
                        description={`Semua toko di bawah ${organization.name}`}
                    />

                    {subscription ? (
                        <Badge variant={subscriptionBadgeVariant(subscription.status)}>
                            {subscription.planName} ·{' '}
                            {subscription.canceledAt
                                ? 'Akan berakhir'
                                : subscription.statusLabel}
                        </Badge>
                    ) : null}
                </div>

                {/* ── Cancellation notice / cancel-subscription ── */}
                {subscription && canManage && subscription.status !== 'canceled' && !subscription.isCustom ? (
                    subscription.canceledAt ? (
                        <Card className="border-destructive/50">
                            <CardContent className="flex items-center justify-between py-4">
                                <p className="text-sm">
                                    Langganan dijadwalkan berakhir pada{' '}
                                    <strong>
                                        {subscription.currentPeriodEnd
                                            ? formatDate(subscription.currentPeriodEnd)
                                            : '-'}
                                    </strong>
                                    . Akses toko tetap normal sampai tanggal
                                    tersebut.
                                </p>
                                <Form {...resumeSubscription.form()}>
                                    {({ processing }) => (
                                        <Button
                                            type="submit"
                                            variant="outline"
                                            disabled={processing}
                                            data-test="resume-subscription"
                                        >
                                            Batalkan Pembatalan
                                        </Button>
                                    )}
                                </Form>
                            </CardContent>
                        </Card>
                    ) : (
                        <div className="flex justify-end">
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                className="text-muted-foreground"
                                onClick={() => setCancelModalOpen(true)}
                                data-test="cancel-subscription"
                            >
                                Batalkan Langganan
                            </Button>
                        </div>
                    )
                ) : null}

                {/* ── Pending downgrade notice ────────────────── */}
                {subscription && canManage && subscription.pendingPlanCode ? (
                    <Card className="border-amber-500/50">
                        <CardContent className="flex items-center justify-between py-4">
                            <p className="text-sm">
                                Paket akan turun ke{' '}
                                <strong>{subscription.pendingPlanName}</strong>{' '}
                                mulai{' '}
                                <strong>
                                    {subscription.currentPeriodEnd
                                        ? formatDate(subscription.currentPeriodEnd)
                                        : 'akhir periode saat ini'}
                                </strong>
                                . Sampai saat itu, Anda tetap menikmati
                                kuota paket {subscription.planName}.
                            </p>
                            <Form
                                action="/settings/organization/subscription/cancel-downgrade"
                                method="post"
                            >
                                {({ processing }) => (
                                    <Button
                                        type="submit"
                                        variant="outline"
                                        disabled={processing}
                                        data-test="cancel-downgrade"
                                    >
                                        Batalkan Downgrade
                                    </Button>
                                )}
                            </Form>
                        </CardContent>
                    </Card>
                ) : null}

                {/* ── Quota usage ─────────────────────────────── */}
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            Kuota Toko
                        </CardTitle>
                        <CardDescription>
                            {quota.maxStores === null
                                ? `${quota.storeCount} toko digunakan · kuota tidak dibatasi (paket custom)`
                                : `${quota.storeCount} dari ${quota.maxStores} toko digunakan`}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {quota.maxStores !== null ? (
                            <div className="h-2 w-full overflow-hidden rounded-full bg-muted">
                                <div
                                    className={cn(
                                        'h-full rounded-full transition-all',
                                        quota.isFull
                                            ? 'bg-destructive'
                                            : 'bg-primary',
                                    )}
                                    style={{ width: `${quotaPercent}%` }}
                                />
                            </div>
                        ) : null}

                        {quota.isFull ? (
                            <p className="mt-3 text-sm text-destructive">
                                Kuota toko pada paket Anda sudah penuh.
                                Upgrade paket di bawah untuk menambah toko.
                            </p>
                        ) : null}
                    </CardContent>
                </Card>

                {/* ── Store list ──────────────────────────────── */}
                <div className="space-y-3">
                    <div className="flex items-center justify-between">
                        <h2 className="text-sm font-medium text-muted-foreground">
                            Daftar Toko
                        </h2>

                        <TooltipProvider>
                            {quota.isFull ? (
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <span>
                                            <Button disabled data-test="add-store-disabled">
                                                <Plus /> Tambah Toko
                                            </Button>
                                        </span>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p>Kuota toko sudah penuh — upgrade paket dulu</p>
                                    </TooltipContent>
                                </Tooltip>
                            ) : (
                                <CreateTeamModal>
                                    <Button data-test="add-store-button">
                                        <Plus /> Tambah Toko
                                    </Button>
                                </CreateTeamModal>
                            )}
                        </TooltipProvider>
                    </div>

                    <div className="space-y-3">
                        {stores.map((store) => (
                            <div
                                key={store.id}
                                className="flex items-center justify-between rounded-lg border p-4"
                            >
                                <div className="flex items-center gap-3">
                                    <Store className="h-4 w-4 text-muted-foreground" />
                                    <div>
                                        <div className="flex items-center gap-2">
                                            <span className="font-medium">
                                                {store.name}
                                            </span>
                                            {store.isPersonal ? (
                                                <Badge variant="secondary">
                                                    Personal
                                                </Badge>
                                            ) : null}
                                        </div>
                                    </div>
                                </div>

                                <Button variant="ghost" size="sm" asChild>
                                    <Link href={editTeam(store.slug)}>
                                        Kelola
                                    </Link>
                                </Button>
                            </div>
                        ))}

                        {stores.length === 0 ? (
                            <p className="py-8 text-center text-muted-foreground">
                                Belum ada toko.
                            </p>
                        ) : null}
                    </div>
                </div>

                {/* ── Plans ───────────────────────────────────── */}
                <div className="space-y-3">
                    <div className="flex items-center justify-between">
                        <h2 className="text-sm font-medium text-muted-foreground">
                            Paket Berlangganan
                        </h2>

                        <div className="inline-flex rounded-lg border p-1">
                            <Button
                                type="button"
                                variant={
                                    billingPeriod === 'monthly'
                                        ? 'secondary'
                                        : 'ghost'
                                }
                                size="sm"
                                onClick={() => setBillingPeriod('monthly')}
                                data-test="billing-period-monthly"
                            >
                                Bulanan
                            </Button>
                            <Button
                                type="button"
                                variant={
                                    billingPeriod === 'yearly'
                                        ? 'secondary'
                                        : 'ghost'
                                }
                                size="sm"
                                onClick={() => setBillingPeriod('yearly')}
                                data-test="billing-period-yearly"
                            >
                                Tahunan
                            </Button>
                        </div>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        {plans.map((plan) => (
                            <Card
                                key={plan.code}
                                className={cn(
                                    plan.isCurrent && 'border-primary',
                                )}
                            >
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2 text-base">
                                        {plan.name}
                                        {plan.isCurrent ? (
                                            <Badge>Paket Aktif</Badge>
                                        ) : null}
                                    </CardTitle>
                                    <CardDescription>
                                        {plan.maxStores === null
                                            ? 'Kuota toko custom'
                                            : `Maks. ${plan.maxStores} toko`}
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <p className="text-2xl font-semibold">
                                        {plan.isCustom
                                            ? 'Hubungi Sales'
                                            : billingPeriod === 'monthly'
                                              ? `${formatRupiah(plan.priceMonthly)}/bln`
                                              : `${formatRupiah(plan.priceYearly)}/thn`}
                                    </p>
                                </CardContent>
                                <CardFooter>
                                    {plan.isCustom ? (
                                        <Button variant="outline" className="w-full" asChild>
                                            <Link href={createCustomPlanRequest()}>
                                                Request Paket Custom
                                            </Link>
                                        </Button>
                                    ) : plan.isCurrent ? (
                                        <Button className="w-full" disabled>
                                            <Check /> Sedang Dipakai
                                        </Button>
                                    ) : !canManage ? (
                                        <Button className="w-full" disabled>
                                            Hanya Owner
                                        </Button>
                                    ) : subscription?.pendingPlanCode === plan.code ? (
                                        <Button className="w-full" variant="outline" disabled>
                                            Downgrade Terjadwal
                                        </Button>
                                    ) : plan.isDowngrade ? (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            className="w-full"
                                            disabled={downgradingCode === plan.code}
                                            onClick={() => handleDowngrade(plan)}
                                            data-test={`downgrade-${plan.code}`}
                                        >
                                            {downgradingCode === plan.code
                                                ? 'Memproses...'
                                                : 'Downgrade'}
                                        </Button>
                                    ) : (
                                        <Form
                                            {...checkoutStore.form()}
                                            className="w-full"
                                        >
                                            {({ processing }) => (
                                                <>
                                                    <input
                                                        type="hidden"
                                                        name="plan_code"
                                                        value={plan.code}
                                                    />
                                                    <input
                                                        type="hidden"
                                                        name="billing_period"
                                                        value={billingPeriod}
                                                    />
                                                    <Button
                                                        type="submit"
                                                        className="w-full"
                                                        disabled={processing}
                                                        data-test={`checkout-${plan.code}`}
                                                    >
                                                        Pilih Paket
                                                    </Button>
                                                </>
                                            )}
                                        </Form>
                                    )}
                                </CardFooter>
                            </Card>
                        ))}
                    </div>
                </div>
            </div>

            {subscription ? (
                <CancelSubscriptionModal
                    periodEndLabel={
                        subscription.currentPeriodEnd
                            ? formatDate(subscription.currentPeriodEnd)
                            : null
                    }
                    open={cancelModalOpen}
                    onOpenChange={setCancelModalOpen}
                />
            ) : null}
        </>
    );
}

OrganizationStores.layout = {
    breadcrumbs: [
        {
            title: 'Toko Saya',
            href: '/settings/organization/stores',
        },
    ],
};
