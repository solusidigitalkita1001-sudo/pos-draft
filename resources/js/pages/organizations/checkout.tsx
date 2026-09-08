import { Head, router } from '@inertiajs/react';
import { Loader2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { stores as organizationStores } from '@/routes/organizations';

type Props = {
    invoice: {
        orderId: string;
        status: string;
        snapToken: string | null;
        planName: string;
        amount: string;
    };
    midtrans: {
        clientKey: string | null;
        isProduction: boolean;
    };
};

declare global {
    interface Window {
        snap?: {
            pay: (
                token: string,
                callbacks: {
                    onSuccess?: (result: unknown) => void;
                    onPending?: (result: unknown) => void;
                    onError?: (result: unknown) => void;
                    onClose?: () => void;
                },
            ) => void;
        };
    }
}

function formatRupiah(value: string) {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(Number(value));
}

export default function OrganizationCheckout({ invoice, midtrans }: Props) {
    const [status, setStatus] = useState<'loading' | 'ready' | 'error'>('loading');

    useEffect(() => {
        if (!invoice.snapToken || !midtrans.clientKey) {
            setStatus('error');
            return;
        }

        const scriptUrl = midtrans.isProduction
            ? 'https://app.midtrans.com/snap/snap.js'
            : 'https://app.sandbox.midtrans.com/snap/snap.js';

        const script = document.createElement('script');
        script.src = scriptUrl;
        script.setAttribute('data-client-key', midtrans.clientKey);
        script.onload = () => {
            setStatus('ready');
            window.snap?.pay(invoice.snapToken as string, {
                onSuccess: () => router.visit(organizationStores()),
                onPending: () => router.visit(organizationStores()),
                onError: () => setStatus('error'),
                onClose: () => router.visit(organizationStores()),
            });
        };
        script.onerror = () => setStatus('error');
        document.body.appendChild(script);

        return () => {
            document.body.removeChild(script);
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    return (
        <>
            <Head title="Checkout" />

            <div className="mx-auto flex max-w-md flex-col space-y-6">
                <Heading
                    variant="small"
                    title="Checkout"
                    description={`Order ID: ${invoice.orderId}`}
                />

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            {invoice.planName}
                        </CardTitle>
                        <CardDescription>
                            {formatRupiah(invoice.amount)}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-col items-center gap-4 py-8">
                        {status === 'loading' || status === 'ready' ? (
                            <>
                                <Loader2 className="h-6 w-6 animate-spin text-muted-foreground" />
                                <p className="text-center text-sm text-muted-foreground">
                                    Membuka jendela pembayaran Midtrans...
                                </p>
                            </>
                        ) : (
                            <>
                                <p className="text-center text-sm text-destructive">
                                    Gagal memuat jendela pembayaran. Coba
                                    muat ulang halaman ini.
                                </p>
                                <Button variant="outline" asChild>
                                    <a href={window.location.href}>
                                        Muat Ulang
                                    </a>
                                </Button>
                            </>
                        )}
                    </CardContent>
                </Card>

                <Button variant="ghost" asChild>
                    <a href={organizationStores().url}>
                        Batal, kembali ke Toko Saya
                    </a>
                </Button>
            </div>
        </>
    );
}

OrganizationCheckout.layout = {
    breadcrumbs: [
        {
            title: 'Toko Saya',
            href: '/settings/organization/stores',
        },
        {
            title: 'Checkout',
            href: '#',
        },
    ],
};
