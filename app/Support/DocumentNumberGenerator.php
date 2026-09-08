<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Menghasilkan nomor dokumen berurutan per scope (team/organization) dan per hari.
 *
 * Format: {PREFIX}-{YYYYMMDD}-{0001}
 *
 * Contoh penggunaan:
 *   DocumentNumberGenerator::generate('POS', 'transactions', 'invoice_number', $team->id);
 *   DocumentNumberGenerator::generate('RFN', 'transaction_refunds', 'refund_number', $team->id);
 *   DocumentNumberGenerator::generate('RTN', 'transaction_returns', 'return_number', $team->id);
 *   DocumentNumberGenerator::generate('TRX', 'transactions', 'invoice_number', $team->id);
 *   DocumentNumberGenerator::generate('SUB', 'subscription_invoices', 'order_id', $organization->id, scopeColumn: 'organization_id');
 *
 * CATATAN: Metode ini rentan terhadap race condition pada traffic sangat tinggi.
 * Untuk skala besar, pertimbangkan menggunakan sequence database atau Redis INCR.
 * Tambahkan UNIQUE INDEX pada kolom nomor dokumen + tangkap UniqueConstraintViolationException
 * untuk retry otomatis.
 */
class DocumentNumberGenerator
{
    public static function generate(
        string $prefix,
        string $table,
        string $column,
        int $scopeId,
        ?string $date = null,
        int $padding = 4,
        string $scopeColumn = 'team_id',
    ): string {
        $dateString = $date ?? now()->format('Ymd');
        $fullPrefix = "{$prefix}-{$dateString}-";

        $count = DB::table($table)
            ->where($scopeColumn, $scopeId)
            ->where($column, 'like', $fullPrefix.'%')
            ->count() + 1;

        return $fullPrefix.str_pad((string) $count, $padding, '0', STR_PAD_LEFT);
    }
}
