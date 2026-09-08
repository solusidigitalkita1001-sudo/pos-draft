<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A simple platform-wide admin flag for you (the SaaS operator) to
     * review custom plan requests across ALL organizations. Deliberately
     * NOT built on Spatie's team-scoped roles — those are scoped to a
     * single team's permission context and don't fit a cross-tenant
     * admin concern. Grant with: php artisan platform-admin:grant {email}
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_platform_admin')->default(false)->after('current_organization_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_platform_admin');
        });
    }
};
