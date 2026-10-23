<?php

namespace App\Services;

use App\Models\GlobalVariable;
use App\Models\Purok;
use App\Models\Record;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Turns a plain-language record question into a fixed set of filters and
 * answers it. User text never becomes SQL: it only selects from a closed list
 * of conditions that are checked in PHP against the records table.
 *
 * Understands counts ("how many MS in purok 1 grupo 3?"), breakdowns ("MS per
 * purok"), and name lists ("list the buklod in purok 2a"), in English and
 * Filipino, plus follow-ups such as "how about purok 2?".
 */
class LspBotRecordQuery
{
    public const LIST_LIMIT = 15;

    private const STATUSES = ['MS', 'UWP', 'MK', 'INACTIVE'];

    private const DIMENSIONS = ['purok', 'grupo', 'status', 'kapisanan', 'gender'];

    public function __construct(private readonly R210Eligibility $eligibility) {}

    /**
     * The question as a query spec, or null when it is not a record question.
     * $previous is the last spec, used for follow-ups like "what about purok 2?".
     */
    public function parse(string $message, ?array $previous = null): ?array
    {
        $text = $this->normalize($message);
        $intent = $this->intent($text);
        $filters = $this->filters($text);
        $dimension = $this->dimension($text);

        $followUp = $previous !== null
            && preg_match('/^(?:how about|what about|and|eh|e|paano naman|paano sa|sa|ano naman|ilan naman|how many in|tapos)\b/u', $text) === 1;

        if ($followUp && ($filters !== [] || $dimension !== null)) {
            // Keep the earlier question, replacing only the conditions mentioned now.
            $merged = $previous['filters'];
            foreach ($filters as $key => $value) {
                $merged[$key] = $value;
            }

            return [
                'intent' => $dimension !== null ? 'breakdown' : ($intent ?? $previous['intent']),
                'dimension' => $dimension ?? ($intent === 'count' ? null : $previous['dimension']),
                'filters' => $merged,
            ];
        }

        if ($intent === null && $dimension !== null && $this->mentionsRecords($text, $filters)) {
            $intent = 'breakdown';
        }
        if ($intent === null) {
            return null;
        }
        if ($filters === [] && $dimension === null && ! $this->mentionsRecords($text, $filters)) {
            return null;
        }
        if ($dimension !== null && $intent === 'count') {
            $intent = 'breakdown';
        }

        return ['intent' => $intent, 'dimension' => $dimension, 'filters' => $filters];
    }

    /** True when answering $spec needs R2-10 (De-Listment) access. */
    public function needsDelistmentAccess(array $spec): bool
    {
        $filters = $spec['filters'];

        return isset($filters['endorsement']) || ! empty($filters['eligible']);
    }

    /** True when the answer would list MK member names (confidential). */
    public function listsMkNames(array $spec): bool
    {
        $statuses = $spec['filters']['status'] ?? ['MS', 'UWP', 'MK'];

        return $spec['intent'] === 'list' && in_array('MK', $statuses, true);
    }

    public function answer(array $spec): string
    {
        $filters = $spec['filters'];
        $records = $this->matching($filters);
        $subject = $this->subject($filters);
        $conditions = $this->conditions($filters);

        return match ($spec['intent']) {
            'breakdown' => $this->breakdownReply($records, $spec['dimension'] ?? 'purok', $subject, $conditions),
            'list' => $this->listReply($records, $subject, $conditions),
            default => $this->countReply($records->count(), $subject, $conditions, $filters),
        };
    }

    // ---------------------------------------------------------------- parsing

    private function normalize(string $message): string
    {
        $text = mb_strtolower(trim($message));
        $text = str_replace(['’', '‘', '“', '”'], ["'", "'", '"', '"'], $text);
        $text = preg_replace('/\br2\s*-?\s*10\b/u', 'r210', $text) ?? $text;

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    private function intent(string $text): ?string
    {
        if (preg_match('/\b(?:breakdown|break down|distribution|per|by|each|every|bawat|kada)\s+(?:purok|grupo|group|status|kalagayan|kapisanan|gender|sex|kasarian)\b/u', $text) === 1) {
            return 'breakdown';
        }
        if (preg_match('/^(?:list|show|display|give me|ipakita|ilista|sino|sino-sino|who)\b|\b(?:list of|names of|who are|sino ang|sino-sino ang|pangalan ng)\b/u', $text) === 1) {
            return 'list';
        }
        if (preg_match('/\b(?:how many|how much|count|number of|total|ilan|ilang|bilang ng|gaano karami)\b/u', $text) === 1) {
            return 'count';
        }

        return null;
    }

    private function dimension(string $text): ?string
    {
        if (preg_match('/\b(?:per|by|each|every|bawat|kada|breakdown of|breakdown by|distribution by)\s+(purok|grupo|group|status|kalagayan|kapisanan|gender|sex|kasarian)\b/u', $text, $match) !== 1) {
            return null;
        }

        return match ($match[1]) {
            'group', 'grupo' => 'grupo',
            'status', 'kalagayan' => 'status',
            'gender', 'sex', 'kasarian' => 'gender',
            default => $match[1],
        };
    }

    private function mentionsRecords(string $text, array $filters): bool
    {
        return $filters !== []
            || preg_match('/\b(?:records?|members?|kapatid|miyembro|people|tao|katao|everyone|lahat|ms|uwp|mk)\b/u', $text) === 1;
    }

    private function filters(string $text): array
    {
        $filters = [];

        // "All records / including inactive" means every status; read it before the
        // status words so "including inactive" is not taken as "inactive only".
        $allPattern = '/\b(?:all records|lahat ng records|including inactive|kasama ang inactive|whole database)\b/u';
        if (preg_match($allPattern, $text) === 1) {
            $filters['all_statuses'] = true;
            $text = preg_replace($allPattern, ' ', $text) ?? $text;
        }

        // Status. "inactive" contains "active", so it is read first and removed.
        $statuses = [];
        if (preg_match('/\b(?:inactive|in-active|hindi aktibo|di aktibo|delisted)\b/u', $text) === 1) {
            $statuses[] = 'INACTIVE';
            $text = preg_replace('/\b(?:inactive|in-active|hindi aktibo|di aktibo|delisted)\b/u', ' ', $text) ?? $text;
        }
        preg_match_all('/\b(ms|uwp|mk)\b/u', $text, $matches);
        foreach ($matches[1] as $status) {
            $statuses[] = mb_strtoupper($status);
        }
        if ($statuses === [] && preg_match('/\b(?:active|aktibo)\b/u', $text) === 1) {
            $statuses = ['MS', 'UWP', 'MK'];
        }
        if ($statuses !== []) {
            $filters['status'] = array_values(array_unique($statuses));
        }

        // Purok, with optional segment letter (2a / 2b), several allowed.
        if (preg_match_all('/\bpurok\s*-?\s*(\d+)\s*([a-z])?\b/u', $text, $matches, PREG_SET_ORDER) > 0) {
            $filters['purok'] = array_map(
                fn (array $match): array => ['number' => (int) $match[1], 'segment' => $match[2] ?? ''],
                $matches
            );
        }
        if (preg_match_all('/\b(?:grupo|group|grp)\s*-?\s*(\d+)\b/u', $text, $matches) > 0) {
            $filters['grupo'] = array_map('intval', $matches[1]);
        }

        $kapisanan = match (true) {
            preg_match('/\bbinhis?\b/u', $text) === 1 => 'binhi',
            preg_match('/\bkadiwas?\b/u', $text) === 1 => 'kadiwa',
            preg_match('/\bbuklods?\b/u', $text) === 1 => 'buklod',
            default => null,
        };
        if ($kapisanan !== null) {
            $filters['kapisanan'] = $kapisanan;
        }

        if (preg_match('/\b(?:female|females|women|sisters?|babae|kababaihan)\b/u', $text) === 1) {
            $filters['gender'] = 'Female';
        } elseif (preg_match('/\b(?:male|males|men|brothers?|lalaki|kalalakihan)\b/u', $text) === 1) {
            $filters['gender'] = 'Male';
        }

        if (preg_match('/\b(?:single|unmarried|not married|walang asawa|hindi kasal)\b/u', $text) === 1) {
            $filters['married'] = false;
        } elseif (preg_match('/\b(?:married|may asawa|kasal)\b/u', $text) === 1) {
            $filters['married'] = true;
        }

        // R2-10 endorsement and eligibility.
        $eligible = preg_match('/\b(?:eligible|qualified|qualify|pasok)\b/u', $text) === 1;
        $mentionsR210 = preg_match('/\b(?:r210|endorse|endorsed|endorsement|unendorsed)\b/u', $text) === 1;
        if ($eligible) {
            $filters['eligible'] = true;
        }
        $notEndorsed = preg_match('/\b(?:not|never|haven\'?t|hasn\'?t|pending|unendorsed|hindi pa|di pa|wala pang)\b/u', $text) === 1;
        if ($mentionsR210 || $eligible) {
            if ($notEndorsed && ($mentionsR210 || $eligible)) {
                $filters['endorsement'] = false;
            } elseif (preg_match('/\bendorsed\b/u', $text) === 1 || ($mentionsR210 && ! $eligible)) {
                $filters['endorsement'] = true;
            }
        }

        // Baptism and entry dates by year.
        foreach (['baptism' => '(?:baptized|baptised|baptism|bautismo|nabautismuhan|bininyagan)', 'entry' => '(?:entered|entry|joined|pumasok|naitala)'] as $field => $words) {
            $range = $this->yearRange($text, $words);
            if ($range !== null) {
                $filters[$field.'_year'] = $range;
            }
        }

        // Age.
        $age = $this->ageRange($text);
        if ($age !== null) {
            $filters['age'] = $age;
        }

        if (preg_match('/\b(?:rfid)\b/u', $text) === 1) {
            $filters['rfid'] = preg_match('/\b(?:without|no|walang)\s+rfid\b/u', $text) !== 1;
        }
        if (preg_match('/\b(?:monthly code|pending code|may code)\b/u', $text) === 1) {
            $filters['monthly_code'] = true;
        }

        $missing = [];
        $missingWords = [
            'date_of_baptism' => '(?:baptism date|date of baptism|baptism|bautismo)',
            'date_of_birth' => '(?:birthday|birth date|date of birth|birthdate|kaarawan)',
            'date_of_entry' => '(?:entry date|date of entry)',
            'registry' => '(?:registry|registry number)',
            'purok' => '(?:purok)',
            'grupo' => '(?:grupo|group)',
            'gender' => '(?:gender|kasarian)',
        ];
        foreach ($missingWords as $column => $words) {
            if (preg_match('/\b(?:no|without|missing|walang|wala)\s+(?:a\s+|an\s+|na\s+)?'.$words.'\b/u', $text) === 1) {
                $missing[] = $column;
            }
        }
        if ($missing !== []) {
            $filters['missing'] = $missing;
            if (in_array('purok', $missing, true)) {
                unset($filters['purok']);
            }
            if (in_array('grupo', $missing, true)) {
                unset($filters['grupo']);
            }
            if (in_array('date_of_baptism', $missing, true)) {
                unset($filters['baptism_year']);
            }
        }

        return $filters;
    }

    /** @return array{from: ?int, to: ?int, label: string}|null */
    private function yearRange(string $text, string $words): ?array
    {
        $thisYear = (int) Carbon::now('Asia/Manila')->year;
        if (preg_match('/\b'.$words.'\b[^.?!]*?\b(?:this year|ngayong taon)\b/u', $text) === 1) {
            return ['from' => $thisYear, 'to' => $thisYear, 'label' => 'in '.$thisYear];
        }
        if (preg_match('/\b'.$words.'\b[^.?!]*?\b(?:last year|noong nakaraang taon)\b/u', $text) === 1) {
            return ['from' => $thisYear - 1, 'to' => $thisYear - 1, 'label' => 'in '.($thisYear - 1)];
        }
        if (preg_match('/\b'.$words.'\b[^.?!]*?\b(?:between|from)\s+(\d{4})\s+(?:and|to|-|hanggang)\s+(\d{4})\b/u', $text, $m) === 1) {
            return ['from' => (int) $m[1], 'to' => (int) $m[2], 'label' => "between {$m[1]} and {$m[2]}"];
        }
        if (preg_match('/\b'.$words.'\b[^.?!]*?\b(?:before|bago)\s+(\d{4})\b/u', $text, $m) === 1) {
            return ['from' => null, 'to' => (int) $m[1] - 1, 'label' => 'before '.$m[1]];
        }
        if (preg_match('/\b'.$words.'\b[^.?!]*?\b(?:after|since|from|mula)\s+(\d{4})\b/u', $text, $m) === 1) {
            $from = str_contains($m[0], 'after') ? (int) $m[1] + 1 : (int) $m[1];

            return ['from' => $from, 'to' => null, 'label' => (str_contains($m[0], 'after') ? 'after ' : 'since ').$m[1]];
        }
        if (preg_match('/\b'.$words.'\b[^.?!]*?\b(?:in|noong|year|taong)\s+(\d{4})\b/u', $text, $m) === 1) {
            return ['from' => (int) $m[1], 'to' => (int) $m[1], 'label' => 'in '.$m[1]];
        }

        return null;
    }

    /** @return array{min: ?int, max: ?int, label: string}|null */
    private function ageRange(string $text): ?array
    {
        if (preg_match('/\b(?:minors?|menor de edad)\b/u', $text) === 1) {
            return ['min' => null, 'max' => 17, 'label' => 'under 18'];
        }
        if (preg_match('/\b(?:seniors?|senior citizens?|matatanda)\b/u', $text) === 1) {
            return ['min' => 60, 'max' => null, 'label' => '60 or older'];
        }
        if (preg_match('/\b(?:aged?|ages|edad)\s+(\d{1,3})\s*(?:to|-|and|hanggang)\s*(\d{1,3})\b|\bbetween\s+(\d{1,3})\s+and\s+(\d{1,3})\s+(?:years old|yrs|taong gulang)\b/u', $text, $m) === 1) {
            $low = (int) ($m[1] !== '' ? $m[1] : $m[3]);
            $high = (int) ($m[2] !== '' ? $m[2] : $m[4]);

            return ['min' => min($low, $high), 'max' => max($low, $high), 'label' => 'aged '.min($low, $high).'–'.max($low, $high)];
        }
        if (preg_match('/\b(?:under|below|younger than|less than|wala pang)\s+(\d{1,3})(?:\s*(?:years old|yrs|y\/o|taong gulang))?/u', $text, $m) === 1
            && preg_match('/\b(?:age|aged|years old|yrs|y\/o|taong gulang|edad|old|younger)\b|\bunder\s+\d{1,3}\b/u', $text) === 1) {
            return ['min' => null, 'max' => (int) $m[1] - 1, 'label' => 'under '.$m[1]];
        }
        if (preg_match('/\b(?:over|above|older than|more than|lagpas)\s+(\d{1,3})(?:\s*(?:years old|yrs|y\/o|taong gulang))?/u', $text, $m) === 1
            && preg_match('/\b(?:age|aged|years old|yrs|y\/o|taong gulang|edad|old|older)\b/u', $text) === 1) {
            return ['min' => (int) $m[1] + 1, 'max' => null, 'label' => 'over '.$m[1]];
        }

        return null;
    }

    // ---------------------------------------------------------------- answering

    /** @return Collection<int, Record> */
    private function matching(array $filters): Collection
    {
        $puroks = Purok::query()->orderBy('purok_number')->orderBy('grupo_start')->orderBy('id')->get();
        $requirements = isset($filters['eligible']) ? GlobalVariable::query()->first() : null;
        $today = Carbon::now('Asia/Manila')->startOfDay();
        $statuses = $filters['status'] ?? (! empty($filters['all_statuses']) ? self::STATUSES : ['MS', 'UWP', 'MK']);

        return Record::query()
            ->orderBy('full_name')
            ->get()
            ->filter(function (Record $record) use ($filters, $statuses, $puroks, $requirements, $today): bool {
                $status = mb_strtoupper(trim((string) $record->status));
                if (! in_array($status, $statuses, true)) {
                    return false;
                }
                if (isset($filters['purok']) && ! $this->inAnyPurok($record, $filters['purok'], $puroks)) {
                    return false;
                }
                if (isset($filters['grupo']) && ! in_array((int) $record->grupo, $filters['grupo'], true)) {
                    return false;
                }
                if (isset($filters['kapisanan']) && $this->kapisanan($record) !== $filters['kapisanan']) {
                    return false;
                }
                if (isset($filters['gender']) && $this->gender($record) !== $filters['gender']) {
                    return false;
                }
                if (isset($filters['married']) && (bool) $record->is_married !== $filters['married']) {
                    return false;
                }
                if (isset($filters['endorsement'])) {
                    $endorsed = mb_strtoupper(trim((string) $record->r2_10_endorsement)) === 'ENDORSED';
                    if ($endorsed !== $filters['endorsement']) {
                        return false;
                    }
                }
                if (! empty($filters['eligible']) && ! $this->eligibility->forRecord($record, $requirements)) {
                    return false;
                }
                foreach (['baptism' => 'date_of_baptism', 'entry' => 'date_of_entry'] as $field => $column) {
                    if (! isset($filters[$field.'_year'])) {
                        continue;
                    }
                    $year = $record->{$column}?->year;
                    $range = $filters[$field.'_year'];
                    if ($year === null || ($range['from'] !== null && $year < $range['from']) || ($range['to'] !== null && $year > $range['to'])) {
                        return false;
                    }
                }
                if (isset($filters['age'])) {
                    $age = $record->date_of_birth?->diffInYears($today);
                    $age = $age === null ? null : (int) floor($age);
                    $range = $filters['age'];
                    if ($age === null || ($range['min'] !== null && $age < $range['min']) || ($range['max'] !== null && $age > $range['max'])) {
                        return false;
                    }
                }
                if (isset($filters['rfid']) && (mb_strtoupper(trim((string) $record->registry)) === 'RFID') !== $filters['rfid']) {
                    return false;
                }
                if (! empty($filters['monthly_code']) && trim((string) $record->monthly_code) === '') {
                    return false;
                }
                foreach ($filters['missing'] ?? [] as $column) {
                    if (trim((string) ($record->getRawOriginal($column) ?? '')) !== '') {
                        return false;
                    }
                }

                return true;
            })
            ->values();
    }

    private function inAnyPurok(Record $record, array $wanted, Collection $puroks): bool
    {
        $number = (int) preg_replace('/\D+/', '', (string) $record->purok);
        foreach ($wanted as $purok) {
            if ($number !== $purok['number']) {
                continue;
            }
            if ($purok['segment'] === '') {
                return true;
            }
            $segment = $this->segmentFor($puroks, $number, $purok['segment']);
            if ($segment === null || $this->grupoInSegment((int) $record->grupo, $segment)) {
                return true;
            }
        }

        return false;
    }

    private function segmentFor(Collection $puroks, int $number, string $letter): ?Purok
    {
        return $puroks->first(fn (Purok $purok): bool => (int) $purok->purok_number === $number
            && str_ends_with(mb_strtolower(preg_replace('/\s+/', '', (string) $purok->purok_name) ?? ''), $number.$letter));
    }

    private function grupoInSegment(int $grupo, Purok $segment): bool
    {
        return $segment->full_purok || ($grupo >= (int) $segment->grupo_start && $grupo <= (int) $segment->grupo_end);
    }

    private function segmentName(Record $record, Collection $puroks): string
    {
        $number = (int) preg_replace('/\D+/', '', (string) $record->purok);
        if ($number === 0) {
            return 'No Purok';
        }
        $segments = $puroks->where('purok_number', $number);
        $segment = $segments->first(fn (Purok $purok): bool => $this->grupoInSegment((int) $record->grupo, $purok));

        return $segment?->purok_name ?: 'Purok '.$number;
    }

    private function kapisanan(Record $record): ?string
    {
        return match (mb_strtolower(trim((string) $record->kapisanan))) {
            'b', 'buklod' => 'buklod',
            'k', 'kadiwa' => 'kadiwa',
            'binhi' => 'binhi',
            default => null,
        };
    }

    private function gender(Record $record): ?string
    {
        return match (mb_strtoupper(trim((string) $record->gender))) {
            'L', 'MALE' => 'Male',
            'B', 'FEMALE' => 'Female',
            default => null,
        };
    }

    private function subject(array $filters): string
    {
        $statuses = $filters['status'] ?? [];
        $status = match (true) {
            $statuses === [] && ! empty($filters['all_statuses']) => '',
            $statuses === [] => 'active',
            $statuses === ['MS', 'UWP', 'MK'] => 'active',
            default => implode(' and ', $statuses),
        };
        $kapisanan = isset($filters['kapisanan']) ? ucfirst($filters['kapisanan']) : '';

        return trim($status.' '.$kapisanan);
    }

    /** @return array{location: array<int, string>, traits: array<int, string>, r210: string} */
    private function conditions(array $filters): array
    {
        $location = [];
        if (isset($filters['purok'])) {
            $location[] = 'Purok '.implode(', ', array_map(
                fn (array $purok): string => $purok['number'].mb_strtoupper($purok['segment']),
                $filters['purok']
            ));
        }
        if (isset($filters['grupo'])) {
            $location[] = 'Grupo '.implode(', ', $filters['grupo']);
        }

        $traits = [];
        if (isset($filters['gender'])) {
            $traits[] = mb_strtolower($filters['gender']);
        }
        if (isset($filters['married'])) {
            $traits[] = $filters['married'] ? 'married' : 'single';
        }
        if (isset($filters['age'])) {
            $traits[] = $filters['age']['label'];
        }
        if (isset($filters['baptism_year'])) {
            $traits[] = 'baptized '.$filters['baptism_year']['label'];
        }
        if (isset($filters['entry_year'])) {
            $traits[] = 'entered '.$filters['entry_year']['label'];
        }
        if (isset($filters['rfid'])) {
            $traits[] = $filters['rfid'] ? 'with RFID' : 'without RFID';
        }
        if (! empty($filters['monthly_code'])) {
            $traits[] = 'with a pending monthly code';
        }
        foreach ($filters['missing'] ?? [] as $column) {
            $traits[] = 'no '.str_replace(['date_of_', '_'], ['', ' '], $column).($column === 'date_of_birth' ? ' date' : '');
        }

        $r210 = '';
        if (! empty($filters['eligible']) && ($filters['endorsement'] ?? null) === false) {
            $r210 = ' eligible for R2-10 that are not yet endorsed';
        } elseif (! empty($filters['eligible'])) {
            $r210 = ' eligible for R2-10';
        } elseif (($filters['endorsement'] ?? null) === false) {
            $r210 = ' that are not yet endorsed for R2-10';
        } elseif (($filters['endorsement'] ?? null) === true) {
            $r210 = ' that are endorsed for R2-10';
        }

        return ['location' => $location, 'traits' => $traits, 'r210' => $r210];
    }

    private function describe(int $count, string $subject, array $conditions): string
    {
        $noun = $count === 1 ? 'record' : 'records';
        $text = trim($subject.' '.$noun);
        if ($conditions['location'] !== []) {
            $text .= ' in '.implode(', ', $conditions['location']);
        }
        if ($conditions['traits'] !== []) {
            $text .= ' ('.implode(', ', $conditions['traits']).')';
        }

        $r210 = $count === 1 ? str_replace(' that are ', ' that is ', $conditions['r210']) : $conditions['r210'];

        return $text.$r210;
    }

    private function countReply(int $count, string $subject, array $conditions, array $filters): string
    {
        if ($count === 0) {
            return 'I found no '.$this->describe(2, $subject, $conditions).'.';
        }

        return ($count === 1 ? 'There is 1 ' : "There are {$count} ").$this->describe($count, $subject, $conditions).'.';
    }

    private function breakdownReply(Collection $records, string $dimension, string $subject, array $conditions): string
    {
        $dimension = in_array($dimension, self::DIMENSIONS, true) ? $dimension : 'purok';
        $puroks = $dimension === 'purok' ? Purok::query()->orderBy('purok_number')->orderBy('grupo_start')->orderBy('id')->get() : collect();
        $groups = $records->groupBy(fn (Record $record): string => match ($dimension) {
            'purok' => $this->segmentName($record, $puroks),
            'grupo' => trim((string) $record->grupo) !== '' ? 'Grupo '.(int) $record->grupo : 'No Grupo',
            'status' => mb_strtoupper(trim((string) $record->status)) ?: 'No status',
            'kapisanan' => ucfirst($this->kapisanan($record) ?? 'not recorded'),
            'gender' => $this->gender($record) ?? 'Not recorded',
        });
        $label = ['purok' => 'Purok', 'grupo' => 'Grupo', 'status' => 'status', 'kapisanan' => 'Kapisanan', 'gender' => 'gender'][$dimension];

        if ($records->isEmpty()) {
            return 'I found no '.$this->describe(2, $subject, $conditions).' to break down.';
        }

        $lines = $groups
            ->map(fn (Collection $group, string $name): array => ['name' => $name, 'count' => $group->count()])
            ->sortBy(fn (array $row): string => str_pad((string) (int) preg_replace('/\D+/', '', $row['name']), 4, '0', STR_PAD_LEFT).$row['name'])
            ->map(fn (array $row): string => '• '.$row['name'].': '.$row['count'])
            ->implode("\n");

        return 'Here is the breakdown of '.$this->describe($records->count(), $subject, $conditions)
            ." by {$label} (total {$records->count()}):\n".$lines;
    }

    private function listReply(Collection $records, string $subject, array $conditions): string
    {
        if ($records->isEmpty()) {
            return 'I found no '.$this->describe(2, $subject, $conditions).'.';
        }

        $shown = $records->take(self::LIST_LIMIT)->map(function (Record $record): string {
            $place = trim((string) $record->purok) !== ''
                ? 'Purok '.trim((string) $record->purok).(trim((string) $record->grupo) !== '' ? '-'.trim((string) $record->grupo) : '')
                : 'no Purok';

            return '• '.(trim((string) $record->full_name) ?: 'Unnamed record').' — '.(trim((string) $record->status) ?: 'no status').', '.$place;
        })->implode("\n");
        $total = $records->count();
        $heading = 'I found '.$total.' '.$this->describe($total, $subject, $conditions)
            .($total > self::LIST_LIMIT ? '. Here are the first '.self::LIST_LIMIT.' (use Inquire / Modify for the full list):' : ':');

        return $heading."\n".$shown;
    }
}
