# Multi-Owner & Invoice History (Sprint 5)

## Multi-Owner Organization

Melengkapi definisi paket `custom` ("1 akun owner atau lebih"). Sebelum
sprint ini, `organization_members` sudah mendukung banyak baris secara
skema, tapi tidak ada cara untuk MENAMBAH member kedua lewat UI.

Alurnya identik dengan undangan tim (`team_invitations`), cuma di
level organization:

1. Owner buka `/settings/organization/members` → isi email + pilih peran (`owner`/`manager`) → `InviteOrganizationMemberAction`.
2. Email undangan terkirim (`OrganizationInvitationNotification`) berisi link `/organization-invitations/{code}/accept`.
3. User yang diundang klik link (harus login/register dulu) → `AcceptOrganizationInvitationAction` → jadi member organization tersebut, `current_organization_id`-nya otomatis pindah ke organization ini.

### Kuota `max_owners`

Basic/Premium/Ultra semuanya `max_owners = 1` (dari `PlanSeeder`) — jadi
di paket-paket ini, mengundang member kedua akan selalu ditolak. Ini
memang benar sesuai definisi paket: multi-owner hanya relevan untuk
plan `custom`.

**Dicek di DUA titik, bukan cuma satu** — ini penting:
- **Saat invite** (`InviteOrganizationMemberAction`) — supaya owner
  langsung dapat feedback kalau kuotanya sudah penuh, tanpa perlu
  nunggu orang yang diundang mencoba accept.
- **Saat accept** (`AcceptOrganizationInvitationAction`) — INI YANG
  OTORITATIF. Kenapa perlu dicek dua kali: jarak waktu antara invite
  dan accept bisa lama, dan kuota bisa berubah di antaranya (misalnya
  organization di-downgrade, atau member lain sudah accept undangan
  lain duluan). Kalau cuma dicek saat invite, owner bisa mengundang
  banyak orang sekaligus padahal cuma muat 1, dan yang accept
  belakangan akan lolos padahal kuota sudah penuh.

### Menghapus Member

`RemoveOrganizationMemberAction` menolak menghapus **owner terakhir** —
sebuah organization harus selalu punya minimal 1 owner, kalau tidak
tidak ada yang bisa mengelola billing/toko-nya lagi.

## Riwayat Invoice

Halaman baru `/settings/organization/invoices` — daftar semua
`subscription_invoices` milik organization user saat ini (terbaru
dulu), dengan status badge (pending/paid/failed/expired/canceled).

Datanya sudah lengkap tersimpan sejak Sprint 3 (`CreateCheckoutInvoiceAction`), sprint ini cuma menambahkan halaman untuk melihatnya — tidak ada perubahan skema database.

## Komponen UI Baru

- `resources/js/components/ui/table.tsx` — belum ada di project
  sebelumnya, dibuat mengikuti konvensi shadcn/ui yang sama dengan
  komponen lain (`card.tsx`, `badge.tsx`, dst).

## Yang MASIH belum ada

- **Ubah role member** (owner ↔ manager) setelah bergabung — saat ini
  cuma bisa invite dengan role tertentu atau remove, belum ada "edit role".
- **Resend invitation** untuk undangan yang belum diterima.
- **Export invoice** (PDF/CSV) untuk keperluan pembukuan pelanggan.
- **Filter/pencarian** di halaman riwayat invoice (saat ini cuma daftar
  polos, belum masalah untuk pelanggan baru tapi bisa panjang untuk
  yang sudah lama berlangganan).
