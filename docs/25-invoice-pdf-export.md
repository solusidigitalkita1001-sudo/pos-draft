# Export PDF Invoice

Melengkapi Sprint 3 (invoice tersimpan) dan Sprint 6 (export CSV) —
sekarang tiap invoice juga bisa diunduh sebagai PDF per-dokumen
(kuitansi/invoice), bukan cuma daftar CSV gabungan.

## Kenapa PAKAI dependency baru (beda dari keputusan Midtrans)

Di Sprint 3, integrasi Midtrans sengaja dibuat TANPA package tambahan
karena Midtrans punya REST API yang bisa dipanggil langsung lewat
`Http` facade. **Render HTML jadi PDF tidak punya jalan pintas
semacam itu** — butuh benar-benar ada renderer PDF di server. Jadi di
sini saya pakai `dompdf/dompdf`:

- Pure PHP, TIDAK butuh binary/dependency sistem eksternal (beda
  dengan `wkhtmltopdf` atau Puppeteer yang butuh Chromium) — cocok
  untuk hosting shared/managed yang terbatas aksesnya.
- Dipakai LANGSUNG (`Dompdf\Dompdf`), bukan lewat package Laravel
  wrapper (`barryvdh/laravel-dompdf`) — supaya tidak ada config
  tambahan (`config/dompdf.php`, service provider, facade) untuk
  sesuatu yang cuma dipakai di SATU tempat
  (`App\Services\Pdf\SubscriptionInvoicePdfGenerator`).

## ⚠️ WAJIB dijalankan sebelum fitur ini berfungsi

```bash
composer require dompdf/dompdf
```

Saya sudah tambahkan entry-nya ke `composer.json` (`"dompdf/dompdf": "^3.1"`),
tapi **tidak bisa menjalankan `composer install` dari sandbox saya** —
kamu perlu jalankan sendiri (`composer update` atau `composer install`)
di project asli sebelum test/fitur ini bisa jalan.

## Yang Ditambahkan

- `App\Services\Pdf\SubscriptionInvoicePdfGenerator` — render
  `resources/views/pdf/subscription-invoice.blade.php` ke HTML, lalu
  ke PDF lewat Dompdf. A4, satu halaman, berisi: nama organization,
  order ID, tanggal dibuat/dibayar, nama paket, periode, jumlah, badge
  status berwarna (hijau=lunas, kuning=pending, merah=lainnya).
- `OrganizationInvoiceController::pdf()` — endpoint baru
  `GET /settings/organization/invoices/{order_id}/pdf`, hanya bisa
  diakses untuk invoice milik organization user yang login (404 kalau
  bukan — bukan 403, supaya tidak bocor informasi bahwa order_id itu
  ada di organization lain).
- Tombol ikon PDF di setiap baris tabel pada `organizations/invoices.tsx`.

## Kenapa nama route-nya `pdf`, bukan yang lain

Belajar dari insiden Sprint 6 (`export` adalah reserved word JS yang
merusak file Wayfinder) — dicek dulu: `pdf` BUKAN reserved word
JavaScript, aman dipakai sebagai nama route/method langsung.

## Yang MASIH belum ada

- Kop surat/logo custom per-organization (saat ini nama aplikasi dari
  `config('app.name')`, generik untuk semua toko).
- Export SEMUA invoice sebagai satu PDF gabungan (saat ini cuma
  per-invoice; CSV dari Sprint 6 sudah menutupi kebutuhan "lihat semua
  sekaligus").
- Watermark/nomor seri anti-duplikasi untuk kebutuhan akuntansi formal.
