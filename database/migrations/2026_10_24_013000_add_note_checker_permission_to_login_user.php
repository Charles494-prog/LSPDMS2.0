<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Note Checker moves out of the Form Generator into its own page with its own permission.
 * Everyone who could open it before (Form Generator access) keeps it, so nobody loses access.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('login_user', 'note_checker_allow')) {
            return;
        }

        Schema::table('login_user', function (Blueprint $table): void {
            $table->boolean('note_checker_allow')->default(false);
        });

        DB::table('login_user')
            ->where('form_generator_allow', true)
            ->update(['note_checker_allow' => true]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('login_user', 'note_checker_allow')) {
            Schema::table('login_user', function (Blueprint $table): void {
                $table->dropColumn('note_checker_allow');
            });
        }
    }
};
