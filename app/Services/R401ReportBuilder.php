<?php

namespace App\Services;

use App\Models\MonthlyMovementSession;
use App\Models\MonthlySessionMovement;
use App\Models\Purok;
use App\Models\Record;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class R401ReportBuilder
{
    public function build(string $purok, string $status, ?MonthlyMovementSession $session = null): array
    {
        $candidateRows = Record::query()
            ->orderBy('full_name')
            ->get([
                'id',
                'full_name',
                'registry',
                'gender',
                'purok',
                'grupo',
                'kapisanan',
                'date_of_baptism',
                'evangelist',
                'status',
                'monthly_code',
            ])
            ->map(function (Record $record): array {
                $monthlyCode = trim((string) $record->monthly_code);
                [$codeStatus, $code] = $this->monthlyCodeParts(
                    $monthlyCode,
                    mb_strtoupper(trim((string) $record->status))
                );

                return [
                    'record_id' => $record->id,
                    'status' => $codeStatus ?: mb_strtoupper(trim((string) $record->status)),
                    'code' => $monthlyCode === '' ? 'A' : ($code ?: 'A'),
                    'name' => trim((string) $record->full_name),
                    'registry' => strtoupper(trim((string) $record->registry)),
                    'gender' => $this->displayGender((string) $record->gender),
                    'purok' => trim((string) $record->purok),
                    'grupo' => trim((string) $record->grupo),
                    'kapisanan' => $this->displayKapisanan((string) $record->kapisanan),
                    'date_of_baptism' => $record->date_of_baptism?->format('d/m/Y') ?? '',
                    'doctrinator' => trim((string) $record->evangelist),
                ];
            })
            ->filter(fn (array $row): bool => $row['status'] === $status);

        $session ??= MonthlyMovementSession::query()->where('active_key', 'active')->first();
        if ($session?->status === 'completed') {
            $historicalRows = DB::table('permanent_history')
                ->where('session_id', $session->id)
                ->orderBy('id')
                ->get();
            $codedRecordIds = [];
            $archiveRows = [];
            foreach ($historicalRows as $history) {
                [$codeStatus, $code] = $this->monthlyCodeParts(
                    (string) $history->monthly_code,
                    mb_strtoupper(trim((string) $history->status))
                );
                if (! in_array($codeStatus, ['MS', 'UWP'], true)) {
                    continue;
                }
                $previous = json_decode((string) ($history->previous_values ?? ''), true) ?: [];
                $next = json_decode((string) ($history->next_values ?? ''), true) ?: [];
                $previousStatus = mb_strtoupper(trim((string) ($previous['status'] ?? $history->status)));
                $nextStatus = mb_strtoupper(trim((string) ($next['status'] ?? $history->status)));
                $isTransferOut = $history->action === 'transfer'
                    && str_contains(mb_strtoupper((string) $history->monthly_code), 'C-');
                $isPreviousStatus = ! $isTransferOut
                    && $previousStatus !== $nextStatus
                    && $previousStatus === $codeStatus
                    && ! in_array($code, ['D', 'DD', 'E', 'G', 'H'], true);
                $isPreviousSide = $isTransferOut || $isPreviousStatus;
                $values = $isPreviousSide ? $previous : $next;
                $memberPurok = trim((string) ($values['purok'] ?? ''));
                if ($memberPurok === '') {
                    continue;
                }
                if (! $isPreviousSide) {
                    $codedRecordIds[] = (int) $history->record_id;
                }
                $archiveRows[] = [
                    'record_id' => $history->record_id,
                    'status' => $codeStatus,
                    'code' => $code ?: 'A',
                    'name' => trim((string) $history->full_name),
                    'registry' => strtoupper(trim((string) $history->registry)),
                    'gender' => $this->displayGender((string) $history->gender),
                    'purok' => $memberPurok,
                    'grupo' => trim((string) ($values['grupo'] ?? '')),
                    'kapisanan' => $this->displayKapisanan((string) $history->kapisanan),
                    'date_of_baptism' => $history->date_of_baptism
                        ? Carbon::parse($history->date_of_baptism)->format('d/m/Y')
                        : '',
                    'doctrinator' => trim((string) $history->evangelist),
                ];
            }
            $candidateRows = $candidateRows
                ->reject(fn (array $row): bool => in_array((int) ($row['record_id'] ?? 0), $codedRecordIds, true))
                ->concat(collect($archiveRows)->filter(fn (array $row): bool => $row['status'] === $status));
        } elseif ($session !== null && $session->active_key === 'active') {
            $movementRows = MonthlySessionMovement::query()
                ->where('session_id', $session->id)
                ->orderBy('id')
                ->get()
                ->map(function (MonthlySessionMovement $movement): ?array {
                    $previous = $movement->previous_values ?? [];
                    [$codeStatus, $code] = $this->monthlyCodeParts(
                        (string) $movement->monthly_code,
                        mb_strtoupper(trim((string) ($previous['status'] ?? $movement->status)))
                    );
                    $memberStatus = $codeStatus ?: mb_strtoupper(trim((string) ($previous['status'] ?? $movement->status)));
                    $memberPurok = trim((string) ($previous['purok'] ?? $movement->previous_purok));
                    $memberGrupo = trim((string) ($previous['grupo'] ?? $movement->previous_grupo));
                    if (! in_array($memberStatus, ['MS', 'UWP'], true) || $memberPurok === '') {
                        return null;
                    }

                    return [
                        'record_id' => $movement->record_id,
                        'status' => $memberStatus,
                        'code' => $code ?: 'A',
                        'name' => trim((string) $movement->full_name),
                        'registry' => strtoupper(trim((string) $movement->registry)),
                        'gender' => $this->displayGender((string) $movement->gender),
                        'purok' => $memberPurok,
                        'grupo' => $memberGrupo,
                        'kapisanan' => $this->displayKapisanan((string) $movement->kapisanan),
                        'date_of_baptism' => $movement->date_of_baptism?->format('d/m/Y') ?? '',
                        'doctrinator' => trim((string) $movement->evangelist),
                    ];
                })
                ->filter(fn (?array $row): bool => $row !== null && $row['status'] === $status);
            $candidateRows = $candidateRows->concat($movementRows);
        }

        $candidateRows = $candidateRows
            ->unique(fn (array $row): string => implode('|', [
                (string) ($row['record_id'] ?? $row['name'].'|'.$row['registry']),
                $row['status'],
                $row['code'],
                $this->numericSortKey($row['purok']),
                preg_replace('/\D+/', '', $row['grupo']) ?? '',
            ]))
            ->when($purok !== 'all', fn (Collection $rows): Collection => $rows->filter(
                fn (array $row): bool => $this->numericSortKey($row['purok']) === (int) $purok
            ))
            ->groupBy(fn (array $row): string => (string) $this->numericSortKey($row['purok']))
            ->sortBy(fn ($group, string $key): int => $this->numericSortKey($key));

        $purokDetails = Purok::query()
            ->with('secretaryUser:id,first_name,last_name')
            ->orderBy('purok_number')
            ->orderBy('grupo_start')
            ->get(['id', 'purok_number', 'purok_name', 'secretary', 'manggagawa', 'ministerial_id', 'grupo_start', 'grupo_end', 'full_purok']);
        $reportRows = [];
        $reportPages = [];

        foreach ($candidateRows as $purokNumber => $purokRecords) {
            $number = (int) $purokNumber;
            $splitPuroks = $purokDetails->where('purok_number', $number)->values();
            $assignedRecords = $splitPuroks->mapWithKeys(function (Purok $splitPurok) use ($purokRecords): array {
                $min = $splitPurok->full_purok ? 1 : (int) $splitPurok->grupo_start;
                $max = $splitPurok->full_purok ? 24 : (int) $splitPurok->grupo_end;

                return [
                    $splitPurok->id => $purokRecords->filter(
                        fn (array $row): bool => (int) $row['grupo'] >= $min && (int) $row['grupo'] <= $max
                    ),
                ];
            });
            if ($splitPuroks->isEmpty()) {
                $assignedRecords->put(null, $purokRecords);
            }

            foreach ($assignedRecords as $splitPurokId => $splitRecords) {
                if ($splitRecords->isEmpty()) {
                    continue;
                }
                $splitPurok = $splitPuroks->firstWhere('id', $splitPurokId);
                $rows = $splitRecords
                    ->sort(function (array $left, array $right): int {
                        $codeOrder = ($left['code'] === 'A' ? 0 : 1) <=> ($right['code'] === 'A' ? 0 : 1);
                        if ($codeOrder !== 0) {
                            return $codeOrder;
                        }
                        $grupoOrder = (int) $left['grupo'] <=> (int) $right['grupo'];

                        return $grupoOrder !== 0 ? $grupoOrder : strcasecmp($left['name'], $right['name']);
                    })
                    ->values()
                    ->map(fn (array $row): array => [
                        'record_id' => $row['record_id'] ?? null,
                        'code' => $row['code'],
                        'name' => $row['name'],
                        'registry' => $row['registry'],
                        'gender' => $row['gender'],
                        'purok_grupo' => $this->numericSortKey($row['purok']).'-'.trim($row['grupo'], " \t\n\r\0\x0B-"),
                        'kapisanan' => $row['kapisanan'],
                        'date_of_baptism' => $row['date_of_baptism'],
                        'doctrinator' => $row['doctrinator'],
                    ])->all();
                $reportRows = [...$reportRows, ...$rows];
                foreach (array_chunk($rows, 20) as $pageIndex => $pageRows) {
                    $reportPages[] = [
                        'purok_number' => $purokNumber,
                        'purok_name' => $splitPurok?->purok_name ?? 'Purok '.$purokNumber,
                        'maysakop_name' => trim((string) ($splitPurok?->manggagawa ?? '')),
                        'secretary_name' => trim((string) ($splitPurok?->secretaryUser?->full_name ?? '')),
                        'assigned_number' => trim((string) ($splitPurok?->ministerial_id ?? '')),
                        'page_number' => $pageIndex + 1,
                        'rows' => $pageRows,
                    ];
                }
            }
        }

        return ['rows' => $reportRows, 'pages' => $reportPages];
    }

    private function monthlyCodeParts(string $monthlyCode, string $fallbackStatus = ''): array
    {
        $parts = explode(':', trim($monthlyCode), 2);
        if (count($parts) < 2) {
            return [mb_strtoupper(trim($fallbackStatus)), mb_strtoupper(trim($monthlyCode, " \t\n\r\0\x0B\"'"))];
        }

        $status = mb_strtoupper(trim($parts[0]));
        if (! in_array($status, ['MS', 'UWP', 'MK'], true)) {
            $status = mb_strtoupper(trim($fallbackStatus));
        }

        return [
            $status,
            mb_strtoupper(trim(explode('/', $parts[1], 2)[0], " \t\n\r\0\x0B\"'")),
        ];
    }

    private function numericSortKey(string $value): int
    {
        return (int) preg_replace('/\D+/', '', $value);
    }

    private function displayGender(string $gender): string
    {
        return match (mb_strtolower(trim($gender))) {
            'l', 'male' => 'L',
            'b', 'female' => 'B',
            default => '',
        };
    }

    private function displayKapisanan(string $kapisanan): string
    {
        return match (mb_strtolower(trim($kapisanan))) {
            'b', 'buklod' => 'B',
            'k', 'kadiwa' => 'K',
            's', 'suliranin' => 'S',
            'binhi' => 'b',
            default => trim($kapisanan),
        };
    }
}
