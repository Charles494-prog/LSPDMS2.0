<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('login_user', 'chatbot_records_allow')) {
            Schema::table('login_user', function (Blueprint $table): void {
                $table->boolean('chatbot_records_allow')->default(false);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('login_user', 'chatbot_records_allow')) {
            Schema::table('login_user', function (Blueprint $table): void {
                $table->dropColumn('chatbot_records_allow');
            });
        }
    }
};
