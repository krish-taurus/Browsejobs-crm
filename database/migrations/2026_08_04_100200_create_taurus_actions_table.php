<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Approval queue for anything Taurus proposes.
     *
     * Nothing here executes on its own — a "cancel this subscription" row is a
     * request that waits for the Super Admin to approve or dismiss it. Keeping
     * it as data (rather than acting immediately) is the whole point: the
     * dashboard suggests, a person decides, and the decision is on record.
     */
    public function up(): void
    {
        Schema::create('taurus_actions', function (Blueprint $table) {
            $table->id();

            $table->string('action', 50);                 // e.g. cancel_subscription
            $table->string('target_type', 50)->nullable(); // e.g. taurus_subscription
            $table->unsignedBigInteger('target_id')->nullable();
            $table->string('summary', 500)->nullable();    // human-readable, frozen at request time

            $table->enum('status', ['pending_approval', 'approved', 'dismissed'])->default('pending_approval');

            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_note', 500)->nullable();

            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['target_type', 'target_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taurus_actions');
    }
};
