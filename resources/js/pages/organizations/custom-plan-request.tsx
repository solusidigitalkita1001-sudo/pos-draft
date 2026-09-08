import { Form, Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { store as storeCustomPlanRequest } from '@/routes/organizations/custom-plan-request';

type Props = {
    organization: { name: string };
    currentStoreCount: number;
    pendingRequest: {
        id: number;
        requested_max_stores: number | null;
        requested_max_owners: number | null;
        message: string | null;
    } | null;
};

export default function CustomPlanRequestForm({
    organization,
    currentStoreCount,
    pendingRequest,
}: Props) {
    return (
        <>
            <Head title="Request Paket Custom" />

            <div className="mx-auto flex max-w-lg flex-col space-y-6">
                <Heading
                    variant="small"
                    title="Request Paket Custom"
                    description={`Untuk ${organization.name} — kuota toko/owner akan dikoordinasikan manual oleh tim kami.`}
                />

                {pendingRequest ? (
                    <Alert>
                        <AlertDescription>
                            Anda sudah punya permintaan yang sedang ditinjau
                            ({pendingRequest.requested_max_stores ?? '—'}{' '}
                            toko, {pendingRequest.requested_max_owners ?? '—'}{' '}
                            owner). Mengirim form ini akan membuat permintaan
                            baru.
                        </AlertDescription>
                    </Alert>
                ) : null}

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            Detail Kebutuhan
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Form
                            {...storeCustomPlanRequest.form()}
                            className="space-y-6"
                        >
                            {({ errors, processing }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="requested_max_stores">
                                            Jumlah toko yang dibutuhkan
                                        </Label>
                                        <Input
                                            id="requested_max_stores"
                                            name="requested_max_stores"
                                            type="number"
                                            min={currentStoreCount}
                                            placeholder={`Saat ini: ${currentStoreCount} toko`}
                                        />
                                        <InputError
                                            message={
                                                errors.requested_max_stores
                                            }
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="requested_max_owners">
                                            Jumlah akun owner yang dibutuhkan
                                        </Label>
                                        <Input
                                            id="requested_max_owners"
                                            name="requested_max_owners"
                                            type="number"
                                            min={1}
                                            placeholder="Contoh: 2"
                                        />
                                        <InputError
                                            message={
                                                errors.requested_max_owners
                                            }
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="message">
                                            Catatan tambahan (opsional)
                                        </Label>
                                        <Textarea
                                            id="message"
                                            name="message"
                                            rows={4}
                                            placeholder="Ceritakan kebutuhan bisnis Anda..."
                                        />
                                        <InputError message={errors.message} />
                                    </div>

                                    <Button
                                        type="submit"
                                        disabled={processing}
                                        data-test="submit-custom-plan-request"
                                    >
                                        Kirim Permintaan
                                    </Button>
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

CustomPlanRequestForm.layout = {
    breadcrumbs: [
        {
            title: 'Toko Saya',
            href: '/settings/organization/stores',
        },
        {
            title: 'Request Paket Custom',
            href: '/settings/organization/custom-plan-request',
        },
    ],
};
