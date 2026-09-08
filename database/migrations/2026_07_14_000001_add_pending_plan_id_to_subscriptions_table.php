<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Downgrade ke plan yang lebih kecil TIDAK langsung berlaku —
     * organization sudah bayar untuk periode saat ini di plan yang
     * lebih besar, jadi wajar kalau kuota/fitur plan besar itu tetap
     * dinikmati sampai periode habis. `pending_plan_id` menyimpan plan
     * TUJUAN, baru diterapkan oleh job harian
     * (ProcessSubscriptionLifecycleAction) begitu current_period_end
     * lewat — mirror pola `canceled_at` yang sudah ada untuk voluntary
     * cancellation.
     */
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->foreignId('pending_plan_id')
                ->nullable()
                ->after('plan_id')
                ->constrained('plans')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pending_plan_id');
        });
    }
};
