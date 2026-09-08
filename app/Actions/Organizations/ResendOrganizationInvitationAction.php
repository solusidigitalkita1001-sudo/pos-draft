<?php

namespace App\Actions\Organizations;

use App\Models\OrganizationInvitation;
use App\Notifications\Organizations\OrganizationInvitationNotification;
use Illuminate\Support\Facades\Notification;
use RuntimeException;

class ResendOrganizationInvitationAction
{
    /**
     * Resend a pending invitation email, refreshing its expiry so it
     * doesn't just re-send an already-expired link.
     *
     * @throws RuntimeException
     */
    public function execute(OrganizationInvitation $invitation): void
    {
        if ($invitation->isAccepted()) {
            throw new RuntimeException('Undangan ini sudah diterima, tidak bisa dikirim ulang.');
        }

        $invitation->update(['expires_at' => now()->addDays(3)]);

        Notification::route('mail', $invitation->email)
            ->notify(new OrganizationInvitationNotification($invitation));
    }
}
