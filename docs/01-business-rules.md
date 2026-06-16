# Business Rules

## Team Isolation

Data antar team harus terpisah.

User hanya dapat mengakses data team aktif.

## Product

Product wajib memiliki:

- Team
- Category
- Stock

## Transaction

Transaction wajib:

- Memiliki minimal 1 item
- Memiliki total > 0
- Mengurangi stock

## Refund

Refund:

- Mengembalikan stock
- Membuat audit log

## Return

Return:

- Mengembalikan stock
- Mencatat alasan return

## Voucher

Voucher:

- Harus aktif
- Belum expired
- Belum mencapai usage limit