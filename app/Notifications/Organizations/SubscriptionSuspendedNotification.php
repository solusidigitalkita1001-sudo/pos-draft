<?php

namespace App\Notifications\Organizations;

use App\Enums\NotificationPreferenceKey;
use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionSuspendedNotification extends Notification implements ShouldQueue
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
        return $notifiable->wantsNotification(NotificationPreferenceKey::SubscriptionSuspended) ? ['mail'] : [];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $organization = $this->subscription->organization;

        return (new MailMessage)
            ->subject(__('Akses akun :organization dibatasi sementara', ['organization' => $organization->name]))
            ->greeting(__('Halo :name,', ['name' => $notifiable->name]))
            ->line(__('Akses ke toko-toko di bawah :organization dibatasi sementara karena langganan belum diperpanjang.', [
                'organization' => $organization->name,
            ]))
            ->line(__('Perpanjang atau upgrade paket kapan saja untuk mengaktifkan kembali akses toko Anda.'))
            ->action(__('Perpanjang Sekarang'), url('/settings/organization/stores'));
    }
}
