<?php

namespace App\Services;

use App\Models\Purok;
use Illuminate\Validation\ValidationException;

/**
 * A parsed R4-01 / R4-02 Purok filter.
 *
 * Tokens are comma separated:
 *  - "all"   every Purok segment;
 *  - "N"     every segment configured for purok_number N (legacy links keep working);
 *  - "N:ID"  only the segment puroks.id = ID, whose purok_number must be N (e.g. Purok 2a vs 2b).
 */
final class PurokSelection
{
    public const MAX_LENGTH = 200;

    private const MAX_TOKENS = 50;

    /**
     * @param  list<int>  $numbers  purok numbers selected with every segment
     * @param  list<int>  $segmentIds  individually selected segment ids
     * @param  list<int>  $segmentNumbers  purok numbers owning the selected segment ids
     */
    private function __construct(
        public readonly bool $all,
        public readonly array $numbers,
        public readonly array $segmentIds,
        public readonly array $segmentNumbers,
    ) {}

    public static function all(): self
    {
        return new self(true, [], [], []);
    }

    /**
     * Lenient conversion for internal callers that pass the historical string argument ("all" or "N[,N]").
     * Request input must go through fromRequestValue() instead so it is validated against the database.
     */
    public static function fromLegacyString(string $purok): self
    {
        $tokens = array_values(array_filter(array_map('trim', explode(',', $purok)), fn (string $token): bool => $token !== ''));
        if ($tokens === [] || in_array('all', $tokens, true)) {
            return self::all();
        }

        $numbers = [];
        $segmentIds = [];
        $segmentNumbers = [];
        foreach ($tokens as $token) {
            if (preg_match('/^(\d{1,6}):(\d{1,10})$/', $token, $match) === 1) {
                $segmentIds[] = (int) $match[2];
                $segmentNumbers[] = (int) $match[1];

                continue;
            }
            $numbers[] = (int) preg_replace('/\D+/', '', $token);
        }

        return new self(false, array_values(array_unique($numbers)), array_values(array_unique($segmentIds)), array_values(array_unique($segmentNumbers)));
    }

    /**
     * Validate a request value against the configured puroks.
     *
     * @throws ValidationException
     */
    public static function fromRequestValue(mixed $value, string $field = 'purok'): self
    {
        if (! is_string($value) && ! is_int($value)) {
            throw ValidationException::withMessages([$field => 'Select a valid Purok.']);
        }
        $value = trim((string) $value);
        if ($value === '' || mb_strlen($value) > self::MAX_LENGTH) {
            throw ValidationException::withMessages([$field => 'Select a valid Purok.']);
        }

        $tokens = array_map('trim', explode(',', $value));
        if (count($tokens) > self::MAX_TOKENS) {
            throw ValidationException::withMessages([$field => 'Too many Puroks were selected.']);
        }
        if (in_array('all', $tokens, true)) {
            if (count($tokens) > 1) {
                throw ValidationException::withMessages([$field => 'Select either All Purok or specific Puroks, not both.']);
            }

            return self::all();
        }

        $numbers = [];
        $segments = [];
        foreach ($tokens as $token) {
            if (preg_match('/^\d{1,6}$/', $token) === 1) {
                $numbers[(int) $token] = (int) $token;

                continue;
            }
            if (preg_match('/^(\d{1,6}):(\d{1,10})$/', $token, $match) === 1) {
                $segments[(int) $match[2]] = (int) $match[1];

                continue;
            }
            throw ValidationException::withMessages([$field => 'Select a valid Purok.']);
        }

        if ($numbers !== []) {
            $knownNumbers = Purok::query()
                ->whereIn('purok_number', array_values($numbers))
                ->distinct()
                ->pluck('purok_number')
                ->map(fn ($number): int => (int) $number)
                ->all();
            if (array_diff(array_values($numbers), $knownNumbers) !== []) {
                throw ValidationException::withMessages([$field => 'The selected Purok does not exist.']);
            }
        }
        if ($segments !== []) {
            $knownSegments = Purok::query()
                ->whereIn('id', array_keys($segments))
                ->pluck('purok_number', 'id');
            foreach ($segments as $segmentId => $number) {
                if (! $knownSegments->has($segmentId) || (int) $knownSegments[$segmentId] !== $number) {
                    throw ValidationException::withMessages([$field => 'The selected Purok segment does not exist.']);
                }
            }
        }

        return new self(false, array_values($numbers), array_keys($segments), array_values(array_unique(array_values($segments))));
    }

    /**
     * Whether rows of this purok number need to be considered at all.
     */
    public function includesNumber(int $number): bool
    {
        return $this->all
            || in_array($number, $this->numbers, true)
            || in_array($number, $this->segmentNumbers, true);
    }

    /**
     * Whether a configured segment (or the unconfigured null segment) of a purok number is selected.
     */
    public function includesSegment(int $number, ?int $segmentId): bool
    {
        if ($this->all || in_array($number, $this->numbers, true)) {
            return true;
        }

        return $segmentId !== null && in_array($segmentId, $this->segmentIds, true);
    }

    /**
     * Ids of every configured segment this selection covers, ordered like the reports.
     *
     * @return list<int>
     */
    public function coveredSegmentIds(): array
    {
        return Purok::query()
            ->when(! $this->all, fn ($query) => $query->where(function ($query): void {
                $query->whereIn('purok_number', $this->numbers === [] ? [-1] : $this->numbers)
                    ->orWhereIn('id', $this->segmentIds === [] ? [-1] : $this->segmentIds);
            }))
            ->orderBy('purok_number')
            ->orderBy('grupo_start')
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * The token the UI uses for one segment: "N:ID" when purok number N is split into several segments, otherwise "N".
     */
    public static function tokenFor(int $number, ?int $segmentId, int $segmentsForNumber): string
    {
        return $segmentId !== null && $segmentsForNumber > 1 ? $number.':'.$segmentId : (string) $number;
    }
}
