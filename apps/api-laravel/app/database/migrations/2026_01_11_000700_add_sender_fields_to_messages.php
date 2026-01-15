<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->string('sender_wa_id')->nullable()->after('storage_path');
            $table->string('sender_name')->nullable()->after('sender_wa_id');
            $table->string('sender_phone')->nullable()->after('sender_name');
            $table->index('sender_wa_id');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex(['sender_wa_id']);
            $table->dropColumn(['sender_wa_id', 'sender_name', 'sender_phone']);
        });
    }
};
