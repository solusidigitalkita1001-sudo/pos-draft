<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sebelumnya tax_total di transaksi POS selalu hardcode 0 — tidak
     * ada tempat sama sekali untuk menyimpan tarif pajak toko. Kolom
     * ini nilainya persen (mis. 11.00 untuk PPN 11%), default 0 supaya
     * toko yang tidak kena pajak/tidak mau menghitung pajak tidak
     * terpengaruh sama sekali oleh perubahan ini.
     */
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->decimal('tax_rate', 5, 2)->default(0)->after('is_personal');
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn('tax_rate');
        });
    }
};
