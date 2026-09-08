# Downgrade Plan Sebagai Tombol Terpisah di UI

Melengkapi Sprint 3 (checkout untuk upgrade) — sekarang turun paket
(mis. Ultra → Premium) punya alur sendiri yang terpisah dari upgrade,
tanpa lewat pembayaran.

## Kenapa Downgrade Butuh Alur Berbeda dari Upgrade

- **Upgrade** = butuh kuota LEBIH BESAR sekarang juga → wajar minta
  bayar dulu (lewat checkout Midtrans, Sprint 3), baru kuota bertambah.
- **Downgrade** = organization SUDAH BAYAR untuk periode saat ini di
  paket yang lebih besar. Memutus kuota besar itu di tengah jalan
  tidak adil buat pelanggan yang sudah bayar. Jadi downgrade **tidak
  langsung berlaku** — dijadwalkan, baru diterapkan di akhir periode
  yang sedang berjalan, TANPA perlu bayar apa-apa (karena memang
  turun, bukan naik).

## Desain: `pending_plan_id`

Mirror persis pola `canceled_at` yang sudah ada untuk voluntary
cancellation (Sprint 6):

1. **`RequestPlanDowngradeAction`** — owner pilih paket lebih kecil →
   `subscriptions.pending_plan_id` diisi. Plan AKTIF (`plan_id`) TIDAK
   berubah sama sekali saat ini.
   - Menolak kalau target BUKAN downgrade (dibandingkan lewat
     `plans.sort_order` — basic=1, premium=2, ultra=3, custom=4).
   - Menolak plan `custom` (itu tetap lewat alur request manual).
   - Menolak kalau jumlah toko SAAT INI sudah melebihi kuota target —
     ini cuma peringatan dini, pengecekan yang SEBENARNYA menentukan
     terjadi lagi saat downgrade difinalisasi (lihat poin 3).

2. **`CancelPlanDowngradeAction`** — owner berubah pikiran sebelum
   periode habis → `pending_plan_id` di-null-kan, tidak ada yang
   berubah.

3. **`ProcessSubscriptionLifecycleAction::finalizePendingDowngrades()`**
   (job harian, Sprint 4) — begitu `current_period_end` lewat:
   - Cek ULANG kuota toko (bisa saja jumlah toko sudah bertambah sejak
     downgrade diminta). Kalau masih muat → plan resmi berubah.
   - Kalau SUDAH TIDAK MUAT → downgrade **dibatalkan otomatis**
     (bukan di-retry terus-menerus), owner dapat email penjelasan
     (`PlanDowngradeSkippedNotification`), plan tetap seperti semula.
   - Kalau berhasil diterapkan, owner dapat email konfirmasi
     (`PlanDowngradeAppliedNotification`).

## Interaksi dengan Fitur Lain (supaya tidak saling bertabrakan)

- **Cancel subscription** (Sprint 6) sekarang ikut membersihkan
  `pending_plan_id` — kalau organization dibatalkan sepenuhnya, tidak
  ada gunanya masih "menjadwalkan downgrade" ke plan lain.
- **Request downgrade** sebaliknya ikut membersihkan `canceled_at` —
  kalau owner sebelumnya sempat cancel tapi berubah pikiran jadi mau
  downgrade saja (bukan berhenti total), ini otomatis membatalkan
  pembatalannya.
- **Checkout sukses** (upgrade, Sprint 3) ikut membersihkan
  `pending_plan_id` — kalau owner beli paket baru lewat checkout,
  downgrade lama yang masih terjadwal jadi tidak relevan lagi.

## UI

Di halaman Toko Saya (`stores.tsx`):
- Kartu paket yang levelnya LEBIH RENDAH dari paket aktif menampilkan
  tombol **"Downgrade"** (bukan "Pilih Paket") — klik langsung
  memproses (dengan konfirmasi `window.confirm()`), TIDAK membuka
  popup pembayaran Midtrans sama sekali.
- Kalau downgrade ke plan tersebut sudah terjadwal, tombolnya berubah
  jadi **"Downgrade Terjadwal"** (disabled) supaya tidak submit ganda.
- Banner kuning di atas daftar paket menampilkan downgrade yang sedang
  terjadwal + tanggal berlakunya + tombol "Batalkan Downgrade".

## ⚠️ Pelajaran dari sprint sebelumnya yang saya terapkan lagi

Sempat mau menamai route `organizations.subscription.cancel-downgrade`
— tapi segmen TERAKHIR nama route itu jadi nama fungsi JavaScript yang
di-generate Wayfinder, dan **hyphen tidak valid dalam nama fungsi
JavaScript**. Sama persis pola masalah `export` di Sprint 6. Sudah
saya ganti jadi `organizations.subscription.cancelDowngrade`
(camelCase, tanpa hyphen) sebelum sempat jadi bug.

## Yang MASIH belum ada

- Prorata (potongan harga proporsional) untuk upgrade di tengah
  periode — saat ini upgrade selalu bayar harga penuh paket baru,
  tidak ada pengurangan berdasarkan sisa waktu paket lama.
- Preview "downgrade akan mempengaruhi toko mana" — kalau nanti toko
  bertambah sebelum downgrade difinalisasi dan jadi tidak muat,
  organization cuma dapat notifikasi setelah downgrade dibatalkan
  otomatis, bukan peringatan proaktif sebelumnya.
