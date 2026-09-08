import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Card, CardContent } from '@/components/ui/card';
import { Switch } from '@/components/ui/switch';
import { edit } from '@/routes/notifications';

type Preference = {
    key: string;
    label: string;
    description: string;
    enabled: boolean;
};

type Props = {
    preferences: Preference[];
};

export default function NotificationPreferences({ preferences }: Props) {
    const [values, setValues] = useState<Record<string, boolean>>(
        Object.fromEntries(preferences.map((p) => [p.key, p.enabled])),
    );
    const [savingKey, setSavingKey] = useState<string | null>(null);

    function toggle(key: string, next: boolean) {
        const previousValues = values;
        const nextValues = { ...values, [key]: next };

        setValues(nextValues);
        setSavingKey(key);

        router.patch(
            '/settings/notifications',
            { preferences: nextValues },
            {
                preserveScroll: true,
                onError: () => setValues(previousValues), // roll back on failure
                onFinish: () => setSavingKey(null),
            },
        );
    }

    return (
        <>
            <Head title="Preferensi Notifikasi" />

            <div className="flex flex-col space-y-6">
                <Heading
                    variant="small"
                    title="Preferensi Notifikasi"
                    description="Pilih email mana yang ingin Anda terima. Notifikasi transaksional (mis. undangan) selalu terkirim."
                />

                <Card>
                    <CardContent className="divide-y divide-border p-0">
                        {preferences.map((preference) => (
                            <div
                                key={preference.key}
                                className="flex items-center justify-between gap-4 p-4"
                            >
                                <div>
                                    <p className="text-sm font-medium">
                                        {preference.label}
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        {preference.description}
                                    </p>
                                </div>
                                <Switch
                                    checked={values[preference.key]}
                                    disabled={savingKey === preference.key}
                                    onCheckedChange={(checked) =>
                                        toggle(preference.key, checked)
                                    }
                                    data-test={`notification-toggle-${preference.key}`}
                                />
                            </div>
                        ))}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

NotificationPreferences.layout = {
    breadcrumbs: [
        {
            title: 'Preferensi Notifikasi',
            href: edit(),
        },
    ],
};
