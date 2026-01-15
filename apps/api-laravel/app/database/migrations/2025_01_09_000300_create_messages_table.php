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
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')
                ->constrained('conversations')
                ->cascadeOnDelete();
            $table->enum('direction', ['in', 'out']);
            $table->enum('type', ['text', 'image', 'video', 'audio', 'document', 'sticker'])
                ->default('text');
            $table->text('body')->nullable();
            $table->string('wa_message_id')->nullable()->unique();
            $table->uuid('client_message_id')->unique();
            $table->timestampTz('wa_timestamp')->index();
            $table->enum('status', ['pending', 'sent', 'delivered', 'read', 'failed'])
                ->default('pending');
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->string('media_mime')->nullable();
            $table->unsignedInteger('media_size')->nullable();
            $table->string('media_url')->nullable();
            $table->string('storage_path')->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'wa_timestamp']);
            $table->index(['conversation_id', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
