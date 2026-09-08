<?php

namespace App\Notifications\Organizations;

use App\Enums\CustomPlanRequestStatus;
use App\Models\CustomPlanRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CustomPlanRequestReviewedNotification extends Notification implements ShouldQueue
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
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $approved = $this->request->status === CustomPlanRequestStatus::Approved;

        $message = (new MailMessage)
            ->subject($approved
                ? __('Permintaan paket custom Anda disetujui')
                : __('Permintaan paket custom Anda ditolak'))
            ->greeting(__('Halo :name,', ['name' => $notifiable->name]));

        if ($approved) {
            $subscription = $this->request->organization->currentSubscription();

            return $message
                ->line(__('Permintaan paket custom untuk :organization telah disetujui.', [
                    'organization' => $this->request->organization->name,
                ]))
                ->line(__('Kuota aktif sekarang: :stores toko, :owners akun owner.', [
                    'stores' => $subscription?->effectiveMaxStores() ?? '—',
                    'owners' => $subscription?->effectiveMaxOwners() ?? '—',
                ]))
                ->action(__('Lihat Toko Saya'), url('/settings/organization/stores'));
        }

        return $message
            ->line(__('Mohon maaf, permintaan paket custom untuk :organization belum bisa kami setujui saat ini.', [
                'organization' => $this->request->organization->name,
            ]))
            ->line(__('Silakan hubungi tim kami kalau ada pertanyaan lebih lanjut.'));
    }
}
