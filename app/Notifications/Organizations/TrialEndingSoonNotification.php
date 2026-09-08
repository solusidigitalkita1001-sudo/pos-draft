<?php

namespace App\Notifications\Organizations;

use App\Enums\NotificationPreferenceKey;
use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TrialEndingSoonNotification extends Notification implements ShouldQueue
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
        return $notifiable->wantsNotification(NotificationPreferenceKey::TrialEndingSoon) ? ['mail'] : [];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $organization = $this->subscription->organization;
        $daysLeft = max(0, (int) now()->diffInDays($this->subscription->trial_ends_at, false));

        return (new MailMessage)
            ->subject(__('Masa trial :organization akan berakhir', ['organization' => $organization->name]))
            ->greeting(__('Halo :name,', ['name' => $notifiable->name]))
            ->line(__('Masa trial akun :organization akan berakhir dalam :days hari.', [
                'organization' => $organization->name,
                'days' => $daysLeft,
            ]))
            ->line(__('Upgrade paket sekarang supaya toko Anda tidak terganggu setelah masa trial berakhir.'))
            ->action(__('Lihat Paket'), url('/settings/organization/stores'));
    }
}
