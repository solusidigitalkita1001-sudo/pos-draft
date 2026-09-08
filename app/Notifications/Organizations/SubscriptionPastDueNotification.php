<?php

namespace App\Notifications\Organizations;

use App\Enums\NotificationPreferenceKey;
use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionPastDueNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Subscription $subscription, public int $graceDays)
    {
        //
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return $notifiable->wantsNotification(NotificationPreferenceKey::SubscriptionPastDue) ? ['mail'] : [];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $organization = $this->subscription->organization;

        return (new MailMessage)
            ->subject(__('Perpanjangan langganan :organization diperlukan', ['organization' => $organization->name]))
            ->greeting(__('Halo :name,', ['name' => $notifiable->name]))
            ->line(__('Periode langganan akun :organization sudah berakhir dan belum diperpanjang.', [
                'organization' => $organization->name,
            ]))
            ->line(__('Anda punya waktu :days hari lagi sebelum akses toko dibatasi sementara.', [
                'days' => $this->graceDays,
            ]))
            ->action(__('Perpanjang Sekarang'), url('/settings/organization/stores'));
    }
}
