# Polish SaaS: Role, Resend, Export, Toggle Periode, Cancel Mandiri (Sprint 6)

## 1. Ubah Role Member

`ChangeOrganizationMemberRoleAction` — owner bisa ubah role anggota lain
(`owner` ↔ `manager`) langsung dari dropdown di halaman
`/settings/organization/members`. Sama seperti hapus member, menolak
mengubah role **owner terakhir** jadi manager (organization harus selalu
punya minimal 1 owner).

UI-nya pakai `router.patch()` langsung dari `onValueChange` di komponen
`Select` (bukan submit form biasa), supaya perubahan role langsung
tersimpan begitu dipilih tanpa perlu tombol submit terpisah.

## 2. Resend Invitation

`ResendOrganizationInvitationAction` — refresh `expires_at` (+3 hari
dari sekarang) dan kirim ulang email undangan. Tombol "Kirim Ulang" ada
di sebelah tombol "Batalkan" pada tiap undangan tertunda. Menolak resend
untuk undangan yang sudah diterima.

## 3. Export Invoice (CSV)

`OrganizationInvoiceController::download()` — stream CSV berisi semua
invoice organization, mirror pola `streamDownload` yang sudah dipakai
`ReportController` untuk laporan POS.

⚠️ **Catatan penting soal penamaan**: method ini sengaja diberi nama
`download()`, BUKAN `export()`, dan nama route-nya
`organizations.invoices.download` bukan `...export`. Alasannya: Wayfinder
meng-generate fungsi JavaScript dengan nama persis dari **segmen
terakhir nama route** — kalau route-nya bernama `...export`, hasilnya
adalah `export function export(...)` di file yang di-generate, dan itu
**syntax error** karena `export` adalah reserved keyword di JavaScript.
Ini saya temukan & perbaiki sebelum sempat jadi bug produksi.

Belum ada export PDF — sengaja dilewati supaya tidak menambah dependency
Composer baru (konsisten dengan keputusan di Sprint 3 soal Midtrans).
CSV sudah cukup untuk kebutuhan pembukuan dasar; PDF per-invoice bisa
ditambah belakangan pakai `dompdf`/`barryvdh/laravel-dompdf` kalau perlu.

## 4. Toggle Bulanan/Tahunan di Checkout

Backend sudah siap sejak Sprint 3 (`price_monthly` vs `price_yearly`,
`BillingPeriod` enum). Sprint ini menambahkan toggle button
"Bulanan"/"Tahunan" di atas kartu paket pada halaman Toko Saya — harga
yang ditampilkan dan `billing_period` yang dikirim ke checkout mengikuti
pilihan toggle (sebelumnya hardcode `monthly`).

## 5. Cancel Subscription Mandiri

**Desain penting — cancel EFEKTIF DI AKHIR PERIODE, bukan langsung:**

- `CancelSubscriptionAction` men-set `subscriptions.canceled_at = now()`
  tapi **tidak langsung mengubah `status`**. Akses toko tetap normal
  sampai `current_period_end`.
- Kenapa begini, bukan langsung suspend: kalau pelanggan sudah bayar
  untuk sebulan penuh, memutus akses di tengah jalan itu tidak adil dan
  bisa memicu komplain/refund. Pola ini sama seperti kebanyakan produk
  SaaS (Netflix, Spotify, dst) — cancel = "jangan perpanjang lagi",
  bukan "putus sekarang juga".
- **Pengecualian: plan Trial** — karena tidak ada periode yang sudah
  dibayar, cancel saat trial langsung efektif (status → `canceled`
  seketika).
- `ProcessSubscriptionLifecycleAction` (dari Sprint 4) ditambah step
  baru `finalizeVoluntaryCancellations()` yang MENDAHULUI
  `markPastDue()` — begitu `current_period_end` lewat untuk subscription
  yang `canceled_at`-nya terisi, statusnya langsung jadi `canceled`
  (BUKAN `past_due` lalu grace period lalu `suspended` — itu alur untuk
  gagal bayar, beda konteks dengan pembatalan yang disengaja).
- **Resume**: selama status masih usable (belum difinalisasi oleh job
  di atas), owner bisa klik "Batalkan Pembatalan" kapan saja — cukup
  `canceled_at` di-null-kan lagi (`ResumeSubscriptionAction`).
- **Checkout ulang otomatis meng-clear `canceled_at`** — kalau user yang
  sudah minta cancel ternyata checkout paket lagi sebelum periode
  habis, `HandleMidtransNotificationAction::markPaid()` (Sprint 3)
  sekarang ikut mereset `canceled_at` ke `null`.

Konfirmasi di UI pakai modal (`CancelSubscriptionModal`), bukan tombol
submit langsung — aksi ini cukup konsekuensial untuk butuh konfirmasi
eksplisit, walau tidak sedestruktif hapus toko (makanya tidak perlu
ketik ulang nama seperti modal hapus toko).

## Yang MASIH belum ada

- Export PDF per-invoice (butuh dependency baru, lihat catatan poin 3).
- Downgrade plan (turun dari Ultra ke Premium misalnya) belum ada di
  UI — backend `UpgradePlanAction`/checkout flow secara teknis
  mendukung tapi belum diekspos sebagai tombol "Downgrade" terpisah.
- Preferensi notifikasi per-user (mana yang mau/tidak mau diterima).
