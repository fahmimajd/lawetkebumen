<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('source')->default('wa-gateway');
            $table->string('event_type');
            $table->string('event_id')->unique();
            $table->json('payload');
            $table->timestampTz('received_at');
            $table->timestampTz('processed_at')->nullable();
            $table->enum('status', ['received', 'processed', 'ignored', 'failed'])
                ->default('received');
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};
