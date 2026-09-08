# Organization Module (SaaS Billing Layer)

## Purpose

Memisahkan konsep "akun pelanggan" (Organization) dari "toko" (Team).

Satu Organization = satu pelanggan yang berlangganan.
Satu Organization bisa punya banyak Team (toko), sesuai kuota paketnya.

## Relasi

```
Organization 1 ── N Team (toko)
Organization 1 ── N OrganizationMembership ── N User (akun owner/manager)
Organization 1 ── N Subscription (histori langganan)
Subscription  N ── 1 Plan
```

## Plan (Paket)

| Code    | max_stores | max_owners | is_custom |
|---------|-----------:|-----------:|-----------|
| basic   | 1          | 1          | false     |
| premium | 3          | 1          | false     |
| ultra   | 7          | 1          | false     |
| custom  | null*      | null*      | true      |

\* Untuk plan `custom`, kuota efektif diambil dari
`subscriptions.max_stores_override` / `max_owners_override`
(lihat `Subscription::effectiveMaxStores()` / `effectiveMaxOwners()`),
bukan dari `plans.max_stores`. Ini supaya tiap pelanggan custom bisa
punya kuota berbeda meskipun sama-sama di plan `custom`.

## Subscription Status

- `trial` — masa coba, dibatasi `trial_ends_at`
- `active` — berlangganan aktif
- `past_due` — telat bayar, akses masih boleh jalan (grace period)
- `suspended` — akses dikunci (baca `SubscriptionStatus::isUsable()`)
- `canceled` — berhenti berlangganan

## Yang BELUM ada di modul ini (di luar scope Sprint 1)

- Enforcement kuota saat create Team (`TeamPolicy::create()` masih `true` tanpa syarat)
- Controller/route/UI React untuk organization
- Integrasi payment gateway & invoice
- Job terjadwal untuk expire trial / cek jatuh tempo

Ini akan masuk di Sprint 2 dan seterusnya.

## Sprint 2 — Quota Enforcement & UI

Ditambahkan di Sprint 2:

- `users.current_organization_id` — mirror dari `current_team_id`, lewat trait `HasOrganizations` (mirror `HasTeams`).
- `CreateTeam::handle()` sekarang **wajib** menerima `Organization` dan melempar `StoreQuotaExceededException` kalau `organization->teams()->count() >= subscription->effectiveMaxStores()`. Row organization di-lock (`lockForUpdate()`) selama pengecekan supaya dua request "buat toko" yang barengan tidak sama-sama lolos.
- `TeamPolicy::create(User $user, ?Organization $organization)` — memisahkan **otorisasi** (apakah user owner/manager dari organization ini) dari **kuota** (business rule, ditangani di Action, bukan Policy).
- `TeamController::store()` sekarang resolve `$user->currentOrganization`, `Gate::authorize('create', [Team::class, $organization])`, lalu tangani `StoreQuotaExceededException` dengan redirect + flash toast ke halaman "Toko Saya".
- `CreateNewUser` (Fortify) dan `RegisterResponse` diwire supaya **setiap user baru otomatis dapat Organization** (plan Basic, trial 14 hari) sebelum toko pertamanya dibuat — tanpa ini, quota enforcement di atas akan mem-block semua user baru karena `currentOrganization` mereka `null`.
- `UserFactory` diupdate supaya setiap `User::factory()->create()` juga otomatis dapat Organization + Plan (quota longgar, `max_stores: 10`) + Subscription aktif, supaya seluruh test suite yang sudah ada tidak rusak oleh perubahan ini. Test yang secara spesifik menguji kuota membuat Organization/Plan/Subscription sendiri (lihat `tests/Feature/Organizations/StoreQuotaTest.php`), tidak bergantung pada default factory ini.
- `OrganizationController@stores` — halaman **"Toko Saya"**: daftar toko di bawah organization user, progress bar kuota, dan kartu tiap paket (basic/premium/ultra/custom) untuk upgrade.
- `OrganizationController@upgrade` + `UpgradePlanAction` — ganti plan organization secara self-service (**belum ada payment gateway** — itu Sprint 3). Menolak downgrade ke plan yang kuotanya lebih kecil dari jumlah toko yang sudah ada. Plan `custom` ditolak lewat self-service (harus lewat tim sales / admin, lihat `UpgradeOrganizationPlanRequest`).
- Route baru: `GET/POST settings/organization/stores` dan `settings/organization/upgrade` (nama route: `organizations.stores`, `organizations.upgrade`), plus nav item "Billing" di `settings/layout.tsx`.

### Yang MASIH belum ada (menunggu Sprint 3+)

- Payment gateway (Midtrans/Xendit) — upgrade plan saat ini instan tanpa pembayaran, cocok untuk basic/premium/ultra versi awal tapi belum production-ready untuk billing sungguhan.
- Downgrade plan dari UI (Action `UpgradePlanAction` sebenarnya sudah mendukung "downgrade" juga selama kuota muat, tapi UI kartu paket saat ini hanya expose "Pilih Paket" untuk plan yang belum aktif — belum ada halaman "cancel subscription").
- Multi-owner untuk plan custom (`max_owners` sudah ada di skema, tapi belum ada UI untuk invite owner kedua ke organization).
- Notifikasi email saat trial mau habis / gagal bayar.
- Halaman admin internal untuk approve/atur kuota custom plan secara manual.

## Backfill Data Lama

Jalankan setelah migration:

```
php artisan organizations:backfill
```

Perintah ini mengelompokkan Team lama berdasarkan owner-nya (1 user bisa
sudah punya beberapa toko di sistem lama), membuatkan 1 Organization per
owner, memilih plan yang cukup untuk jumlah toko yang sudah mereka punya
(basic/premium/ultra/custom), lalu men-set subscription-nya `active`
(bukan trial, karena mereka pelanggan lama).
