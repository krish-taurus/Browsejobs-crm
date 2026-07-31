<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();

            $table->string('mobile');
            $table->string('name')->nullable();
            $table->string('email')->nullable();

            $table->string('source')->nullable();        // e.g. Meta Ads, Google Ads, Website
            $table->string('campaign_name')->nullable();  // for correlating lead quality per campaign

            $table->foreignId('added_by_user_id')
                ->nullable()                               // null = website/LMS/webhook capture (no internal user)
                ->constrained('users')
                ->cascadeOnDelete();                       // Media Strategist who added the lead

            $table->foreignId('current_status_id')
                ->nullable()
                ->constrained('lead_statuses')
                ->nullOnDelete();

            $table->foreignId('assigned_to_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();                           // current HR Executive

            $table->foreignId('assigned_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();                           // HR Manager who assigned

            $table->timestamp('assigned_at')->nullable();

            $table->timestamps();

            $table->index('mobile');
            $table->index(['current_status_id', 'assigned_to_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
