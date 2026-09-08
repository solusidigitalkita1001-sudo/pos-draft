<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Named "subscription_invoices" (not "invoices") to avoid clashing
     * with the POS module's own invoice_number concept on `transactions`
     * — these are billing invoices for the SaaS subscription itself,
     * a completely different thing from a store's sales invoice.
     */
    public function up(): void
    {
        Schema::create('subscription_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->string('order_id')->unique();
            $table->decimal('amount', 12, 2);
            $table->string('billing_period'); // monthly | yearly
            $table->string('status'); // pending | paid | failed | expired | canceled
            $table->string('snap_token')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_invoices');
    }
};
