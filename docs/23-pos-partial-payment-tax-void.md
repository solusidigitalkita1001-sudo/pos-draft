# Perbaikan Technical Debt POS Inti: Partial Payment, Pajak, Void Transaction

Tiga bug lama (bukan dari modul SaaS) yang dikonfirmasi & diperbaiki.

## 1. Partial Payment (sebelumnya benar-benar tidak bisa dipakai)

**Root cause**: diblokir di EMPAT tempat sekaligus, semuanya dengan
aturan yang sama — menolak `paid_amount` kalau kurang dari total:
- `CreatePosTransactionAction::validatePaidAmount()` (backend, checkout awal)
- `ProcessTransactionPaymentAction::validatePaidAmount()` (backend, pelunasan)
- `resources/js/pages/pos/index.tsx` (client-side, checkout awal)
- `resources/js/pages/pos/components/recent-transactions.tsx` (client-side, pelunasan)

Constant `Transaction::PAYMENT_STATUS_PARTIAL` sudah ada sejak awal
tapi tidak pernah bisa dicapai state-nya.

**Perbaikan**:
- `App\Support\PaymentStatusResolver` — logic yang SEBENARNYA SUDAH BENAR
  di `TransactionController` (endpoint edit manual milik owner) sekarang
  di-share ke `CreatePosTransactionAction` & `ProcessTransactionPaymentAction`
  supaya konsisten satu aturan di semua tempat.
- Keempat lokasi di atas sudah tidak lagi menolak partial payment — cuma
  menolak `paid_amount <= 0`.
- `ProcessTransactionPaymentAction` sekarang juga menerima **top-up
  sebagian** (tidak wajib langsung melunasi semua sisa dalam satu kali
  bayar) — cocok dengan UI "Sisa" yang sudah ada tapi sebelumnya jadi
  dead code karena tidak pernah bisa dipicu.
- `CartPanel` (ringkasan checkout) sekarang menampilkan "Sisa Tagihan"
  alih-alih "Kembalian: Rp 0" yang membingungkan saat pembayaran kurang.

## 2. `tax_total` Hardcode 0

**Root cause**: tidak ada infrastruktur pajak sama sekali di aplikasi —
tidak ada kolom, tidak ada setting, `tax_total` selalu di-set `0` di
`CreatePosTransactionAction`.

**Perbaikan**:
- Kolom baru `teams.tax_rate` (persen, decimal, default `0` — toko yang
  tidak pakai pajak TIDAK terpengaruh sama sekali oleh perubahan ini).
- Field "Tarif Pajak (%)" di halaman pengaturan tim (`teams/edit.tsx`).
- `CreatePosTransactionAction` menghitung pajak dari `team.tax_rate`:
  `tax_total = round((subtotal - discount) * tax_rate / 100, 2)`,
  `grand_total = (subtotal - discount) + tax_total`.
- `CartPanel` (ringkasan di POS) menghitung & menampilkan baris "Pajak"
  yang sama persis, supaya kasir melihat total yang identik dengan yang
  akan disimpan server — sebelumnya kalau cuma backend yang diperbaiki
  tanpa ini, kasir akan lihat total yang SALAH (tanpa pajak) sementara
  server menghitung dengan pajak, bikin bingung soal kembalian.

**Belum disentuh**: transaksi manual lewat `TransactionController`
(endpoint edit oleh owner) — di situ owner memang mengetik `grand_total`
sendiri secara manual, jadi tidak otomatis dihitung pajak. Ini
keputusan sadar, bukan kelupaan: endpoint itu untuk koreksi data,
bukan alur jual-beli normal.

## 3. Void Transaction (sebelumnya tidak ada implementasinya sama sekali)

**Root cause**: `Transaction::STATUS_VOID` sudah ada sebagai constant
sejak awal, dicek di beberapa tempat (guard di `ProcessTransactionPaymentAction`,
counter di dashboard), TAPI tidak ada satupun action/endpoint yang
benar-benar men-set status ini.

**Perbaikan** — `App\Actions\Pos\VoidTransactionAction`:
- Set `status = void`, catat `void_reason`, `voided_at`, `voided_by`
  (kolom baru).
- **Mengembalikan stok dengan presisi**, bukan menghitung ulang dari
  `transaction_items` — item paket/promosi punya SATU baris transaksi
  yang mempengaruhi BEBERAPA produk sekaligus, jadi baris item saja
  tidak cukup untuk tahu persis produk mana & berapa banyak yang harus
  dikembalikan. Solusinya: replay `ProductStockMovement` (dicocokkan
  lewat `reference_type`/`reference_id` yang sudah menunjuk ke
  transaksi ini sejak awal) — sumber data yang PALING presisi karena
  itu memang catatan asli tiap pergerakan stok saat checkout.
- Menolak void ganda (transaksi yang sudah void tidak bisa di-void lagi).
- Produk yang sudah dihapus sejak transaksi dibuat dilewati dengan aman
  (tidak error, cuma tidak ada stok yang dikembalikan untuk produk itu).
- **Hanya owner** yang boleh void (dicek di controller, konsisten dengan
  pola `TransactionController::destroy` yang sudah ada).

**UI**: tombol "Batalkan Transaksi" + input alasan (opsional) muncul di
detail transaksi pada `recent-transactions.tsx`, dengan konfirmasi
`window.confirm()` sebelum submit. Transaksi yang sudah void
menampilkan badge merah "Dibatalkan" dan alasannya, form pelunasan
disembunyikan.

## Yang MASIH belum disentuh

- **Promotion engine masih evaluasi manual** — belum diselidiki di
  putaran ini, technical debt masih ada.
- Tidak ada UI khusus untuk melihat riwayat void (siapa membatalkan apa
  kapan) di luar detail transaksi individual — untuk laporan lebih
  lengkap, bisa query `transactions` dengan `status = void` +
  join `voided_by`.
