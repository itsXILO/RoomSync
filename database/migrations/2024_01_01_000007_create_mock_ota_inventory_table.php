<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mock_ota_inventory', function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_type_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('available');
            $table->decimal('rate', 10, 2);
            $table->timestamps();

            // One row per channel per room type per day
            $table->unique(['channel_id', 'room_type_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mock_ota_inventory');
    }
};
