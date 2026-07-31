<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_status_history', function (Blueprint $table) {
            $table->id();

            $table->foreignId('lead_id')
                ->constrained('leads')
                ->cascadeOnDelete();

            $table->foreignId('status_id')
                ->constrained('lead_statuses')
                ->cascadeOnDelete();

            $table->foreignId('lost_reason_id')
                ->nullable()
                ->constrained('lost_reasons')
                ->nullOnDelete();                  // required only when status = Lost (enforce in app logic)

            $table->foreignId('changed_by_user_id')
                ->nullable()                        // null = system change (AI call outcome)
                ->constrained('users')
                ->cascadeOnDelete();                // HR Executive who made the change

            $table->text('remarks')->nullable();

            $table->timestamps();

            $table->index(['lead_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_status_history');
    }
};
