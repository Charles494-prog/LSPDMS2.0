<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * One account per email address. Existing duplicates (compared as
 * LOWER(TRIM(gmail))) are never deleted or merged here: the index is skipped
 * with a warning so an administrator can resolve them, then rerun.
 */
return new class extends Migration
{
    private const INDEX = 'login_user_gmail_unique';

    public function up(): void
    {
        if (! Schema::hasTable('login_user') || Schema::hasIndex('login_user', self::INDEX)) {
            return;
        }

        $duplicates = DB::table('login_user')
            ->selectRaw('LOWER(TRIM(gmail)) AS normalized_gmail, COUNT(*) AS total')
            ->groupByRaw('LOWER(TRIM(gmail))')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->count();
        if ($duplicates > 0) {
            Log::warning('login_user.gmail unique index skipped: duplicate email addresses exist.', [
                'duplicate_groups' => $duplicates,
            ]);

            return;
        }

        try {
            Schema::table('login_user', function (Blueprint $table): void {
                $table->unique('gmail', self::INDEX);
            });
        } catch (UniqueConstraintViolationException) {
            Log::warning('login_user.gmail unique index skipped: the database reported duplicate email addresses.');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('login_user') && Schema::hasIndex('login_user', self::INDEX)) {
            Schema::table('login_user', function (Blueprint $table): void {
                $table->dropUnique(self::INDEX);
            });
        }
    }
};
