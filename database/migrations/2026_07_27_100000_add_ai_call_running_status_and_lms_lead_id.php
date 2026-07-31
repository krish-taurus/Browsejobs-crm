<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Track which LMS lead a CRM lead came from, so the sync never
        // imports the same lead twice.
        Schema::table('leads', function (Blueprint $table) {
            $table->unsignedBigInteger('lms_lead_id')->nullable()->unique()->after('campaign_name');
        });

        // "AI Call Running" sits between New and Interested: a lead enters it
        // when the AI auto-call is placed and leaves it when the call finishes
        // (outcome status) or fails (back to New).
        if (! DB::table('lead_statuses')->where('slug', 'ai_call_running')->exists()) {
            DB::table('lead_statuses')->where('sort_order', '>=', 2)->increment('sort_order');

            DB::table('lead_statuses')->insert([
                'name' => 'AI Call Running',
                'slug' => 'ai_call_running',
                'color' => '#6f42c1',
                'sort_order' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn('lms_lead_id');
        });

        DB::table('lead_statuses')->where('slug', 'ai_call_running')->delete();
        DB::table('lead_statuses')->where('sort_order', '>=', 3)->decrement('sort_order');
    }
};
