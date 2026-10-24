<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('channel_id')->constrained()->cascadeOnDelete();
            // The OTA's own reference number for this booking
            $table->string('external_ref');
            $table->string('guest_name');
            $table->date('check_in');
            $table->date('check_out');  // exclusive (the night before checkout)
            $table->unsignedInteger('rooms');
            // confirmed = inventory decremented, rejected = no inventory, cancelled = restored
            $table->enum('status', ['confirmed', 'rejected', 'cancelled']);
            // Human-readable reason for rejected bookings
            $table->text('reason')->nullable();
            $table->timestamps();

            // Idempotency key: same OTA can't book the same ref twice
            $table->unique(['channel_id', 'external_ref']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
