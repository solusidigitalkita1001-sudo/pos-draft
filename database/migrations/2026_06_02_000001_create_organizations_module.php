<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Organization = akun pelanggan (billing entity).
     * Team tetap merepresentasikan 1 toko. Satu Organization bisa punya
     * banyak Team (toko), sesuai kuota dari plan/subscription-nya.
     */
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
            $table->softDeletes();
        });

        /**
         * Pemilik akun (owner) dari sebuah organization.
         * Untuk plan basic/premium/ultra hanya ada 1 owner.
         * Untuk plan custom, bisa lebih dari 1 (dibatasi oleh
         * plans.max_owners / subscriptions.max_owners_override).
         */
        Schema::create('organization_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role'); // owner | manager
            $table->timestamps();

            $table->unique(['organization_id', 'user_id']);
        });

        /**
         * Master paket berlangganan. max_stores/max_owners null artinya
         * "negotiable" (khusus plan custom) — nilai efektifnya diambil
         * dari subscriptions.max_stores_override / max_owners_override.
         */
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // basic | premium | ultra | custom
            $table->string('name');
            $table->unsignedInteger('max_stores')->nullable();
            $table->unsignedInteger('max_owners')->nullable();
            $table->decimal('price_monthly', 12, 2)->nullable();
            $table->decimal('price_yearly', 12, 2)->nullable();
            $table->boolean('is_custom')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->json('features')->nullable();
            $table->timestamps();
        });

        /**
         * Status langganan sebuah organization. Riwayat pergantian plan
         * disimpan sebagai baris baru (bukan overwrite), supaya histori
         * upgrade/downgrade tetap terlacak untuk keperluan invoice/reporting.
         *
         * max_stores_override / max_owners_override dipakai untuk plan
         * custom (kuota dinegosiasikan per pelanggan) — kalau null,
         * kuota efektif ikut default dari plans.max_stores / max_owners.
         */
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->string('status'); // trial | active | past_due | suspended | canceled
            $table->unsignedInteger('max_stores_override')->nullable();
            $table->unsignedInteger('max_owners_override')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('plans');
        Schema::dropIfExists('organization_members');
        Schema::dropIfExists('organizations');
    }
};
