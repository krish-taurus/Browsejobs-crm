<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Which LMS course this lead is interested in — set by HR during/after
        // the qualification call. Drives which weekend masterclass batch the
        // candidate is seated in when they become Interested.
        Schema::table('leads', function (Blueprint $table) {
            $table->string('interested_course_slug')->nullable()->after('campaign_name');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn('interested_course_slug');
        });
    }
};
