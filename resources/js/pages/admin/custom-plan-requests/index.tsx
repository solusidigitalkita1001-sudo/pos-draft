import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import ReviewCustomPlanRequestModal from '@/components/review-custom-plan-request-modal';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { AdminCustomPlanRequest, CustomPlanRequestStatus } from '@/types';

type Props = {
    requests: AdminCustomPlanRequest[];
    statusFilter: string;
};

const FILTERS: { value: string; label: string }[] = [
    { value: 'pending', label: 'Menunggu' },
    { value: 'approved', label: 'Disetujui' },
    { value: 'rejected', label: 'Ditolak' },
    { value: 'all', label: 'Semua' },
];

function statusBadgeVariant(status: CustomPlanRequestStatus) {
    switch (status) {
        case 'approved':
            return 'default' as const;
        case 'rejected':
            return 'destructive' as const;
        default:
            return 'secondary' as const;
    }
}

export default function AdminCustomPlanRequestsIndex({
    requests,
    statusFilter,
}: Props) {
    const [activeRequest, setActiveRequest] =
        useState<AdminCustomPlanRequest | null>(null);

    return (
        <>
            <Head title="Custom Plan Requests" />

            <div className="mx-auto flex max-w-3xl flex-col space-y-6 p-6">
                <Heading
                    variant="small"
                    title="Custom Plan Requests"
                    description="Permintaan kuota custom dari seluruh organization."
                />

                <div className="flex gap-2">
                    {FILTERS.map((filter) => (
                        <Button
                            key={filter.value}
                            variant={
                                statusFilter === filter.value
                                    ? 'default'
                                    : 'outline'
                            }
                            size="sm"
                            asChild
                        >
                            <Link href={`/admin/custom-plan-requests?status=${filter.value}`}>
                                {filter.label}
                            </Link>
                        </Button>
                    ))}
                </div>

                <div className="space-y-3">
                    {requests.map((request) => (
                        <Card key={request.id}>
                            <CardHeader>
                                <div className="flex items-center justify-between">
                                    <CardTitle className="text-base">
                                        {request.organizationName}
                                    </CardTitle>
                                    <Badge
                                        variant={statusBadgeVariant(
                                            request.status,
                                        )}
                                    >
                                        {request.statusLabel}
                                    </Badge>
                                </div>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                <p className="text-sm text-muted-foreground">
                                    {request.requestedByName} (
                                    {request.requestedByEmail}) — minta{' '}
                                    {request.requestedMaxStores ?? '—'} toko,{' '}
                                    {request.requestedMaxOwners ?? '—'} owner.
                                    Toko saat ini: {request.currentStoreCount}
                                    .
                                </p>

                                {request.message ? (
                                    <p className="rounded-md bg-muted p-3 text-sm">
                                        {request.message}
                                    </p>
                                ) : null}

                                {request.status === 'pending' ? (
                                    <Button
                                        size="sm"
                                        onClick={() =>
                                            setActiveRequest(request)
                                        }
                                        data-test={`review-request-${request.id}`}
                                    >
                                        Review
                                    </Button>
                                ) : (
                                    <p className="text-xs text-muted-foreground">
                                        Direview oleh {request.reviewedByName}
                                    </p>
                                )}
                            </CardContent>
                        </Card>
                    ))}

                    {requests.length === 0 ? (
                        <p className="py-8 text-center text-muted-foreground">
                            Tidak ada permintaan.
                        </p>
                    ) : null}
                </div>
            </div>

            {activeRequest ? (
                <ReviewCustomPlanRequestModal
                    request={activeRequest}
                    open={!!activeRequest}
                    onOpenChange={(open) => {
                        if (!open) setActiveRequest(null);
                    }}
                />
            ) : null}
        </>
    );
}
