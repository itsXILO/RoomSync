<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            // format determines which ChannelAdapter to use
            $table->enum('format', ['json', 'xml']);
            // The URL RoomSync will POST inventory updates to
            $table->string('endpoint_url');
            // Channels send this key in X-API-Key when calling our webhook
            $table->string('api_key');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channels');
    }
};
