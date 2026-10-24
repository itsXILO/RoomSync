<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_id')->constrained()->cascadeOnDelete();
            // 'in' = inbound webhook from OTA, 'out' = we pushed to OTA
            $table->enum('direction', ['in', 'out']);
            // Short name for the event e.g. 'booking_received', 'inventory_push'
            $table->string('event');
            // Full raw payload (JSON or XML)
            $table->longText('payload');
            $table->enum('status', ['success', 'failed', 'pending']);
            // How many times this job has been attempted (for retry tracking)
            $table->unsignedInteger('attempts')->default(0);
            // HTTP response code from the OTA (null for inbound)
            $table->unsignedSmallInteger('response_code')->nullable();
            // Error message if failed
            $table->text('error')->nullable();
            // Append-only log — no updated_at
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_logs');
    }
};
