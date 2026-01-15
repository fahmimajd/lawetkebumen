<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->timestampTz('assigned_at')->nullable()->after('assigned_to');
            $table->timestampTz('closed_at')->nullable()->after('assigned_at');
            $table->foreignId('queue_id')
                ->nullable()
                ->after('closed_at')
                ->constrained('queues')
                ->nullOnDelete();
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->dropUnique(['contact_id']);
            $table->index(['contact_id', 'status', 'closed_at']);
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropIndex(['contact_id', 'status', 'closed_at']);
            $table->unique('contact_id');
            $table->dropForeign(['queue_id']);
            $table->dropColumn(['assigned_at', 'closed_at', 'queue_id']);
        });
    }
};
