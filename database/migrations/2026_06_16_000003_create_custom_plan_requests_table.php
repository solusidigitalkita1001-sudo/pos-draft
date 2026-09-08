<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Custom" plan flow — an organization owner requests a specific
     * store/owner quota, a platform admin reviews and approves/rejects
     * it manually (see docs/19-payment-and-custom-plan.md).
     */
    public function up(): void
    {
        Schema::create('custom_plan_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('requested_max_stores')->nullable();
            $table->unsignedInteger('requested_max_owners')->nullable();
            $table->text('message')->nullable();
            $table->string('status')->default('pending'); // pending | approved | rejected
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_plan_requests');
    }
};
