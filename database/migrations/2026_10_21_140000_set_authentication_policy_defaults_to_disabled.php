<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('global_variables')) {
            return;
        }

        Schema::table('global_variables', function (Blueprint $table): void {
            if (Schema::hasColumn('global_variables', 'require_otp')) {
                $table->boolean('require_otp')->default(false)->change();
            }

            if (Schema::hasColumn('global_variables', 'allow_forgot_password')) {
                $table->boolean('allow_forgot_password')->default(false)->change();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('global_variables')) {
            return;
        }

        Schema::table('global_variables', function (Blueprint $table): void {
            if (Schema::hasColumn('global_variables', 'require_otp')) {
                $table->boolean('require_otp')->default(true)->change();
            }

            if (Schema::hasColumn('global_variables', 'allow_forgot_password')) {
                $table->boolean('allow_forgot_password')->default(true)->change();
            }
        });
    }
};
