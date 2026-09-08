import { Head } from '@inertiajs/react';
import { Download, FileText } from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { download as downloadInvoices, pdf as invoicePdf } from '@/routes/organizations/invoices';
import type { OrganizationInvoice, OrganizationInvoiceStatus } from '@/types';

type Props = {
    invoices: OrganizationInvoice[];
};

function statusBadgeVariant(status: OrganizationInvoiceStatus) {
    switch (status) {
        case 'paid':
            return 'default' as const;
        case 'pending':
            return 'secondary' as const;
        default:
            return 'destructive' as const;
    }
}

function formatRupiah(value: string) {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(Number(value));
}

function formatDate(value: string) {
    return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

export default function OrganizationInvoices({ invoices }: Props) {
    return (
        <>
            <Head title="Riwayat Invoice" />

            <div className="flex flex-col space-y-6">
                <div className="flex items-center justify-between">
                    <Heading
                        variant="small"
                        title="Riwayat Invoice"
                        description="Semua invoice upgrade paket untuk organization Anda."
                    />

                    {invoices.length > 0 ? (
                        <Button variant="outline" size="sm" asChild>
                            <a href={downloadInvoices()}>
                                <Download className="h-4 w-4" />
                                Export CSV
                            </a>
                        </Button>
                    ) : null}
                </div>

                {invoices.length === 0 ? (
                    <p className="py-8 text-center text-muted-foreground">
                        Belum ada invoice.
                    </p>
                ) : (
                    <div className="overflow-x-auto rounded-lg border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Order ID</TableHead>
                                    <TableHead>Paket</TableHead>
                                    <TableHead>Periode</TableHead>
                                    <TableHead>Jumlah</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Tanggal</TableHead>
                                    <TableHead className="text-right">
                                        PDF
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {invoices.map((invoice) => (
                                    <TableRow key={invoice.orderId}>
                                        <TableCell className="font-mono text-xs">
                                            {invoice.orderId}
                                        </TableCell>
                                        <TableCell>
                                            {invoice.planName}
                                        </TableCell>
                                        <TableCell>
                                            {invoice.billingPeriodLabel}
                                        </TableCell>
                                        <TableCell>
                                            {formatRupiah(invoice.amount)}
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                variant={statusBadgeVariant(
                                                    invoice.status,
                                                )}
                                            >
                                                {invoice.statusLabel}
                                            </Badge>
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {formatDate(
                                                invoice.paidAt ??
                                                    invoice.createdAt,
                                            )}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                asChild
                                                data-test={`invoice-pdf-${invoice.orderId}`}
                                            >
                                                <a
                                                    href={invoicePdf(
                                                        invoice.orderId,
                                                    )}
                                                >
                                                    <FileText className="h-4 w-4" />
                                                </a>
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}
            </div>
        </>
    );
}

OrganizationInvoices.layout = {
    breadcrumbs: [
        {
            title: 'Toko Saya',
            href: '/settings/organization/stores',
        },
        {
            title: 'Riwayat Invoice',
            href: '/settings/organization/invoices',
        },
    ],
};
