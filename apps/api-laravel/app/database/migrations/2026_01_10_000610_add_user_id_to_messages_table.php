<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->foreignId('user_id')
                ->nullable()
                ->after('conversation_id')
                ->constrained('users')
                ->nullOnDelete();

            $table->index(['user_id', 'created_at'], 'messages_user_id_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex('messages_user_id_created_at_index');
            $table->dropForeign(['user_id']);
            $table->dropColumn(['user_id']);
        });
    }
};
