<?php

namespace App\Http\Controllers;

use App\Models\Record;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TarhetaStatusController extends Controller
{
    private const STATUSES = ['MS', 'UWP', 'MK', 'INACTIVE'];

    public function index()
    {
        $records = Record::query()
            ->select(['id', 'full_name', 'status', 'tarheta_status', 'filing_status', 'filing_number'])
            ->orderBy('full_name')
            ->get()
            ->map(function (Record $record): array {
                $status = mb_strtoupper(trim((string) $record->status));
                $tarhetaStatus = mb_strtoupper(trim((string) $record->tarheta_status));
                $filingStatus = mb_strtoupper(trim((string) $record->filing_status));

                return [
                    'id' => $record->id,
                    'name' => trim((string) $record->full_name) ?: 'Unnamed record',
                    'status' => $status,
                    'tarheta_status' => $tarhetaStatus,
                    'filing_status' => $filingStatus,
                    'filing_number' => (string) ($record->filing_number ?? ''),
                    'matched' => $status === $tarhetaStatus && $status === $filingStatus,
                ];
            });
        $total = $records->count();
        $matched = $records->where('matched', true)->count();

        return view('tarheta-status', [
            'records' => $records,
            'total' => $total,
            'matched' => $matched,
            'percentage' => $total === 0 ? 0 : round(($matched / $total) * 100, 1),
        ]);
    }

    public function update(Request $request, Record $record): JsonResponse
    {
        $validated = $request->validate([
            'tarheta_status' => ['sometimes', 'nullable', Rule::in(self::STATUSES)],
            'filing_status' => ['sometimes', 'nullable', Rule::in(self::STATUSES)],
            'filing_number' => ['sometimes', 'nullable', 'string', 'max:100'],
        ]);
        abort_if($validated === [], 422, 'Provide at least one status or filing number to update.');

        $record = DB::transaction(function () use ($record, $validated): Record {
            $record = Record::query()->lockForUpdate()->findOrFail($record->id);
            $record->fill($validated);
            $record->save();

            return $record;
        });

        $status = mb_strtoupper(trim((string) $record->status));
        $tarhetaStatus = mb_strtoupper(trim((string) $record->tarheta_status));
        $filingStatus = mb_strtoupper(trim((string) $record->filing_status));

        return response()->json([
            'message' => 'Tarheta status saved.',
            'matches' => $status === $tarhetaStatus && $status === $filingStatus,
            'status' => $status,
            'tarheta_status' => $tarhetaStatus,
            'filing_status' => $filingStatus,
            'filing_number' => (string) ($record->filing_number ?? ''),
        ]);
    }
}
