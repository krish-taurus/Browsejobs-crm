<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tracks the manual masterclass flow: when the watch link went out to an
     * interested lead (WhatsApp + email) and when the did-you-watch follow-up
     * ran — both exactly once per lead.
     */
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->timestamp('masterclass_link_sent_at')->nullable()->after('interested_course_slug');
            $table->timestamp('masterclass_followup_at')->nullable()->after('masterclass_link_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn(['masterclass_link_sent_at', 'masterclass_followup_at']);
        });
    }
};
