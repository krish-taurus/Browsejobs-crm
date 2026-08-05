<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Recurring vendor spend for the Taurus expense sentinel.
     *
     * Deliberately separate from `expenses`: that table records what was paid
     * and when, one row per payment. This one records a standing commitment —
     * cost per month, seats, and when it was last actually used — which is
     * what makes "you are paying ₹19,100/mo for something nobody opened in
     * three months" answerable.
     */
    public function up(): void
    {
        Schema::create('taurus_subscriptions', function (Blueprint $table) {
            $table->id();

            $table->string('name');                      // e.g. "Zoom Pro · 4 seats"
            $table->string('vendor')->nullable();
            $table->decimal('monthly_cost', 12, 2);
            $table->unsignedSmallInteger('seats')->nullable();

            // Null = never recorded as used, which the sentinel treats as the
            // worst case rather than the best.
            $table->date('last_used_on')->nullable();

            $table->string('usage_note', 500)->nullable(); // "7 logins in 90 days · SEO paused"
            $table->boolean('is_active')->default(true);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['is_active', 'last_used_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taurus_subscriptions');
    }
};
