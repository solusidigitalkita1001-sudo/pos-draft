# Preferensi Notifikasi Per-User

Item terakhir dari seluruh catatan technical debt & pengembangan yang
kita susun sejak review awal. Dengan ini, semua item selesai.

## Desain: Opt-Out, Bukan Opt-In

`users.notification_preferences` (JSON, nullable). Kalau kolomnya
`null` ATAU sebuah key belum pernah di-set, **dianggap enabled**
(`User::wantsNotification()` default `true`). Ini penting: kalau
sebaliknya (default `false`/opt-in), semua user LAMA yang belum pernah
buka halaman preferensi akan tiba-tiba berhenti dapat email penting
begitu fitur ini di-deploy — itu regresi, bukan fitur.

## Mana yang Bisa Dimatikan, Mana yang Tidak

**Toggleable** (8 jenis, semuanya notifikasi "siklus billing" yang
berpotensi dianggap berulang/mengganggu):
- Trial akan berakhir
- Perpanjangan langganan diperlukan (past due)
- Akun ditangguhkan (suspended)
- Langganan berakhir (canceled)
- Pembayaran gagal
- Downgrade diterapkan
- Downgrade dibatalkan otomatis
- Permintaan paket custom baru (khusus platform admin)

**SELALU terkirim, TIDAK toggleable**:
- Undangan organization/team (`OrganizationInvitationNotification`,
  `TeamInvitation`) — kalau ini bisa dimatikan, orang yang diundang
  tidak akan pernah tahu mereka diundang.
- Hasil review permintaan custom plan milik sendiri
  (`CustomPlanRequestReviewedNotification`) — ini hasil langsung dari
  aksi yang user lakukan sendiri, bukan pengingat berkala.

Alasan pemisahan ini: yang toggleable adalah notifikasi yang sifatnya
**pemberitahuan/pengingat** (bisa terjadi berulang, tidak selalu perlu
aksi segera). Yang tidak toggleable adalah notifikasi **transaksional**
(hasil langsung dari satu aksi spesifik, jarang terjadi, dan penting
untuk fungsi dasar aplikasi).

## Implementasi Teknis

- `App\Enums\NotificationPreferenceKey` — daftar 8 key + `label()` +
  `description()` untuk ditampilkan di UI.
- `User::wantsNotification(NotificationPreferenceKey $key): bool`.
- Tiap notification class yang toggleable, method `via()`-nya diubah
  dari `return ['mail'];` jadi
  `return $notifiable->wantsNotification(NotificationPreferenceKey::X) ? ['mail'] : [];`
  — TIDAK ada perubahan di titik pemanggilan `->notify()` manapun
  (Action classes yang sudah ada semuanya tetap sama), jadi risiko
  regresi minimal.
- Pengecualian: `CustomPlanRequestSubmittedNotification` punya 2
  channel (`mail` + `database`). Cuma `mail` yang toggleable — channel
  `database` (catatan in-app untuk to-do list admin) selalu ada,
  karena ini actionable item untuk platform admin, bukan sekadar FYI.

## UI

Halaman baru `/settings/notifications` — daftar 8 toggle dengan
label + deskripsi, **auto-save per toggle** (langsung tersimpan begitu
diklik, tidak perlu tombol "Simpan" terpisah — mirror pola ubah role
member di Sprint 5). Kalau request gagal, toggle otomatis kembali ke
posisi semula (optimistic update dengan rollback).

## Komponen UI Baru

`resources/js/components/ui/switch.tsx` — belum ada sebelumnya di
project. Dibuat menggunakan package `radix-ui` (meta-package yang
sudah terpasang, membundel semua primitive Radix termasuk Switch) —
**TIDAK perlu install package baru** (`@radix-ui/react-switch` tidak
ditambahkan secara terpisah, cukup pakai yang sudah ada).

## Yang MASIH belum ada

- Preferensi channel selain email (in-app notification center yang
  lebih lengkap, push notification, dst) — saat ini cuma on/off untuk
  email.
- Preferensi per-organization (kalau user jadi anggota banyak
  organization, saat ini preferensinya global untuk semua, belum bisa
  beda-beda per organization).
