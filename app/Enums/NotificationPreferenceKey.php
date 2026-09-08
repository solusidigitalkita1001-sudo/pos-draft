<?php

namespace App\Enums;

/**
 * Hanya notifikasi "siklus billing" yang bisa dimatikan pengguna —
 * ini yang paling berpotensi dianggap mengganggu/berulang. Notifikasi
 * transaksional (undangan organization/team, hasil review custom plan
 * request milik sendiri) SENGAJA tidak masuk daftar ini dan selalu
 * terkirim — itu bukan "pengingat berkala", tapi hasil langsung dari
 * aksi yang user lakukan sendiri.
 */
enum NotificationPreferenceKey: string
{
    case TrialEndingSoon = 'trial_ending_soon';
    case SubscriptionPastDue = 'subscription_past_due';
    case SubscriptionSuspended = 'subscription_suspended';
    case SubscriptionCanceled = 'subscription_canceled';
    case InvoicePaymentFailed = 'invoice_payment_failed';
    case PlanDowngradeApplied = 'plan_downgrade_applied';
    case PlanDowngradeSkipped = 'plan_downgrade_skipped';
    case CustomPlanRequestSubmitted = 'custom_plan_request_submitted';

    public function label(): string
    {
        return match ($this) {
            self::TrialEndingSoon => 'Pengingat masa trial akan berakhir',
            self::SubscriptionPastDue => 'Pengingat perpanjangan langganan',
            self::SubscriptionSuspended => 'Pemberitahuan akun ditangguhkan',
            self::SubscriptionCanceled => 'Konfirmasi langganan berakhir',
            self::InvoicePaymentFailed => 'Pemberitahuan pembayaran gagal',
            self::PlanDowngradeApplied => 'Konfirmasi downgrade paket diterapkan',
            self::PlanDowngradeSkipped => 'Pemberitahuan downgrade dibatalkan otomatis',
            self::CustomPlanRequestSubmitted => 'Permintaan paket custom baru (admin)',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::TrialEndingSoon => 'Email H-3 sebelum masa trial organization Anda berakhir.',
            self::SubscriptionPastDue => 'Email saat langganan belum diperpanjang setelah periode berakhir.',
            self::SubscriptionSuspended => 'Email saat akses toko dibatasi karena langganan tidak aktif.',
            self::SubscriptionCanceled => 'Email konfirmasi setelah pembatalan langganan selesai diproses.',
            self::InvoicePaymentFailed => 'Email saat pembayaran upgrade paket gagal/ditolak/kedaluwarsa.',
            self::PlanDowngradeApplied => 'Email konfirmasi setelah downgrade paket yang dijadwalkan berhasil diterapkan.',
            self::PlanDowngradeSkipped => 'Email kalau downgrade yang dijadwalkan gagal diterapkan karena kuota tidak muat lagi.',
            self::CustomPlanRequestSubmitted => 'Email ke Anda (sebagai platform admin) setiap ada organization mengajukan paket custom baru.',
        };
    }

    /**
     * @return array<int, self>
     */
    public static function all(): array
    {
        return self::cases();
    }
}
