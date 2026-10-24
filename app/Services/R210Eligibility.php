<?php

namespace App\Services;

use App\Models\GlobalVariable;
use App\Models\Record;
use Illuminate\Support\Carbon;

class R210Eligibility
{
    public function forRecord(Record $record, ?GlobalVariable $requirements): bool
    {
        $status = mb_strtoupper(trim((string) $record->status));
        if ($status === 'MK') {
            return true;
        }
        if (! in_array($status, ['MS', 'UWP'], true) || ! $record->date_of_entry) {
            return false;
        }

        $requiredYears = max(1, (int) ($requirements?->entry_years_requirement ?? 1));
        // Today's date in Manila (the app clock is UTC), at midnight like the stored dates it is compared with.
        $today = Carbon::parse(Carbon::now('Asia/Manila')->toDateString());
        if ($record->date_of_entry->copy()->addYears($requiredYears)->isAfter($today)) {
            return false;
        }

        if (! $requirements?->dateofbaptism_requirement_boolean) {
            return true;
        }

        return $requirements->baptism_requirement !== null
            && $record->date_of_baptism !== null
            && $record->date_of_baptism->lessThanOrEqualTo($requirements->baptism_requirement);
    }
}
