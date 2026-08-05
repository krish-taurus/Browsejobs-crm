<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Monthly per-person targets behind the Taurus team signal.
     *
     * Only the target is stored. The actual is always measured live from the
     * CRM/LMS (see TaurusSnapshotService::actualFor) so a number on this page
     * can never drift from the system of record, and nothing has to be
     * recalculated by a nightly job.
     */
    public function up(): void
    {
        Schema::create('taurus_targets', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('metric', 50);                 // key from config('taurus.target_metrics')
            $table->decimal('target_value', 12, 2);
            $table->char('period', 7);                    // YYYY-MM

            $table->foreignId('set_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            // One target per person, per metric, per month.
            $table->unique(['user_id', 'metric', 'period']);
            $table->index('period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taurus_targets');
    }
};
