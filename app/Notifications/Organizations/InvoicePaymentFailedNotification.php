<?php

namespace App\Notifications\Organizations;

use App\Enums\NotificationPreferenceKey;
use App\Models\SubscriptionInvoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvoicePaymentFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public SubscriptionInvoice $invoice)
    {
        //
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return $notifiable->wantsNotification(NotificationPreferenceKey::InvoicePaymentFailed) ? ['mail'] : [];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Pembayaran paket :plan gagal', ['plan' => $this->invoice->plan->name]))
            ->greeting(__('Halo :name,', ['name' => $notifiable->name]))
            ->line(__('Pembayaran untuk upgrade ke paket :plan (order :order) tidak berhasil diproses.', [
                'plan' => $this->invoice->plan->name,
                'order' => $this->invoice->order_id,
            ]))
            ->line(__('Paket Anda saat ini tetap seperti semula. Silakan coba checkout ulang.'))
            ->action(__('Coba Lagi'), url('/settings/organization/stores'));
    }
}
