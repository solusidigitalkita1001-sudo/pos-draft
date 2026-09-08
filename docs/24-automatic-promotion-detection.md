# Deteksi Otomatis Promosi BXGY dari Keranjang

Menuntaskan item terakhir dari catatan technical debt POS lama.

## Kondisi Sebelumnya

Promosi Buy-X-Get-Y sudah berfungsi, tapi cuma lewat **seleksi manual di
katalog** — kasir harus tahu nama promosinya dan mencarinya sendiri
seperti mencari produk biasa (`item_type = 'promotion'` di keranjang).
Sistem tidak pernah mendeteksi sendiri kalau isi keranjang sebenarnya
sudah memenuhi syarat sebuah promosi.

## Yang Ditambahkan

**`App\Actions\ProductPromotion\EvaluateCartPromotionsAction`** — diberi
isi keranjang saat ini (baris produk biasa), mengembalikan daftar
promosi aktif yang syaratnya sudah terpenuhi beserta **berapa kali**
syaratnya terpenuhi.

Perhitungan "berapa kali" (`times`):
- Untuk tiap syarat (trigger) promosi: `floor(jumlah_di_keranjang / min_quantity)`.
- Kalau promosi butuh LEBIH DARI SATU produk syarat sekaligus (mis. beli
  1 Nasi + 1 Ayam gratis 1 Es Teh), `times` diambil dari trigger yang
  **paling sedikit terpenuhi** — supaya tidak menyarankan promo lebih
  banyak dari yang benar-benar bisa dipenuhi.

**Endpoint** `POST /pos/promotions/evaluate` (`PosController::evaluatePromotions`)
— menerima baris keranjang, mengembalikan promosi yang terdeteksi dalam
bentuk **PosItem yang sama persis** dengan hasil pencarian katalog
(pakai ulang `formatPromotionForPos()` yang sudah ada), plus field
`suggested_quantity`. Ini sengaja disamakan bentuknya supaya frontend
bisa langsung `addToCart()` tanpa transformasi data tambahan.

## Kenapa "Sarankan + Klik" bukan "Auto-Inject Diam-diam"

Promosi yang terdeteksi TIDAK otomatis masuk keranjang tanpa
sepengetahuan kasir. Sebagai gantinya, muncul banner "🎉 Promo
Tersedia" di panel checkout dengan tombol "Terapkan" per promosi.
Alasannya:
- Kasir tetap sadar & mengonfirmasi promosi apa yang diterapkan —
  penting untuk akuntabilitas transaksi (uang & stok sungguhan
  berpindah).
- Menghindari race condition membingungkan kalau kasir SEDANG mengetik
  jumlah produk dan promosi tiba-tiba nambah sendiri di tengah proses.
- Kalau kasir sudah menerapkan promosi itu (ada di keranjang sebagai
  baris `item_type=promotion`), promosi yang sama tidak disarankan lagi
  (dicek lewat `appliedPromotionIds` di `CartPanel`).

Deteksinya sendiri tetap **otomatis** (real-time, di-debounce 320ms
setiap keranjang berubah, sama seperti validasi voucher yang sudah
ada) — kasir tidak perlu mencari nama promosi secara manual lagi, itu
inti perbaikannya.

## Yang TIDAK berubah (sengaja, demi keamanan)

Mekanisme checkout (`CreatePosTransactionAction::buildPromotionLine`)
**sama sekali tidak disentuh**. Begitu kasir klik "Terapkan", promosi
ditambahkan ke keranjang lewat jalur `addToCart()` yang sama persis
dengan seleksi manual — cuma sumber datanya sekarang datang dari hasil
deteksi otomatis, bukan hasil pencarian manual. Validasi stok & harga
final tetap dihitung ulang sepenuhnya di server saat checkout
sungguhan (tidak pernah percaya begitu saja pada hasil deteksi
client-side).

## Yang MASIH belum ada

- Promosi berbasis diskon persentase/nominal langsung (bukan BXGY) —
  `ProductPromotion::TYPE_BXGY` adalah satu-satunya tipe yang ada di
  skema saat ini.
- Highlight visual di keranjang yang menunjukkan produk mana yang
  "memicu" promosi yang sedang disarankan (saat ini cuma nama promosi
  & tombol Terapkan, tanpa menyorot baris produk terkait).
