<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nullable, tidak ada default JSON — kalau kolomnya null atau
     * sebuah key belum ada di dalamnya, dianggap "enabled" (opt-out,
     * bukan opt-in). Ini penting supaya user LAMA yang belum pernah
     * buka halaman preferensi tetap dapat notifikasi seperti biasa,
     * bukan tiba-tiba semuanya mati.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('notification_preferences')->nullable()->after('is_platform_admin');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('notification_preferences');
        });
    }
};
