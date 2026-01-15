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
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')
                ->constrained('contacts')
                ->cascadeOnDelete();
            $table->enum('status', ['open', 'pending', 'closed'])->default('open');
            $table->foreignId('assigned_to')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestampTz('last_message_at')->nullable()->index();
            $table->unsignedInteger('unread_count')->default(0);
            $table->string('last_message_preview')->nullable();
            $table->timestamps();

            $table->index(['assigned_to', 'status', 'last_message_at']);
            $table->unique('contact_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
