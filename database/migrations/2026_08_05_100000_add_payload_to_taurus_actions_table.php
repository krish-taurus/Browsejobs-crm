<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Taurus can now propose actions it has worked out itself — send this
     * person a message, create this task — not just "cancel this subscription".
     * Those carry arguments, so the queued row needs somewhere to keep them
     * until a human approves and they are executed verbatim.
     */
    public function up(): void
    {
        Schema::table('taurus_actions', function (Blueprint $table) {
            $table->json('payload')->nullable()->after('target_id');
        });
    }

    public function down(): void
    {
        Schema::table('taurus_actions', function (Blueprint $table) {
            $table->dropColumn('payload');
        });
    }
};
