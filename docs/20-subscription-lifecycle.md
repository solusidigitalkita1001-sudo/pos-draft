# Subscription Lifecycle & Notifications (Sprint 4)

## Alur Status

```
trial ──(habis, tanpa upgrade)──────────────► suspended
active ──(current_period_end lewat)────────► past_due ──(3 hari grace)──► suspended
                                                  │
                                    (bayar via checkout, status manapun)
                                                  ▼
                                               active
```

- **Grace period**: 3 hari. Organization `past_due` MASIH bisa pakai toko
  (`SubscriptionStatus::isUsable()` return `true` untuk `past_due`) —
  cuma dapat email pengingat. Baru diblokir setelah 3 hari tanpa
  perpanjangan.
- **Suspended/canceled** = akses toko diblokir. Lihat bagian
  "Enforcement" di bawah.
- Bayar lewat checkout kapan saja (status apa pun) langsung
  mengembalikan subscription ke `active` — lihat
  `HandleMidtransNotificationAction::markPaid()` dari Sprint 3.

## Job Terjadwal

`php artisan subscriptions:process-lifecycle` — dijalankan `ProcessSubscriptionLifecycleAction`, terdaftar di `routes/console.php` untuk jalan **setiap hari jam 01:00**.

Yang dikerjakan tiap run:
1. **Reminder trial** — kirim email H-3 sebelum `trial_ends_at`, SEKALI SAJA (ditandai lewat kolom baru `subscriptions.trial_reminder_sent_at`, supaya job yang jalan berkali-kali tidak spam).
2. **Trial habis** → `suspended` + email.
3. **Active lewat `current_period_end`** → `past_due` + email (masih bisa dipakai, ini baru peringatan).
4. **Past_due lewat grace period (3 hari)** → `suspended` + email.

### Supaya job ini benar-benar jalan

Laravel Scheduler butuh SATU cron job di server yang jalan tiap menit:
```bash
* * * * * cd /path-ke-project && php artisan schedule:run >> /dev/null 2>&1
```
Di Laravel Herd (Windows), aktifkan lewat **Herd > Scheduler** untuk site ini — tanpa itu, `dailyAt('01:00')` di atas TIDAK AKAN PERNAH jalan sendiri.

## Enforcement — Blokir Akses Toko

**Temuan penting**: `EnsureTeamMembership` middleware (yang menjaga semua route `{current_team}/...`) di kode aslinya:
1. Tidak pernah cek status subscription sama sekali.
2. Punya fallback `createPersonalTeam()` yang bikin Team BARU **tanpa organization_id** kalau user somehow tidak punya team — bug yang sama seperti yang diperbaiki di `RegisterResponse.php` (Sprint 2), tapi independen dan belum ketahuan sampai sprint ini.

Keduanya diperbaiki di sprint ini:
- Fallback pembuatan team sekarang **selalu** lewat `CreateOrganizationAction` + `CreateTeam`, tidak mungkin lagi ada team tanpa organization.
- Middleware sekarang cek `$team->organization->currentSubscription()->isUsable()` — kalau `false` (suspended/canceled), user di-redirect ke `/settings/organization/stores` dengan pesan error, SEBELUM diizinkan masuk ke halaman toko manapun (dashboard, POS, laporan, dll — semuanya di bawah prefix `{current_team}`).

## Notifikasi Email

Semua notification class ada di `app/Notifications/Organizations/`, pakai `ShouldQueue` (butuh queue worker jalan — `QUEUE_CONNECTION=database` sudah default dari awal project ini, jalankan `php artisan queue:work`):

| Notification | Dikirim ke | Kapan |
|---|---|---|
| `TrialEndingSoonNotification` | Owner organization | H-3 sebelum trial habis |
| `SubscriptionPastDueNotification` | Owner organization | Begitu masuk grace period |
| `SubscriptionSuspendedNotification` | Owner organization | Trial habis ATAU grace period lewat |
| `InvoicePaymentFailedNotification` | Owner organization | Webhook Midtrans: `deny`/`cancel`/`expire` |
| `CustomPlanRequestSubmittedNotification` | SEMUA `is_platform_admin` | Owner submit request custom plan |
| `CustomPlanRequestReviewedNotification` | User yang request | Admin approve/reject |

Semua lewat channel `mail` (+ `database` khusus untuk notifikasi ke
admin, supaya muncul juga di dalam aplikasi, bukan cuma email).

**Belum dikonfigurasi**: `.env.example` masih `MAIL_MAILER=log` (email
masuk ke log file, tidak benar-benar terkirim). Ganti ke SMTP/provider
email sungguhan sebelum production.

## Yang MASIH belum ada

- **Notifikasi berhenti setelah suspended.** Sekali organization
  `suspended`, tidak ada reminder susulan (misal tiap minggu) — cuma
  sekali saat transisi. Kalau mau lebih persisten, bisa tambah reminder
  berkala untuk yang sudah suspended.
- **Halaman preferensi notifikasi** — owner belum bisa atur mana yang
  mau/tidak mau diterima.
- **Auto-retry pembayaran** — kalau invoice `failed`/`expired`, user
  harus manual checkout ulang, belum ada dunning/retry otomatis.
- **Cancel subscription oleh user sendiri** — belum ada endpoint untuk
  user membatalkan langganannya sendiri (voluntary cancel, beda dari
  suspend karena telat bayar).
