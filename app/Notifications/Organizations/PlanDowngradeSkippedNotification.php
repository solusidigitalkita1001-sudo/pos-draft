<?php

namespace App\Notifications\Organizations;

use App\Enums\NotificationPreferenceKey;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PlanDowngradeSkippedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Subscription $subscription, public Plan $intendedPlan)
    {
        //
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return $notifiable->wantsNotification(NotificationPreferenceKey::PlanDowngradeSkipped) ? ['mail'] : [];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $organization = $this->subscription->organization;

        return (new MailMessage)
            ->subject(__('Downgrade paket :organization dibatalkan otomatis', ['organization' => $organization->name]))
            ->greeting(__('Halo :name,', ['name' => $notifiable->name]))
            ->line(__('Downgrade ke paket :plan untuk :organization tidak bisa diterapkan karena jumlah toko Anda sekarang melebihi kuota paket tersebut.', [
                'plan' => $this->intendedPlan->name,
                'organization' => $organization->name,
            ]))
            ->line(__('Paket Anda tetap seperti semula (:plan). Kurangi jumlah toko lalu ajukan downgrade lagi kalau masih diinginkan.', [
                'plan' => $this->subscription->plan->name,
            ]))
            ->action(__('Lihat Toko Saya'), url('/settings/organization/stores'));
    }
}
