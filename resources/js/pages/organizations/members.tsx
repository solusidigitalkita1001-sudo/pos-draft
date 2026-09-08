import { Form, Head, router } from '@inertiajs/react';
import { UserPlus, X } from 'lucide-react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    destroy as destroyInvitation,
    resend as resendInvitation,
    store as storeInvitation,
} from '@/routes/organizations/invitations';
import { destroy as destroyMember } from '@/routes/organizations/members';
import type { OrganizationMember, OrganizationPendingInvitation } from '@/types';

type Props = {
    organization: { name: string };
    canManage: boolean;
    quota: { memberCount: number; maxOwners: number | null };
    members: OrganizationMember[];
    pendingInvitations: OrganizationPendingInvitation[];
};

export default function OrganizationMembers({
    organization,
    canManage,
    quota,
    members,
    pendingInvitations,
}: Props) {
    const quotaFull = quota.maxOwners !== null && quota.memberCount >= quota.maxOwners;

    return (
        <>
            <Head title="Anggota Organization" />

            <div className="flex flex-col space-y-8">
                <Heading
                    variant="small"
                    title="Anggota Organization"
                    description={`Owner & manager di ${organization.name} — relevan untuk paket dengan lebih dari 1 akun owner.`}
                />

                <div className="space-y-3">
                    <h2 className="text-sm font-medium text-muted-foreground">
                        Anggota Saat Ini
                    </h2>

                    {members.map((member) => (
                        <div
                            key={member.userId}
                            className="flex items-center justify-between rounded-lg border p-4"
                        >
                            <div>
                                <div className="flex items-center gap-2">
                                    <span className="font-medium">
                                        {member.name}
                                    </span>
                                    {canManage && !member.isSelf ? null : (
                                        <Badge variant="secondary">
                                            {member.roleLabel}
                                        </Badge>
                                    )}
                                    {member.isSelf ? (
                                        <Badge variant="outline">Anda</Badge>
                                    ) : null}
                                </div>
                                <p className="text-sm text-muted-foreground">
                                    {member.email}
                                </p>
                            </div>

                            {canManage && !member.isSelf ? (
                                <div className="flex items-center gap-2">
                                    <Select
                                        defaultValue={member.role}
                                        onValueChange={(role) =>
                                            router.patch(
                                                `/settings/organization/members/${member.userId}`,
                                                { role },
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        <SelectTrigger
                                            className="w-32"
                                            data-test={`role-select-${member.userId}`}
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="owner">
                                                Owner
                                            </SelectItem>
                                            <SelectItem value="manager">
                                                Manager
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>

                                    <Form
                                        {...destroyMember.form(
                                            member.userId,
                                        )}
                                    >
                                        {({ processing }) => (
                                            <Button
                                                type="submit"
                                                variant="ghost"
                                                size="sm"
                                                disabled={processing}
                                                data-test={`remove-member-${member.userId}`}
                                            >
                                                <X className="h-4 w-4" />
                                            </Button>
                                        )}
                                    </Form>
                                </div>
                            ) : null}
                        </div>
                    ))}
                </div>

                {pendingInvitations.length > 0 ? (
                    <div className="space-y-3">
                        <h2 className="text-sm font-medium text-muted-foreground">
                            Undangan Tertunda
                        </h2>

                        {pendingInvitations.map((invitation) => (
                            <div
                                key={invitation.id}
                                className="flex items-center justify-between rounded-lg border border-dashed p-4"
                            >
                                <div>
                                    <div className="flex items-center gap-2">
                                        <span className="font-medium">
                                            {invitation.email}
                                        </span>
                                        <Badge variant="secondary">
                                            {invitation.roleLabel}
                                        </Badge>
                                    </div>
                                    <p className="text-sm text-muted-foreground">
                                        Menunggu diterima
                                    </p>
                                </div>

                                {canManage ? (
                                    <div className="flex items-center gap-2">
                                        <Form
                                            {...resendInvitation.form(
                                                invitation.id,
                                            )}
                                        >
                                            {({ processing }) => (
                                                <Button
                                                    type="submit"
                                                    variant="outline"
                                                    size="sm"
                                                    disabled={processing}
                                                    data-test={`resend-invitation-${invitation.id}`}
                                                >
                                                    Kirim Ulang
                                                </Button>
                                            )}
                                        </Form>

                                        <Form
                                            {...destroyInvitation.form(
                                                invitation.id,
                                            )}
                                        >
                                            {({ processing }) => (
                                                <Button
                                                    type="submit"
                                                    variant="ghost"
                                                    size="sm"
                                                    disabled={processing}
                                                    data-test={`cancel-invitation-${invitation.id}`}
                                                >
                                                    Batalkan
                                                </Button>
                                            )}
                                        </Form>
                                    </div>
                                ) : null}
                            </div>
                        ))}
                    </div>
                ) : null}

                {canManage ? (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <UserPlus className="h-4 w-4" />
                                Undang Anggota Baru
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            {quotaFull ? (
                                <p className="text-sm text-destructive">
                                    Kuota akun owner/manager pada paket Anda
                                    sudah penuh ({quota.memberCount}/
                                    {quota.maxOwners}). Ajukan paket custom
                                    untuk menambah kuota.
                                </p>
                            ) : (
                                <Form
                                    {...storeInvitation.form()}
                                    resetOnSuccess
                                    className="space-y-4"
                                >
                                    {({ errors, processing }) => (
                                        <>
                                            <div className="grid gap-2">
                                                <Label htmlFor="email">
                                                    Email
                                                </Label>
                                                <Input
                                                    id="email"
                                                    name="email"
                                                    type="email"
                                                    placeholder="owner-kedua@example.com"
                                                    required
                                                />
                                                <InputError
                                                    message={errors.email}
                                                />
                                            </div>

                                            <div className="grid gap-2">
                                                <Label htmlFor="role">
                                                    Peran
                                                </Label>
                                                <Select
                                                    name="role"
                                                    defaultValue="manager"
                                                >
                                                    <SelectTrigger id="role">
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem value="owner">
                                                            Owner
                                                        </SelectItem>
                                                        <SelectItem value="manager">
                                                            Manager
                                                        </SelectItem>
                                                    </SelectContent>
                                                </Select>
                                                <InputError
                                                    message={errors.role}
                                                />
                                            </div>

                                            <Button
                                                type="submit"
                                                disabled={processing}
                                                data-test="send-organization-invitation"
                                            >
                                                Kirim Undangan
                                            </Button>
                                        </>
                                    )}
                                </Form>
                            )}
                        </CardContent>
                    </Card>
                ) : null}
            </div>
        </>
    );
}

OrganizationMembers.layout = {
    breadcrumbs: [
        {
            title: 'Toko Saya',
            href: '/settings/organization/stores',
        },
        {
            title: 'Anggota',
            href: '/settings/organization/members',
        },
    ],
};
