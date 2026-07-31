<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The LMS batch number the lead was manually allocated into (the moment it
     * became a student) — shown on the leads list so HR can see at a glance
     * who is already placed and who still needs a batch.
     */
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('allocated_batch_number', 50)->nullable()->after('masterclass_followup_at');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn('allocated_batch_number');
        });
    }
};
