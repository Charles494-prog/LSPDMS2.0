<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $headSecretaries = DB::table('login_user')
            ->whereRaw("LOWER(TRIM(tungkulin)) IN (?, ?)", ['head secretary', 'secretary head'])
            ->count();

        if ($headSecretaries > 1) {
            throw new \RuntimeException(
                "Cannot enforce one Head Secretary: found {$headSecretaries} Head Secretary accounts. "
                .'Choose the account to retain, demote the others in Admin, then rerun php artisan migrate.'
            );
        }

        DB::table('login_user')
            ->whereRaw("LOWER(TRIM(tungkulin)) = ?", ['secretary head'])
            ->update(['tungkulin' => 'Head Secretary']);

        Schema::table('login_user', function (Blueprint $table): void {
            $table->unsignedTinyInteger('head_secretary_slot')
                ->nullable()
                ->virtualAs("CASE WHEN LOWER(TRIM(tungkulin)) IN ('head secretary', 'secretary head') THEN 1 ELSE NULL END");
            $table->unique('head_secretary_slot', 'login_user_single_head_secretary');
        });

        Schema::table('global_variables', function (Blueprint $table): void {
            $table->boolean('r401_use_purok_secretary')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('global_variables', function (Blueprint $table): void {
            $table->dropColumn('r401_use_purok_secretary');
        });

        Schema::table('login_user', function (Blueprint $table): void {
            $table->dropUnique('login_user_single_head_secretary');
            $table->dropColumn('head_secretary_slot');
        });
    }
};
