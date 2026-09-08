import { Form } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { review } from '@/routes/admin/custom-plan-requests';
import type { AdminCustomPlanRequest } from '@/types';

type Props = {
    request: AdminCustomPlanRequest;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function ReviewCustomPlanRequestModal({
    request,
    open,
    onOpenChange,
}: Props) {
    const [decision, setDecision] = useState<'approve' | 'reject'>('approve');

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <Form
                    key={`${request.id}-${decision}`}
                    {...review.form(request.id)}
                    className="space-y-6"
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ errors, processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>
                                    Review Permintaan — {request.organizationName}
                                </DialogTitle>
                                <DialogDescription>
                                    Diajukan oleh {request.requestedByName} (
                                    {request.requestedByEmail}) · Toko saat
                                    ini: {request.currentStoreCount}
                                </DialogDescription>
                            </DialogHeader>

                            {request.message ? (
                                <p className="rounded-md bg-muted p-3 text-sm text-muted-foreground">
                                    {request.message}
                                </p>
                            ) : null}

                            <input type="hidden" name="decision" value={decision} />

                            {decision === 'approve' ? (
                                <div className="space-y-4">
                                    <div className="grid gap-2">
                                        <Label htmlFor="approved_max_stores">
                                            Kuota toko yang disetujui
                                        </Label>
                                        <Input
                                            id="approved_max_stores"
                                            name="approved_max_stores"
                                            type="number"
                                            min={request.currentStoreCount}
                                            defaultValue={
                                                request.requestedMaxStores ??
                                                request.currentStoreCount
                                            }
                                            required
                                        />
                                        <InputError
                                            message={
                                                errors.approved_max_stores
                                            }
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="approved_max_owners">
                                            Kuota akun owner yang disetujui
                                        </Label>
                                        <Input
                                            id="approved_max_owners"
                                            name="approved_max_owners"
                                            type="number"
                                            min={1}
                                            defaultValue={
                                                request.requestedMaxOwners ?? 1
                                            }
                                            required
                                        />
                                        <InputError
                                            message={
                                                errors.approved_max_owners
                                            }
                                        />
                                    </div>
                                </div>
                            ) : null}

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary">Batal</Button>
                                </DialogClose>

                                {decision === 'approve' ? (
                                    <Button
                                        type="submit"
                                        disabled={processing}
                                        data-test="approve-custom-plan-request"
                                    >
                                        Setujui
                                    </Button>
                                ) : (
                                    <Button
                                        type="submit"
                                        variant="destructive"
                                        disabled={processing}
                                        data-test="reject-custom-plan-request"
                                    >
                                        Tolak
                                    </Button>
                                )}

                                <Button
                                    type="button"
                                    variant="ghost"
                                    onClick={() =>
                                        setDecision((prev) =>
                                            prev === 'approve'
                                                ? 'reject'
                                                : 'approve',
                                        )
                                    }
                                >
                                    {decision === 'approve'
                                        ? 'Tolak sebagai gantinya'
                                        : 'Setujui sebagai gantinya'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
