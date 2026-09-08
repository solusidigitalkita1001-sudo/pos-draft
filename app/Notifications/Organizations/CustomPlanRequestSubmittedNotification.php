<?php

namespace App\Notifications\Organizations;

use App\Enums\NotificationPreferenceKey;
use App\Models\CustomPlanRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CustomPlanRequestSubmittedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public CustomPlanRequest $request)
    {
        //
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        // Database channel (in-app admin to-do record) always fires —
        // only the email is toggleable, since this is an actionable
        // item for a platform admin, not just an FYI.
        return $notifiable->wantsNotification(NotificationPreferenceKey::CustomPlanRequestSubmitted)
            ? ['mail', 'database']
            : ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $organization = $this->request->organization;

        return (new MailMessage)
            ->subject(__('Permintaan paket custom baru — :organization', ['organization' => $organization->name]))
            ->greeting(__('Halo :name,', ['name' => $notifiable->name]))
            ->line(__(':organization mengajukan permintaan paket custom.', ['organization' => $organization->name]))
            ->line(__('Diminta: :stores toko, :owners akun owner.', [
                'stores' => $this->request->requested_max_stores ?? '—',
                'owners' => $this->request->requested_max_owners ?? '—',
            ]))
            ->action(__('Review Permintaan'), url('/admin/custom-plan-requests'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'custom_plan_request_id' => $this->request->id,
            'organization_name' => $this->request->organization->name,
        ];
    }
}
