<?php

namespace App\Notifications\Organizations;

use App\Models\OrganizationInvitation as OrganizationInvitationModel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrganizationInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public OrganizationInvitationModel $invitation)
    {
        //
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $organization = $this->invitation->organization;
        $inviter = $this->invitation->inviter;

        return (new MailMessage)
            ->subject(__('Anda diundang bergabung ke :organization', ['organization' => $organization->name]))
            ->line(__(':inviter mengundang Anda sebagai :role di akun :organization.', [
                'inviter' => $inviter->name,
                'role' => $this->invitation->role->label(),
                'organization' => $organization->name,
            ]))
            ->action(__('Terima Undangan'), url("/organization-invitations/{$this->invitation->code}/accept"));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'invitation_id' => $this->invitation->id,
            'organization_id' => $this->invitation->organization_id,
            'organization_name' => $this->invitation->organization->name,
            'role' => $this->invitation->role->value,
        ];
    }
}
