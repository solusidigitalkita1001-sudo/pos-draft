# Transaction Module

## Transaction Types

- Sale
- Refund
- Return

## Rules

Sale:
- Mengurangi stock

Refund:
- Mengembalikan stock

Return:
- Mengembalikan stock

## Consistency

Semua perubahan stock harus menggunakan:

DB::transaction()