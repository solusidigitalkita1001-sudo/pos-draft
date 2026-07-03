# Transaction Module

## Transaction Types

- Sale
- Refund
- Return

---

## Sale

- Mengurangi stok.
- Membuat riwayat transaksi.

---

## Refund

- Mengembalikan stok.
- Membuat audit log.

---

## Return

- Mengembalikan stok.
- Menyimpan alasan return.

---

## Consistency

Semua perubahan stok harus menggunakan:

DB::transaction()