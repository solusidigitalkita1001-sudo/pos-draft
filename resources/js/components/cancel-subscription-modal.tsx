import { Form } from '@inertiajs/react';
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
import { cancel } from '@/routes/organizations/subscription';

type Props = {
    periodEndLabel: string | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function CancelSubscriptionModal({
    periodEndLabel,
    open,
    onOpenChange,
}: Props) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <Form
                    key={String(open)}
                    {...cancel.form()}
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>Batalkan langganan?</DialogTitle>
                                <DialogDescription>
                                    {periodEndLabel
                                        ? `Akses toko Anda tetap normal sampai ${periodEndLabel}. Setelah itu, akses akan dibatasi sampai Anda berlangganan lagi.`
                                        : 'Akses toko akan dibatasi setelah periode langganan saat ini berakhir.'}{' '}
                                    Anda bisa membatalkan pembatalan ini kapan
                                    saja sebelum tanggal tersebut.
                                </DialogDescription>
                            </DialogHeader>

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary">
                                        Tidak Jadi
                                    </Button>
                                </DialogClose>

                                <Button
                                    type="submit"
                                    variant="destructive"
                                    disabled={processing}
                                    data-test="confirm-cancel-subscription"
                                >
                                    Ya, Batalkan Langganan
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
