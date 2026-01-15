<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->foreignId('closed_by')
                ->nullable()
                ->after('closed_at')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestampTz('reopened_at')->nullable()->after('closed_by');

            $table->index(['assigned_to', 'assigned_at'], 'conversations_assigned_to_assigned_at_index');
            $table->index(['closed_by', 'closed_at'], 'conversations_closed_by_closed_at_index');
            $table->index(['status', 'assigned_to'], 'conversations_status_assigned_to_index');
            $table->index(['reopened_at'], 'conversations_reopened_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropIndex('conversations_assigned_to_assigned_at_index');
            $table->dropIndex('conversations_closed_by_closed_at_index');
            $table->dropIndex('conversations_status_assigned_to_index');
            $table->dropIndex('conversations_reopened_at_index');

            $table->dropForeign(['closed_by']);
            $table->dropColumn(['closed_by', 'reopened_at']);
        });
    }
};
