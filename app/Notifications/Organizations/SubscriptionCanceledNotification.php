<?php

namespace App\Notifications\Organizations;

use App\Enums\NotificationPreferenceKey;
use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionCanceledNotification extends Notification implements ShouldQueue
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
        return $notifiable->wantsNotification(NotificationPreferenceKey::SubscriptionCanceled) ? ['mail'] : [];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $organization = $this->subscription->organization;

        return (new MailMessage)
            ->subject(__('Langganan :organization telah berakhir', ['organization' => $organization->name]))
            ->greeting(__('Halo :name,', ['name' => $notifiable->name]))
            ->line(__('Sesuai permintaan pembatalan Anda, langganan :organization telah berakhir dan akses toko dibatasi.', [
                'organization' => $organization->name,
            ]))
            ->line(__('Anda bisa berlangganan lagi kapan saja lewat halaman Toko Saya.'))
            ->action(__('Lihat Paket'), url('/settings/organization/stores'));
    }
}
