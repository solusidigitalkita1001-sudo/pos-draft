<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nullable dulu supaya migration ini aman dijalankan di database yang
     * sudah punya data team. Setelah backfill (php artisan organizations:backfill)
     * dijalankan, kolom ini bisa di-nonaktifkan nullable-nya lewat migration
     * terpisah di Sprint 2.
     */
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->foreignId('organization_id')
                ->nullable()
                ->after('id')
                ->constrained()
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropConstrainedForeignId('organization_id');
        });
    }
};
