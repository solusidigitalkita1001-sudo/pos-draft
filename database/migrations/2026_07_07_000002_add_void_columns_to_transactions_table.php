<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Transaction::STATUS_VOID sudah ada sejak awal sebagai constant,
     * tapi tidak ada satupun action/endpoint yang benar-benar
     * menyetelnya — void transaction belum punya implementasi sama
     * sekali. Kolom ini untuk mencatat kapan/oleh siapa/kenapa sebuah
     * transaksi dibatalkan.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->text('void_reason')->nullable()->after('note');
            $table->timestamp('voided_at')->nullable()->after('paid_at');
            $table->unsignedBigInteger('voided_by')->nullable()->after('voided_at');

            $table->foreign('voided_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['voided_by']);
            $table->dropColumn(['void_reason', 'voided_at', 'voided_by']);
        });
    }
};
