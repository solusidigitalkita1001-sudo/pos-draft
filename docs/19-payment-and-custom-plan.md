# Payment & Custom Plan Module (Sprint 3)

## Payment Gateway — Midtrans (Snap)

Dipakai HANYA untuk billing SaaS (upgrade paket organization), bukan
untuk transaksi POS di toko. Diimplementasikan lewat `Http` facade
(`App\Services\Midtrans\MidtransClient`), **bukan** package
`midtrans/midtrans-php` — supaya tidak nambah dependency Composer.

### Alur Checkout

1. User klik "Pilih Paket" di halaman Toko Saya (plan basic/premium/ultra saja — bukan custom).
2. `CheckoutController@store` → `CreateCheckoutInvoiceAction` → bikin baris `subscription_invoices` (status `pending`) + minta Snap token ke Midtrans.
3. Redirect ke `CheckoutController@show` → halaman React `organizations/checkout.tsx` yang load `snap.js` dan buka popup pembayaran Midtrans.
4. User bayar di popup Midtrans.
5. Midtrans memanggil `POST /webhooks/midtrans` (server-to-server, di luar sesi user) → `MidtransWebhookController` → `HandleMidtransNotificationAction`.
6. **Hanya di webhook inilah** plan organization benar-benar diaktifkan — callback `onSuccess` di popup Snap.js **tidak** dipakai untuk mengubah data apa pun, cuma untuk mengarahkan user kembali ke halaman Toko Saya. Ini penting: callback client-side bisa dipalsukan/dilewati, webhook (dengan verifikasi signature) tidak.

### Keamanan Webhook

- Setiap payload yang masuk **selalu** dicatat ke `payment_webhook_logs` (audit trail), termasuk yang signature-nya tidak valid.
- Signature diverifikasi: `SHA512(order_id + status_code + gross_amount + ServerKey)`.
- Idempotent: invoice yang statusnya sudah bukan `pending` (misal sudah `paid`) tidak diproses ulang meskipun Midtrans mengirim notifikasi yang sama berkali-kali (Midtrans memang retry sampai dapat response 200).
- Endpoint `webhooks/midtrans` dikecualikan dari CSRF (lihat `bootstrap/app.php`) karena Midtrans tidak punya session/CSRF token — keamanannya murni dari verifikasi signature.
- Endpoint ini **selalu balas HTTP 200** (bahkan untuk payload yang gagal diproses) supaya Midtrans berhenti retry. Semua kegagalan dicatat ke Laravel log, bukan bikin request gagal.

### Setup

```bash
# .env
MIDTRANS_SERVER_KEY=SB-Mid-server-xxxxxxxxxxxxx
MIDTRANS_CLIENT_KEY=SB-Mid-client-xxxxxxxxxxxxx
MIDTRANS_IS_PRODUCTION=false
```

Ambil kredensial dari dashboard.midtrans.com (Settings > Access Keys).
**Gunakan sandbox dulu** sampai benar-benar siap production.

Set URL notifikasi di dashboard Midtrans (Settings > Configuration >
Payment Notification URL) ke:
```
https://domain-kamu.test/webhooks/midtrans
```

## Custom Plan Request Flow

Paket `custom` ("1 akun owner atau lebih, bisa request max toko") TIDAK
bisa self-service — sesuai definisi awal, harus dikoordinasikan manual:

1. Owner organization isi form di `/settings/organization/custom-plan-request` (`RequestCustomPlanAction`) → status `pending`.
2. Kamu (platform admin) buka `/admin/custom-plan-requests`, review permintaan lintas-organization.
3. Approve (`ReviewCustomPlanRequestAction::approve`) — kamu bisa set kuota FINAL yang berbeda dari yang diminta user. Ini akan:
   - Ganti `subscriptions.plan_id` organization tersebut ke Plan `custom` yang sama (satu row `plans` dipakai bareng semua pelanggan custom).
   - Set `max_stores_override` / `max_owners_override` di `subscriptions` sesuai yang kamu approve.
   - Menolak approval kalau kuota yang di-approve lebih kecil dari jumlah toko yang sudah ada.
4. Atau Reject (`ReviewCustomPlanRequestAction::reject`) — tidak mengubah subscription sama sekali.

### Admin Panel Access

Sengaja **tidak** dibangun di atas sistem role Spatie yang team-scoped
(karena itu didesain untuk 1 team, bukan lintas-tenant). Dipakai flag
sederhana `users.is_platform_admin`, dijaga oleh middleware
`EnsurePlatformAdmin`.

```bash
php artisan platform-admin:grant admin@kamu.test
```

⚠️ `is_platform_admin` SENGAJA tidak dimasukkan ke `#[Fillable(...)]`
User — supaya field ini tidak mungkin bisa di-mass-assign lewat request
HTTP manapun. Command di atas pakai `forceFill()`.

## Yang MASIH belum ada (technical debt untuk sprint selanjutnya)

- **Toggle bulanan/tahunan di UI.** `BillingPeriod` sudah didukung penuh
  di backend (`price_monthly` vs `price_yearly`), tapi tombol "Pilih
  Paket" di halaman Toko Saya saat ini hardcode `monthly`. Tinggal
  tambah toggle di UI dan kirim `billing_period` sesuai pilihan.
- **Downgrade / cancel subscription** dari sisi user belum ada UI-nya
  (`UpgradePlanAction` dari Sprint 2 masih ada di backend untuk
  keperluan admin/instant-switch tanpa pembayaran, tapi tidak lagi
  dipakai tombol "Pilih Paket" di UI — itu sekarang lewat checkout).
- **Retry/renewal otomatis.** Belum ada job terjadwal untuk subscription
  yang `current_period_end`-nya lewat (harusnya jadi `past_due` lalu
  `suspended` kalau tidak diperpanjang). Ini penting sebelum production.
- **Notifikasi email** — trial mau habis, pembayaran gagal, request
  custom plan di-approve/reject. Belum ada sama sekali.
- **Invoice history / receipt page** untuk user — `subscription_invoices`
  sudah dicatat lengkap di database, tapi belum ada halaman React untuk
  user melihat riwayat invoice-nya sendiri.
- Multi-owner untuk plan custom (`max_owners_override` sudah ada,
  approval sudah menyimpannya) tapi belum ada UI untuk owner
  meng-invite owner kedua ke organization-nya.
