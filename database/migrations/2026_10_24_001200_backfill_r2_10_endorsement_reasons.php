<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Copy every existing R2-10 reason into the history table as one current row
 * per record, verbatim. Who wrote it and when is unknown, so endorsed_by and
 * endorsed_at stay NULL. records is read only. Records that already have a
 * backfilled row are skipped, so running this again inserts nothing.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('r2_10_endorsement_reasons') || ! Schema::hasTable('records')) {
            return;
        }

        DB::table('records')
            ->select(['id', 'status', 'reason_of_r2_10_endorsement'])
            ->whereNotNull('reason_of_r2_10_endorsement')
            ->whereRaw("TRIM(reason_of_r2_10_endorsement) <> ''")
            ->whereNotExists(fn ($query) => $query
                ->selectRaw('1')
                ->from('r2_10_endorsement_reasons')
                ->whereColumn('r2_10_endorsement_reasons.record_id', 'records.id')
                ->where('r2_10_endorsement_reasons.is_backfilled', true))
            ->chunkById(200, function ($records): void {
                DB::transaction(function () use ($records): void {
                    foreach ($records as $record) {
                        $hasCurrent = DB::table('r2_10_endorsement_reasons')
                            ->where('record_id', $record->id)
                            ->where('is_current', true)
                            ->exists();
                        DB::table('r2_10_endorsement_reasons')->insert([
                            'record_id' => $record->id,
                            'record_type' => mb_substr(mb_strtoupper(trim((string) $record->status)) ?: 'UNKNOWN', 0, 10),
                            'reason' => $record->reason_of_r2_10_endorsement,
                            'endorsed_by' => null,
                            'endorsed_at' => null,
                            'monthly_session_id' => null,
                            'session_movement_id' => null,
                            'source' => 'legacy_backfill',
                            'is_current' => ! $hasCurrent,
                            'is_backfilled' => true,
                            'created_at' => now(),
                        ]);
                    }
                });
            });
    }

    public function down(): void
    {
        if (Schema::hasTable('r2_10_endorsement_reasons')) {
            DB::table('r2_10_endorsement_reasons')->where('source', 'legacy_backfill')->delete();
        }
    }
};
