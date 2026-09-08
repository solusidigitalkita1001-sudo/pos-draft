<?php

namespace App\Notifications\Organizations;

use App\Enums\NotificationPreferenceKey;
use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PlanDowngradeAppliedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Subscription $subscription)
    {
        //
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return $notifiable->wantsNotification(NotificationPreferenceKey::PlanDowngradeApplied) ? ['mail'] : [];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $organization = $this->subscription->organization;

        return (new MailMessage)
            ->subject(__('Paket :organization telah diturunkan', ['organization' => $organization->name]))
            ->greeting(__('Halo :name,', ['name' => $notifiable->name]))
            ->line(__('Sesuai permintaan Anda, paket :organization sekarang :plan.', [
                'organization' => $organization->name,
                'plan' => $this->subscription->plan->name,
            ]))
            ->action(__('Lihat Toko Saya'), url('/settings/organization/stores'));
    }
}
