<?php

namespace App\Services;

use App\Models\GlobalVariable;
use App\Models\Purok;
use App\Models\Record;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Turns a plain-language record question into a fixed set of filters and
 * answers it with SQL aggregates (COUNT / GROUP BY). User text never becomes
 * SQL: it only selects from a closed list of conditions, and every value that
 * reaches the database is a bound parameter.
 *
 * Understands, in English, Filipino and Taglish (with common typos):
 *   counts       "how many MS in purok 1 grupo 3?", "ilang babae ang uwp"
 *   breakdowns   "MS per purok", "members by age group", "birthdays per month"
 *   lists        "list the buklod in purok 2a", "show 5 uwp", then "more"
 *   comparisons  "purok 1 vs purok 2", "compare male and female ms"
 *   percentages  "what percent of ms are female?"
 *   rankings     "which purok has the most ms?"
 *   extremes     "who is the oldest member in purok 1?", "latest baptized"
 *   averages     "average age of kadiwa"
 * plus follow-ups that reuse the previous conditions ("how about purok 2?",
 * "and females?", "list them", "per grupo", "what percentage is that?").
 */
class LspBotRecordQuery
{
    public const LIST_LIMIT = 15;

    public const MAX_LIMIT = 50;

    /** Bumped when the stored spec shape changes, so an old session spec is not reused. */
    private const VERSION = 2;

    private const STATUSES = ['MS', 'UWP', 'MK', 'INACTIVE'];

    private const ACTIVE = ['MS', 'UWP', 'MK'];

    private const DIMENSIONS = [
        'purok', 'grupo', 'status', 'kapisanan', 'gender', 'married', 'age', 'birth_month',
        'baptism_year', 'entry_year', 'endorsement', 'tarheta',
    ];

    private const DIMENSION_LABELS = [
        'purok' => 'Purok', 'grupo' => 'Grupo', 'status' => 'status', 'kapisanan' => 'Kapisanan', 'gender' => 'gender',
        'married' => 'civil status', 'age' => 'age group', 'birth_month' => 'birth month', 'baptism_year' => 'baptism year',
        'entry_year' => 'entry year', 'endorsement' => 'R2-10 endorsement', 'tarheta' => 'Tarheta status',
    ];

    private const MONTHS = [
        'january' => 1, 'february' => 2, 'march' => 3, 'april' => 4, 'may' => 5, 'june' => 6, 'july' => 7,
        'august' => 8, 'september' => 9, 'october' => 10, 'november' => 11, 'december' => 12,
        'jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'jun' => 6, 'jul' => 7, 'aug' => 8, 'sep' => 9, 'sept' => 9,
        'oct' => 10, 'nov' => 11, 'dec' => 12,
        'enero' => 1, 'pebrero' => 2, 'marso' => 3, 'abril' => 4, 'mayo' => 5, 'hunyo' => 6, 'hulyo' => 7,
        'agosto' => 8, 'setyembre' => 9, 'oktubre' => 10, 'nobyembre' => 11, 'disyembre' => 12,
    ];

    /** Words that are fixed even when misspelled by one letter (or two swapped letters). */
    private const VOCABULARY = [
        'purok', 'puroks', 'grupo', 'members', 'member', 'records', 'record', 'female', 'females', 'married', 'single',
        'inactive', 'baptized', 'baptism', 'kapisanan', 'buklod', 'kadiwa', 'binhi', 'breakdown', 'percentage', 'percent',
        'compare', 'versus', 'endorsed', 'endorsement', 'eligible', 'birthday', 'birthdays', 'between', 'oldest',
        'youngest', 'seniors', 'minors', 'gender', 'status', 'registry', 'missing', 'without', 'entered', 'average',
        'january', 'february', 'march', 'april', 'june', 'july', 'august', 'september', 'october', 'november', 'december',
    ];

    /** Short typos that the distance rule cannot safely fix. */
    private const TYPOS = [
        'hw' => 'how', 'hwo' => 'how', 'hoe' => 'how', 'hiw' => 'how', 'haw' => 'how', 'mny' => 'many', 'mant' => 'many',
        'mamy' => 'many', 'meny' => 'many', 'manny' => 'many', 'prk' => 'purok', 'prok' => 'purok', 'purk' => 'purok',
        'puruk' => 'purok', 'grp' => 'grupo', 'grupu' => 'grupo', 'ilng' => 'ilang', 'iln' => 'ilan', 'pls' => 'please',
        'femal' => 'female', 'mles' => 'males', 'membrs' => 'members', 'mems' => 'members', 'recs' => 'records',
        'lsit' => 'list', 'lst' => 'list', 'shw' => 'show', 'ttl' => 'total', 'cnt' => 'count',
    ];

    /** Real words one letter away from a vocabulary word, never "fixed". */
    private const REAL_WORDS = ['match', 'sender', 'tender', 'render', 'carried', 'kissing', 'statue', 'mirrors', 'grupos', 'groups'];

    private const NOT_RECORD_TOPICS = '/\b(?:tasks?|to-?dos?|messages?|chats?|backups?|forms?|sessions?|secretaries|notes?|passwords?|accounts?|times)\b/u';

    /** @var array<string, array<int, mixed>> distinct raw values per column, for this request */
    private array $distinct = [];

    private ?Collection $purokSettings = null;

    /**
     * The question as a query spec, or null when it is not a record question.
     * $previous is the last spec, used for follow-ups like "what about purok 2?".
     * $context carries the asking user's id (for "my purok").
     */
    public function parse(string $message, ?array $previous = null, array $context = []): ?array
    {
        if ($previous !== null && ($previous['version'] ?? null) !== self::VERSION) {
            $previous = null;
        }
        $text = $this->normalize($message);
        if ($text === '') {
            return null;
        }

        $filters = $this->filters($text, $context);
        $dimension = $this->dimension($text);
        $intent = $this->intent($text, $dimension);
        $words = count(explode(' ', $text));

        if ($previous !== null) {
            $followUp = $this->followUp($text, $intent, $filters, $dimension, $words, $previous);
            if ($followUp !== null) {
                return $followUp;
            }
        }

        if ($intent === null && $dimension !== null && $this->mentionsRecords($text, $filters)) {
            $intent = 'breakdown';
        }
        // A bare condition such as "ms purok 1" or "female kadiwa?" is read as a count.
        if ($intent === null && $dimension === null && $this->isBareCondition($text, $filters, $words)) {
            $intent = 'count';
        }
        // "Birthdays this month" / "celebrants today" wants the names.
        if ($intent === null && $dimension === null && isset($filters['birthday']) && $words <= 8) {
            $intent = 'list';
        }
        // "how many males and females" is a gender breakdown rather than one number.
        if ($intent === 'count' && $dimension === null && ! isset($filters['gender']) && $this->mentionsBothGenders($text)) {
            $dimension = 'gender';
        }
        if ($intent === null) {
            return null;
        }
        if ($filters === [] && $dimension === null && ! $this->mentionsRecords($text, $filters)) {
            return null;
        }
        // "How many tasks are due?" or "how many forms..." are not record questions.
        if ($filters === [] && preg_match(self::NOT_RECORD_TOPICS, $text) === 1 && preg_match('/\b(?:records?|members?|kapatid|miyembro|ms|uwp|mk)\b/u', $text) !== 1) {
            return null;
        }
        if ($dimension !== null && in_array($intent, ['count', 'list'], true)) {
            $intent = 'breakdown';
        }
        if ($intent === 'rank' && $dimension === null) {
            $intent = 'count';
        }

        return $this->spec($intent, $dimension, $filters, $text);
    }

    /** True when answering $spec needs R2-10 (De-Listment) access. */
    public function needsDelistmentAccess(array $spec): bool
    {
        return array_key_exists('Delistment_allow', $this->requiredPermissions($spec));
    }

    /**
     * Permissions the spec needs beyond record lookups, each with the reason shown when it is missing.
     *
     * @return array<string, string>
     */
    public function requiredPermissions(array $spec): array
    {
        $needs = [];
        foreach ($this->allFilterSets($spec) as $filters) {
            if (isset($filters['endorsement']) || ! empty($filters['eligible']) || isset($filters['delisted']) || isset($filters['endorsed'])) {
                $needs['Delistment_allow'] = 'Your account cannot view R2-10 endorsement information. Ask your Head Secretary to enable De-Listment access.';
            }
            if (isset($filters['tarheta']) || isset($filters['filing'])) {
                $needs['tarheta_status_allow'] = 'Your account cannot view Tarheta and filing details. Ask your Head Secretary to enable Tarheta Status Checker access.';
            }
        }
        if (($spec['dimension'] ?? null) === 'endorsement') {
            $needs['Delistment_allow'] = 'Your account cannot view R2-10 endorsement information. Ask your Head Secretary to enable De-Listment access.';
        }
        if (($spec['dimension'] ?? null) === 'tarheta') {
            $needs['tarheta_status_allow'] = 'Your account cannot view Tarheta and filing details. Ask your Head Secretary to enable Tarheta Status Checker access.';
        }

        return $needs;
    }

    /**
     * True when the answer would list MK member names (confidential), or would single out MK
     * individuals: "how many mk named gil" confirms who is MK as surely as a list does.
     */
    public function listsMkNames(array $spec): bool
    {
        foreach ($this->mkSensitiveSets($spec) as $filters) {
            if (in_array('MK', $this->statusesOf($filters), true)) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, array> the filter sets that name or single out people, keyed by where they sit in the spec */
    private function mkSensitiveSets(array $spec): array
    {
        $sets = [];
        $singlesOut = fn (array $filters): bool => isset($filters['name']) || isset($filters['evangelist']);
        if (in_array($spec['intent'], ['list', 'extreme'], true) || $singlesOut($spec['filters'])) {
            $sets['filters'] = $spec['filters'];
        }
        foreach ($spec['compare'] ?? [] as $i => $part) {
            if ($singlesOut($part['filters'])) {
                $sets['compare.'.$i] = $part['filters'];
            }
        }
        if (isset($spec['base']) && $singlesOut($spec['base'])) {
            $sets['base'] = $spec['base'];
        }

        return $sets;
    }

    /** @return list<string> */
    private function statusesOf(array $filters): array
    {
        return $filters['status'] ?? (! empty($filters['all_statuses']) ? self::STATUSES : self::ACTIVE);
    }

    public function answer(array $spec): string
    {
        $filters = $spec['filters'];
        $subject = $this->subject($filters);
        $conditions = $this->conditions($filters);

        if (isset($filters['secretary']) && $this->secretaryPuroks($filters['secretary']['ids']) === []) {
            return $filters['secretary']['name'] === 'you'
                ? 'You are not assigned as the Secretary of any Purok yet, so I cannot tell which records are yours. The Head Secretary sets this in Superadmin Dashboard → Purok signatories.'
                : "{$filters['secretary']['name']} is not assigned as the Secretary of any Purok yet. The Head Secretary sets this in Superadmin Dashboard → Purok signatories.";
        }

        return match ($spec['intent']) {
            'breakdown', 'rank' => $this->breakdownReply($spec, $subject, $conditions),
            'list' => $this->listReply($spec, $subject, $conditions),
            'compare' => $this->compareReply($spec),
            'percent' => $this->percentReply($spec),
            'extreme' => $this->extremeReply($spec, $subject, $conditions),
            'average' => $this->averageReply($filters, $subject, $conditions),
            default => $this->countReply($this->query($filters)->count(), $subject, $conditions).$this->splitLines($filters),
        }.$this->mkNote($spec);
    }

    /**
     * Short follow-ups worth offering after this answer (shown as buttons).
     *
     * @return list<string>
     */
    public function suggestions(array $spec): array
    {
        $hasPurok = isset($spec['filters']['purok']) || isset($spec['filters']['secretary']);
        $out = match ($spec['intent']) {
            'count' => ['List them', $hasPurok ? 'Per grupo' : 'Per purok', 'What percentage is that?'],
            'list' => $this->hasMorePages($spec) ? ['More', 'Per purok', 'How many?'] : ['Per purok', 'By gender'],
            'breakdown', 'rank' => ['By gender', 'By kapisanan', 'By age group'],
            'compare', 'percent' => ['Per purok', 'By gender'],
            'extreme' => ['Average age', 'By age group'],
            'average' => ['By age group', 'Who is the oldest?'],
            default => [],
        };
        $current = self::DIMENSION_LABELS[$spec['dimension'] ?? ''] ?? null;

        return array_values(array_filter($out, fn (string $s): bool => $current === null || ! str_ends_with(mb_strtolower($s), mb_strtolower($current))));
    }

    /**
     * Same question with MK records left out, for an account that may count MK records but not see
     * their names. Returns null when the question is only about MK (then it is refused instead).
     */
    public function withoutMkNames(array $spec): ?array
    {
        foreach ($this->mkSensitiveSets($spec) as $where => $filters) {
            $remaining = array_values(array_diff($this->statusesOf($filters), ['MK']));
            if ($remaining === []) {
                return null;
            }
            $filters['status'] = $remaining;
            unset($filters['all_statuses']);
            if ($where === 'filters' || $where === 'base') {
                $spec[$where] = $filters;
            } else {
                $spec['compare'][(int) substr($where, 8)]['filters'] = $filters;
            }
        }
        $spec['mk_hidden'] = true;

        return $spec;
    }

    private function mkNote(array $spec): string
    {
        if (empty($spec['mk_hidden'])) {
            return '';
        }

        return in_array($spec['intent'], ['list', 'extreme'], true)
            ? "\n(MK names are confidential, so MK records are left out. Listing them needs De-Listment access.)"
            : "\n(MK names are confidential, so MK records are left out when a name is searched. That needs De-Listment access.)";
    }

    // ---------------------------------------------------------------- parsing

    private function spec(string $intent, ?string $dimension, array $filters, string $text, int $page = 1): array
    {
        $spec = [
            'version' => self::VERSION,
            'intent' => $intent,
            'dimension' => $dimension,
            'filters' => $filters,
            'page' => max(1, $page),
            'limit' => $this->limit($text, $intent),
        ];
        if ($intent === 'list' && preg_match('/\bpage\s+(\d{1,3})\b/u', $text, $m) === 1) {
            $spec['page'] = max(1, (int) $m[1]);
        }
        if ($intent === 'rank') {
            $spec['order'] = preg_match('/\b(?:least|fewest|lowest|smallest|pinakakaunti|pinakamaliit|pinakakonti)\b/u', $text) === 1 ? 'asc' : 'desc';
        }
        if ($intent === 'extreme') {
            $spec['extreme'] = $this->extremeTarget($text);
        }
        if ($intent === 'compare') {
            $spec['compare'] = $this->comparison($text, $filters);
            if (count($spec['compare']) < 2) {
                $spec['intent'] = 'count';
                unset($spec['compare']);
            }
        }
        if ($intent === 'percent') {
            $spec['base'] = $this->percentBase($text, $filters);
        }

        return $spec;
    }

    private function followUp(string $text, ?string $intent, array $filters, ?string $dimension, int $words, array $previous): ?array
    {
        $prefixed = preg_match('/^(?:how about|what about|and|eh|e|paano naman|paano sa|sa|ano naman|ilan naman|ilang naman|how many in|tapos|only|just|then|also|same for|same but|pero|ngayon)\b/u', $text) === 1;
        $carry = $previous['filters'];

        // Paging: "more", "next", "next page", "page 3", "susunod".
        if (preg_match('/^(?:more|next|next page|show more|load more|continue|susunod|iba pa|more please|more names|marami pa)\s*[?.!]*$/u', $text) === 1
            || preg_match('/^(?:show |go to )?page\s+(\d{1,3})\s*[?.!]*$/u', $text) === 1) {
            if ($previous['intent'] !== 'list') {
                return null;
            }
            $page = preg_match('/page\s+(\d{1,3})/u', $text, $m) === 1 ? (int) $m[1] : ($previous['page'] ?? 1) + 1;

            return ['page' => max(1, $page)] + $previous;
        }

        // "list them", "who are they?", "names please", "sino sila".
        if (preg_match('/^(?:(?:list|show|name|give me)\s+(?:them|those|the names|names|all|everyone)|who are they|who are those|sino sila|sino-sino sila|sinu-sino sila|names?|names please|pangalan nila|mga pangalan)\b/u', $text) === 1) {
            return $this->spec('list', null, $this->merge($carry, $filters), $text);
        }
        // "how many?", "total?", "ilan lahat?".
        if (preg_match('/^(?:how many|ilan|ilang|total|count)(?:\s+(?:all|lahat|in total|is that|are they|sila|ito|yan|po))?\s*[?.!]*$/u', $text) === 1) {
            return $this->spec('count', null, $carry, $text);
        }
        // "what percentage is that?", "ilang porsyento yan?", "who is the oldest?" reuse the last conditions;
        // "who is the oldest member?" names its own (whole) set, so it starts fresh.
        $ownSet = preg_match('/\b(?:members?|records?|everyone|everybody|lahat|kapatid|miyembro)\b/u', $text) === 1;
        if (in_array($intent, ['percent', 'average', 'extreme'], true) && $filters === [] && ! $ownSet) {
            return $this->spec($intent, null, $carry, $text);
        }
        // "all puroks" removes the Purok condition; "all statuses" the status one.
        if ($prefixed && preg_match('/\b(?:all puroks?|every purok|lahat ng purok|whole (?:locale|lokal)|buong lokal)\b/u', $text) === 1) {
            unset($carry['purok'], $carry['grupo'], $carry['secretary']);

            $next = $dimension !== null ? 'breakdown' : ($previous['intent'] === 'list' ? 'list' : 'count');

            return $this->spec($next, $dimension, $this->merge($carry, $filters), $text);
        }
        // "how about 2?" after a Purok question means Purok 2.
        if ($prefixed && $filters === [] && isset($carry['purok']) && preg_match('/\b(\d{1,2})([a-z])?\s*\??$/u', $text, $m) === 1) {
            $filters['purok'] = [['number' => (int) $m[1], 'segment' => $m[2] ?? '']];
        }

        // A short bare condition ("females?", "purok 3 naman") refines the last question; a help question does not.
        // Without "and / how about", only a member trait ("females?", "purok 3") refines; "birthdays this month" is a new question.
        $short = $words <= 6 && $intent === null
            && array_diff(array_keys($filters), ['status', 'purok', 'grupo', 'kapisanan', 'gender', 'married', 'age', 'secretary', 'exclude']) === []
            && preg_match('/^(?:where|how (?:do|can|to)|paano|saan|bakit|why|can i|pwede|puwede|what is|what are|ano ang|who|sino)\b/u', $text) !== 1;
        // "Per grupo", "by gender?" (the suggestion buttons) break the last answer down further.
        $dimensionOnly = $filters === [] && $dimension !== null
            && preg_match('/^(?:breakdown |break down |broken down |grouped |distribution )?(?:per|by|bawat|kada|each|for each|in each)\s+\S+(?:\s+\S+)?\s*[?.!]*$/u', $text) === 1;
        if (! (($prefixed || $short || $dimensionOnly) && ($filters !== [] || $dimension !== null))) {
            return null;
        }

        $merged = $this->merge($carry, $filters);
        $previousIntent = in_array($previous['intent'], ['compare', 'percent', 'extreme', 'average', 'rank'], true) ? 'count' : $previous['intent'];
        $nextIntent = $dimension !== null ? 'breakdown' : ($intent ?? $previousIntent);
        $nextDimension = $dimension ?? ($nextIntent === 'breakdown' ? $previous['dimension'] : null);
        if ($nextIntent === 'breakdown' && $nextDimension === null) {
            $nextIntent = 'count';
        }

        return $this->spec($nextIntent === 'rank' ? 'breakdown' : $nextIntent, $nextDimension, $merged, $text);
    }

    private function merge(array $carry, array $filters): array
    {
        foreach ($filters as $key => $value) {
            $carry[$key] = $value;
        }
        if (isset($filters['status'])) {
            unset($carry['all_statuses']);
        }
        if (isset($filters['purok'])) {
            unset($carry['secretary']);
        }
        if (isset($filters['secretary'])) {
            unset($carry['purok']);
        }

        return $carry;
    }

    private function normalize(string $message): string
    {
        // Case, accents, quotes, chat shorthand and number words ("purok one", "ikalawang purok").
        $text = LspBotLanguage::normalize($message);
        $text = preg_replace('/\br2\s*-?\s*10\b/u', 'r210', $text) ?? $text;
        $text = preg_replace('/(\d)\s*%/u', '$1 percent', $text) ?? $text;
        $text = str_replace('%', ' percent ', $text);
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($this->fixTypos(trim($text)));
    }

    /** Fixes "pruok", "hw many", "membrs" and similar slips; names and numbers are left alone. */
    private function fixTypos(string $text): string
    {
        return preg_replace_callback('/\p{L}+/u', function (array $match): string {
            $word = $match[0];
            if (isset(self::TYPOS[$word])) {
                return self::TYPOS[$word];
            }
            $length = strlen($word);
            if ($length < 5 || in_array($word, self::VOCABULARY, true) || in_array($word, self::REAL_WORDS, true)) {
                return $word;
            }
            foreach (self::VOCABULARY as $target) {
                $targetLength = strlen($target);
                if ($targetLength < 5 || abs($targetLength - $length) > 1) {
                    continue;
                }
                $distance = LspBotLanguage::distance($word, $target);
                if ($distance === 1 || ($distance === 2 && $length >= 8 && $targetLength >= 8)) {
                    return $target;
                }
            }

            return $word;
        }, $text) ?? $text;
    }

    private function intent(string $text, ?string $dimension): ?string
    {
        if (preg_match('/\b(?:vs\.?|versus|compare|compared|comparison|kumpara|ikumpara|paghambingin)\b/u', $text) === 1) {
            return 'compare';
        }
        if (preg_match('/\b(?:percent|percentage|porsyento|porsiyento|proportion|ratio)\b/u', $text) === 1) {
            return 'percent';
        }
        if (preg_match('/\b(?:average|mean|avg|karaniwang)\s+(?:age|edad)\b/u', $text) === 1) {
            return 'average';
        }
        if (preg_match('/\bwhich\b.*\b(?:most|least|fewest|highest|lowest|biggest|largest|smallest)\b|\b(?:pinakamarami|pinakamaraming|pinakakaunti|pinakakonti|pinakamaliit|pinakamalaki)\b/u', $text) === 1) {
            return 'rank';
        }
        if (preg_match('/\b(?:oldest|youngest|eldest|pinakamatanda|pinakabata|newest|latest|most recent(?:ly)?|recently|earliest|pinakabago|pinakauna|first (?:baptized|baptised|entered|joined))\b/u', $text) === 1
            && preg_match('/\b(?:how many|ilan|ilang|count|number of)\b/u', $text) !== 1) {
            return 'extreme';
        }
        if (preg_match('/\b(?:breakdown|break down|distribution)\b/u', $text) === 1 || $dimension !== null && preg_match('/\b(?:per|by|each|every|bawat|kada)\b/u', $text) === 1) {
            return 'breakdown';
        }
        if (preg_match('/^(?:list|show|display|give me|ipakita|ilista|sino|sino-sino|sinu-sino|who|top|first)\b|\b(?:list of|names of|who are|sino ang|sino-sino ang|sinu-sino ang|pangalan ng)\b/u', $text) === 1) {
            return 'list';
        }
        if (preg_match('/\b(?:how many|how much|count|number of|total|ilan|ilang|bilang ng|gaano karami|ilan lahat)\b/u', $text) === 1) {
            return 'count';
        }

        return null;
    }

    private function limit(string $text, string $intent): int
    {
        if (preg_match('/\b(?:top|first|last|list|show|give me|ipakita|ilista)\s+(\d{1,2})\b|\b(\d{1,2})\s+(?:oldest|youngest|eldest|newest|latest|most recent|earliest|names|members|records)\b/u', $text, $m) === 1) {
            $n = (int) ($m[1] !== '' ? $m[1] : $m[2]);
            if ($n >= 1) {
                return min(self::MAX_LIMIT, $n);
            }
        }
        if ($intent === 'extreme') {
            return preg_match('/\b(?:oldest|youngest|eldest|newest|latest|earliest|recent(?:ly)?)\s+(?:\w+\s+)?(?:members|records|people|kapatid|ones|baptisms|entries)\b|\bmga\b/u', $text) === 1 ? 5 : 1;
        }

        return self::LIST_LIMIT;
    }

    private function dimension(string $text): ?string
    {
        $patterns = [
            'age' => '(?:age groups?|age brackets?|age ranges?|ages|age|edad)',
            'birth_month' => '(?:birth months?|birthday months?|birthdays? per month|month of birth|kaarawan)',
            'baptism_year' => '(?:baptism years?|year (?:of )?baptism|year baptized)',
            'entry_year' => '(?:entry years?|year (?:of )?entry|year entered)',
            'married' => '(?:civil status|marital status|married or single)',
            'endorsement' => '(?:endorsements?|endorsement status|r210 status)',
            'tarheta' => '(?:tarheta(?: status)?)',
            'kapisanan' => '(?:kapisanan|kapisanans)',
            'gender' => '(?:gender|sex|kasarian)',
            'status' => '(?:status|statuses|kalagayan|category|categories)',
            'grupo' => '(?:grupos?|groups?)',
            'purok' => '(?:puroks?)',
        ];
        foreach ($patterns as $dimension => $words) {
            if (preg_match('/\b(?:per|by|each|every|bawat|kada|in each|for each|breakdown of|breakdown by|broken down by|distribution by|grouped by|according to)\s+'.$words.'\b/u', $text) === 1) {
                return $dimension;
            }
            if (preg_match('/\b(?:which|what|anong|aling)\s+'.$words.'\b/u', $text) === 1 && preg_match('/\b(?:most|least|fewest|highest|lowest|biggest|smallest|pinaka\w*)\b/u', $text) === 1) {
                return $dimension;
            }
        }
        if (preg_match('/\b(?:baptisms?|baptized|bautismo) (?:per|by|each|every) year\b|\byearly baptisms?\b/u', $text) === 1) {
            return 'baptism_year';
        }
        if (preg_match('/\b(?:entries|entered|joined) (?:per|by|each|every) year\b/u', $text) === 1) {
            return 'entry_year';
        }
        if (preg_match('/\b(?:birthdays?|celebrants?) (?:per|by|each|every) month\b/u', $text) === 1) {
            return 'birth_month';
        }
        if (preg_match('/\bmale (?:vs\.?|and|or) female breakdown\b|\bgender breakdown\b/u', $text) === 1) {
            return 'gender';
        }
        if (preg_match('/\bpurok breakdown\b|\bpurok by purok\b/u', $text) === 1) {
            return 'purok';
        }

        return null;
    }

    /** "ms purok 1", "female kadiwa", "uwp sa purok 2": only conditions, no help or action words. */
    private function isBareCondition(string $text, array $filters, int $words): bool
    {
        if ($words > 6 || array_intersect_key($filters, array_flip(['status', 'purok', 'grupo', 'kapisanan', 'gender', 'secretary'])) === []) {
            return false;
        }

        return preg_match('/\b(?:how|paano|where|saan|why|bakit|can|could|pwede|puwede|what|ano|anong|does|mean|meaning|ibig|explain|mark|endorse|delist|subtract|add|edit|change|print|export|download|who|sino|when|kailan|is|are|ay|i)\b/u', $text) !== 1;
    }

    private function mentionsBothGenders(string $text): bool
    {
        return preg_match('/\b(?:females?|women|woman|ladies|sisters?|babae|kababaihan)\b/u', $text) === 1
            && preg_match('/\b(?:males?|men|man|gentlemen|brothers?|lalaki|kalalakihan)\b/u', $text) === 1;
    }

    private function mentionsRecords(string $text, array $filters): bool
    {
        return $filters !== []
            || preg_match('/\b(?:records?|members?|kapatid|miyembro|people|persons?|tao|katao|everyone|lahat|ms|uwp|mk|baptisms?|celebrants?)\b/u', $text) === 1;
    }

    private function filters(string $text, array $context = []): array
    {
        $filters = [];

        // Negation: "not in purok 1", "except binhi", "maliban sa purok 2", "non-ms", "hindi uwp".
        // The excluded part is read on its own and taken out of the text before anything else.
        $value = '(?:puroks?\s*\d{1,3}\s*[a-f]?\b(?:\s*(?:,|and|at|or|o)\s*\d{1,3}\s*[a-f]?\b)*|grupos?\s*\d{1,3}(?:\s*(?:,|and|at|or|o)\s*\d{1,3})*|ms|uwp|mk|binhi|kadiwa|buklod)';
        $excludePattern = '/\b(?:not in|not from|not|except(?: for)?|excluding|exclude|other than|maliban sa|maliban ang|hindi sa|hindi taga|outside(?: of)?|non-?|hindi)\s*('.$value.'\b(?:\s*(?:,|and|at|or|o)\s*'.$value.'\b)*)/u';
        $excluded = [];
        if (preg_match_all($excludePattern, $text, $negations) > 0) {
            foreach ($negations[1] as $segment) {
                foreach ($this->filters($segment) as $key => $values) {
                    if (in_array($key, ['status', 'purok', 'grupo', 'kapisanan'], true)) {
                        $excluded[$key] = array_values(array_unique(array_merge($excluded[$key] ?? [], (array) $values), SORT_REGULAR));
                    }
                }
            }
            $text = preg_replace($excludePattern, ' ', $text) ?? $text;
        }

        // "All records / including inactive" means every status; read it before the
        // status words so "including inactive" is not taken as "inactive only".
        $allPattern = '/\b(?:all records|lahat ng records|including inactive|kasama ang inactive|whole database|all statuses|any status)\b/u';
        if (preg_match($allPattern, $text) === 1) {
            $filters['all_statuses'] = true;
            $text = preg_replace($allPattern, ' ', $text) ?? $text;
        }

        // Status. "inactive" contains "active", so it is read first and removed.
        $statuses = [];
        if (preg_match('/\b(?:inactive|in-active|hindi aktibo|di aktibo|delisted|na-delist|natanggal)\b/u', $text) === 1) {
            $statuses[] = 'INACTIVE';
        }
        $statusText = preg_replace('/\b(?:inactive|in-active|hindi aktibo|di aktibo)\b/u', ' ', $text) ?? $text;
        preg_match_all('/\b(ms|uwp|mk)\b/u', $statusText, $matches);
        foreach ($matches[1] as $status) {
            $statuses[] = mb_strtoupper($status);
        }
        if ($statuses === [] && preg_match('/\b(?:active|aktibo)\b/u', $statusText) === 1) {
            $statuses = self::ACTIVE;
        }
        if ($statuses !== []) {
            $filters['status'] = array_values(array_unique($statuses));
            unset($filters['all_statuses']);
        }
        if (isset($excluded['status'])) {
            // "not MS" becomes the other statuses, so the answer can name them.
            $base = $filters['status'] ?? (! empty($filters['all_statuses']) ? self::STATUSES : self::ACTIVE);
            $filters['status'] = array_values(array_diff($base, $excluded['status']));
            unset($filters['all_statuses'], $excluded['status']);
        }

        // Purok, with optional segment letter (2a / 2b), several allowed ("puroks 1, 2 and 3").
        if (preg_match_all('/\bpuroks?\s*-?\s*(\d{1,3}\s*[a-z]?\b(?:\s*(?:,|and|at|&|or|o|vs\.?|versus|to)\s*(?:purok\s*)?\d{1,3}\s*[a-z]?\b)*)/u', $text, $groups) > 0) {
            $puroks = [];
            foreach ($groups[1] as $group) {
                if (preg_match('/^(\d{1,3})\s*to\s*(\d{1,3})$/u', trim($group), $range) === 1) {
                    $low = min((int) $range[1], (int) $range[2]);
                    foreach (range($low, min(max((int) $range[1], (int) $range[2]), $low + 30)) as $n) {
                        $puroks[] = ['number' => $n, 'segment' => ''];
                    }

                    continue;
                }
                preg_match_all('/(\d{1,3})(?:\s*([a-f])\b)?/u', $group, $items, PREG_SET_ORDER);
                foreach ($items as $item) {
                    $puroks[] = ['number' => (int) $item[1], 'segment' => $item[2] ?? ''];
                }
            }
            $filters['purok'] = array_values(array_unique($puroks, SORT_REGULAR));
        }
        if (preg_match_all('/\b(?:grupos?|groups?|grp)\s*-?\s*(\d{1,3}(?:\s*(?:,|and|at|&|or|o|to|-|hanggang)\s*\d{1,3})*)\b/u', $text, $groups) > 0) {
            $grupos = [];
            foreach ($groups[1] as $group) {
                if (preg_match('/^(\d{1,3})\s*(?:to|-|hanggang)\s*(\d{1,3})$/u', trim($group), $range) === 1) {
                    $low = min((int) $range[1], (int) $range[2]);
                    $grupos = array_merge($grupos, range($low, min(max((int) $range[1], (int) $range[2]), $low + 50)));

                    continue;
                }
                preg_match_all('/\d{1,3}/u', $group, $numbers);
                $grupos = array_merge($grupos, array_map('intval', $numbers[0]));
            }
            $filters['grupo'] = array_values(array_unique($grupos));
        }

        // "my purok" or "Kimberly's purok" / "secretary Kimberly": the Puroks that secretary is assigned to.
        $secretary = $this->secretaryFilter($text, $context);
        if ($secretary !== null) {
            $filters['secretary'] = $secretary;
            unset($filters['purok']);
        }

        $kapisanan = [];
        foreach (['binhi' => '/\bbinhis?\b/u', 'kadiwa' => '/\bkadiwas?\b/u', 'buklod' => '/\bbuklods?\b/u'] as $name => $pattern) {
            if (preg_match($pattern, $text) === 1) {
                $kapisanan[] = $name;
            }
        }
        if ($kapisanan !== []) {
            $filters['kapisanan'] = $kapisanan;
        }

        $female = preg_match('/\b(?:female|females|women|woman|ladies|sisters?|babae|kababaihan|mga babae)\b/u', $text) === 1;
        $male = preg_match('/\b(?:male|males|men|man|gentlemen|brothers?|lalaki|kalalakihan|mga lalaki)\b/u', $text) === 1;
        if ($female && ! $male) {
            $filters['gender'] = 'Female';
        } elseif ($male && ! $female) {
            $filters['gender'] = 'Male';
        }

        if (preg_match('/\b(?:single|unmarried|not married|walang asawa|hindi kasal|binata|dalaga)\b/u', $text) === 1) {
            $filters['married'] = false;
        } elseif (preg_match('/\b(?:married|may asawa|kasal|mag-asawa)\b/u', $text) === 1) {
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
            if ($notEndorsed) {
                $filters['endorsement'] = false;
            } elseif (preg_match('/\bendorsed\b/u', $text) === 1 || ($mentionsR210 && ! $eligible)) {
                $filters['endorsement'] = true;
            }
        }

        // Dates: baptism, entry, birth, delistment and endorsement, by year, month or range.
        $fields = [
            'baptism' => '(?:baptized|baptised|baptism|baptisms|bautismo|nabautismuhan|bininyagan|bagong bautismo)',
            'entry' => '(?:entered|entry|joined|pumasok|naitala)',
            'birth' => '(?:born|ipinanganak|isinilang)',
            'delisted' => '(?:delisted|na-delist|natanggal)',
            'endorsed' => '(?:endorsed)',
        ];
        foreach ($fields as $field => $words) {
            $range = $this->dateRange($text, $words);
            if ($range !== null && ! ($field === 'endorsed' && ($filters['endorsement'] ?? null) === false)) {
                $filters[$field] = $range;
            }
        }
        if (isset($filters['delisted'])) {
            $filters['status'] = ['INACTIVE'];
        }
        if (isset($filters['endorsed'])) {
            $filters['endorsement'] = true;
        }

        // Birthdays: "birthdays this month", "born in March", "celebrants today".
        $birthday = $this->birthdayFilter($text);
        if ($birthday !== null) {
            $filters['birthday'] = $birthday;
            if (isset($filters['birth']) && $birthday['label'] !== '' && preg_match('/\b(?:19|20)\d{2}\b/u', $text) !== 1) {
                unset($filters['birth']);
            }
        }

        $age = $this->ageRange($text);
        if ($age !== null) {
            $filters['age'] = $age;
        }

        if (preg_match('/\brfid\b/u', $text) === 1) {
            $filters['rfid'] = preg_match('/\b(?:without|no|walang|not)\s+(?:an?\s+)?rfid\b/u', $text) !== 1;
        }
        if (preg_match('/\b(?:monthly code|pending code|may code|with a code|movement code)\b/u', $text) === 1) {
            $filters['monthly_code'] = true;
        }
        if (preg_match('/\btarheta\b/u', $text) === 1 && preg_match('/\b(?:per|by|each|bawat|kada)\s+tarheta\b/u', $text) !== 1) {
            $filters['tarheta'] = preg_match('/\b(?:no|without|walang|missing|not|noinfo|no info)\b/u', $text) === 1 ? 'none' : 'has';
        }
        if (preg_match('/\bfiling(?: number)?\b/u', $text) === 1) {
            $filters['filing'] = preg_match('/\b(?:no|without|walang|missing|not)\b/u', $text) === 1 ? 'none' : 'has';
        }
        if (preg_match('/\b(?:incomplete|with missing (?:data|info|information|fields)|missing (?:data|info|information|fields)|kulang (?:ang )?(?:data|impormasyon)|may kulang)\b/u', $text) === 1) {
            $filters['incomplete'] = true;
        }

        $name = $this->captured($text, '(?:named|called|with (?:the )?name|name (?:is|like|contains|containing)|whose name (?:is|has|contains)|pangalang|nagngangalang)');
        if ($name !== null) {
            $filters['name'] = $name;
        }
        $evangelist = $this->captured($text, '(?:baptized by|baptised by|evangelist|ebanghelista|bininyagan ni|bautismo ni)');
        if ($evangelist !== null && preg_match('/^(?:purok|grupo|group|status|kapisanan|gender|year|month|age)\b/u', $evangelist) !== 1) {
            $filters['evangelist'] = $evangelist;
        }

        if ($excluded !== []) {
            $filters['exclude'] = $excluded;
        }

        $missing = [];
        $missingWords = [
            'date_of_baptism' => '(?:baptism date|date of baptism|baptism|bautismo)',
            'date_of_birth' => '(?:birthday|birth date|date of birth|birthdate|kaarawan|bday)',
            'date_of_entry' => '(?:entry date|date of entry)',
            'registry' => '(?:registry|registry number)',
            'purok' => '(?:purok)',
            'grupo' => '(?:grupo|group)',
            'gender' => '(?:gender|kasarian|sex)',
            'kapisanan' => '(?:kapisanan)',
            'evangelist' => '(?:evangelist|ebanghelista)',
        ];
        foreach ($missingWords as $column => $words) {
            if (preg_match('/\b(?:no|without|missing|walang|wala|blank|empty|null)\s+(?:a\s+|an\s+|na\s+|ng\s+)?'.$words.'\b/u', $text) === 1) {
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
                unset($filters['baptism']);
            }
            if (in_array('date_of_birth', $missing, true)) {
                unset($filters['birth'], $filters['birthday'], $filters['age']);
            }
            if (in_array('gender', $missing, true)) {
                unset($filters['gender']);
            }
            if (in_array('kapisanan', $missing, true)) {
                unset($filters['kapisanan']);
            }
            if (in_array('evangelist', $missing, true)) {
                unset($filters['evangelist']);
            }
        }

        return $filters;
    }

    /** A short name after a keyword ("named maria", "baptized by juan dela cruz"), stopping at the next clause. */
    private function captured(string $text, string $words): ?string
    {
        if (preg_match('/\b'.$words.'\s+"?([\p{L}][\p{L} .\'-]{0,60}?)"?(?=\s+(?:in|sa|from|who|that|and|na|at|with|without|per|by|born|baptized|aged?|under|over|between)\b|[?.!,]|$)/u', $text, $m) !== 1) {
            return null;
        }
        $value = trim($m[1], " .'-");

        return mb_strlen($value) >= 2 ? $value : null;
    }

    /** @return array{ids: list<int>, name: string}|null */
    private function secretaryFilter(string $text, array $context): ?array
    {
        if (preg_match('/\b(?:my|our|aking|ko)\s+puroks?\b|\bpurok ko\b|\bmy records\b|\bmy members\b/u', $text) === 1 && ! empty($context['user_id'])) {
            return ['ids' => [(int) $context['user_id']], 'name' => 'you'];
        }
        $name = null;
        if (preg_match('/\b(?:secretary|kalihim|sec\.?)\s+([\p{L}]{2,30}(?:\s+[\p{L}]{2,30})?)/u', $text, $m) === 1) {
            $name = $m[1];
        } elseif (preg_match('/\bpuroks?\s+(?:of|ni|handled by|under)\s+([\p{L}]{2,30}(?:\s+[\p{L}]{2,30})?)/u', $text, $m) === 1) {
            $name = $m[1];
        } elseif (preg_match('/\b([\p{L}]{2,30})\'s\s+puroks?\b/u', $text, $m) === 1) {
            $name = $m[1];
        }
        if ($name === null) {
            return null;
        }
        $parts = array_values(array_filter(explode(' ', $name), fn (string $w): bool => ! in_array($w, ['in', 'sa', 'of', 'and', 'with', 'are', 'is', 'have', 'has', 'ang', 'ng', 'na'], true)));
        if ($parts === [] || in_array($parts[0], ['purok', 'puroks', 'of', 'role', 'account', 'accounts', 'permissions'], true)) {
            return null;
        }

        $first = $parts[0];
        $users = User::query()
            ->where(function ($query) use ($first): void {
                $query->whereRaw('LOWER(first_name) LIKE ?', [$this->like($first).'%'])
                    ->orWhereRaw('LOWER(last_name) LIKE ?', [$this->like($first).'%']);
            })
            ->get(['id', 'first_name', 'last_name']);
        if (isset($parts[1]) && $users->count() > 1) {
            $narrowed = $users->filter(fn (User $u): bool => str_contains(mb_strtolower($u->first_name.' '.$u->last_name), $parts[1]));
            $users = $narrowed->isNotEmpty() ? $narrowed : $users;
        }
        if ($users->isEmpty()) {
            return null;
        }

        return [
            'ids' => $users->pluck('id')->map(fn ($id): int => (int) $id)->values()->all(),
            'name' => $users->count() === 1 ? trim($users->first()->first_name.' '.$users->first()->last_name) : ucfirst($first),
        ];
    }

    /** @return array{month: int, day: ?int, label: string}|null */
    private function birthdayFilter(string $text): ?array
    {
        $now = Carbon::now('Asia/Manila');
        if (preg_match('/\b(?:birthdays?|celebrants?|kaarawan|bday|nagbi-birthday|magbi-birthday|may birthday)\b/u', $text) !== 1
            && preg_match('/\bborn\s+(?:in|on|noong|sa)\s+(?:the month of\s+)?('.$this->monthWords(false).')\b(?!\s+\d{4})/u', $text, $born) !== 1) {
            return null;
        }
        if (isset($born[1])) {
            $month = self::MONTHS[$born[1]];

            return ['month' => $month, 'day' => null, 'label' => 'born in '.Carbon::create(2000, $month, 1)->format('F')];
        }
        if (preg_match('/\b(?:today|ngayon(?:g araw)?)\b/u', $text) === 1 && preg_match('/\b(?:this|next|ngayong|sa susunod na)\s+(?:month|buwan)\b/u', $text) !== 1) {
            return ['month' => $now->month, 'day' => $now->day, 'label' => 'birthday today'];
        }
        if (preg_match('/\btomorrow\b|\bbukas\b/u', $text) === 1) {
            $tomorrow = $now->copy()->addDay();

            return ['month' => $tomorrow->month, 'day' => $tomorrow->day, 'label' => 'birthday tomorrow'];
        }
        if (preg_match('/\b(?:next month|sa susunod na buwan|susunod na buwan)\b/u', $text) === 1) {
            $next = $now->copy()->startOfMonth()->addMonthNoOverflow();

            return ['month' => $next->month, 'day' => null, 'label' => 'birthday in '.$next->format('F')];
        }
        if (preg_match('/\b(?:this month|ngayong buwan|this mo)\b/u', $text) === 1) {
            return ['month' => $now->month, 'day' => null, 'label' => 'birthday in '.$now->format('F')];
        }
        if (preg_match('/\b('.$this->monthWords(true).')\b/u', $text, $m) === 1) {
            $month = self::MONTHS[$m[1]];

            return ['month' => $month, 'day' => null, 'label' => 'birthday in '.Carbon::create(2000, $month, 1)->format('F')];
        }

        return null;
    }

    private function monthWords(bool $withoutMay): string
    {
        $words = array_keys(self::MONTHS);
        if ($withoutMay) {
            $words = array_values(array_diff($words, ['may', 'mar']));
        }
        usort($words, fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        return implode('|', $words);
    }

    /**
     * A date range after a keyword: "baptized in 2024", "entered last month", "born between 1990 and 2000",
     * "baptized in March 2025", "delisted this year", "baptized before 2015".
     *
     * @return array{from: ?string, to: ?string, label: string}|null
     */
    private function dateRange(string $text, string $words): ?array
    {
        if (preg_match('/\b'.$words.'\b(.*)$/u', $text, $m) !== 1) {
            return null;
        }
        // Stop at the next date keyword so "born in 1990 baptized in 2010" keeps the two apart.
        $tail = preg_split('/\b(?:baptized|baptised|baptism|bautismo|born|entered|entry|joined|delisted|endorsed|birthdays?)\b|[.?!]/u', $m[1])[0] ?? '';
        $now = Carbon::now('Asia/Manila');
        $range = fn (Carbon $from, Carbon $to, string $label): array => ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'label' => $label];

        if (preg_match('/\b(?:this month|ngayong buwan)\b/u', $tail) === 1) {
            return $range($now->copy()->startOfMonth(), $now->copy()->endOfMonth(), 'this month');
        }
        if (preg_match('/\b(?:last month|nakaraang buwan|noong isang buwan)\b/u', $tail) === 1) {
            $last = $now->copy()->startOfMonth()->subMonthNoOverflow();

            return $range($last, $last->copy()->endOfMonth(), 'last month ('.$last->format('F Y').')');
        }
        if (preg_match('/\b(?:this week|ngayong linggo)\b/u', $tail) === 1) {
            return $range($now->copy()->startOfWeek(), $now->copy()->endOfWeek(), 'this week');
        }
        if (preg_match('/\b(?:today|ngayong araw)\b/u', $tail) === 1) {
            return $range($now->copy(), $now->copy(), 'today');
        }
        if (preg_match('/\b(?:this year|ngayong taon)\b/u', $tail) === 1) {
            return $range($now->copy()->startOfYear(), $now->copy()->endOfYear(), 'in '.$now->year);
        }
        if (preg_match('/\b(?:last year|noong nakaraang taon|nakaraang taon|noong isang taon)\b/u', $tail) === 1) {
            $year = $now->year - 1;

            return $range(Carbon::create($year, 1, 1), Carbon::create($year, 12, 31), 'in '.$year);
        }
        if (preg_match('/\blast\s+(\d{1,2})\s+(years?|months?|days?)\b|\bin the (?:past|last)\s+(\d{1,2})\s+(years?|months?|days?)\b/u', $tail, $p) === 1) {
            $n = (int) ($p[1] !== '' ? $p[1] : $p[3]);
            $unit = $p[2] !== '' ? $p[2] : $p[4];
            $from = match (true) {
                str_starts_with($unit, 'year') => $now->copy()->subYears($n),
                str_starts_with($unit, 'month') => $now->copy()->subMonthsNoOverflow($n),
                default => $now->copy()->subDays($n),
            };

            return $range($from, $now->copy(), "in the last {$n} {$unit}");
        }

        $date = '((?:\d{4}-\d{1,2}-\d{1,2})|(?:(?:'.$this->monthWords(false).')\s+\d{1,2},?\s+\d{4})|(?:(?:'.$this->monthWords(false).')(?:\s+\d{4})?)|(?:\d{4}))(?![\p{L}\d])';
        if (preg_match('/\b(?:between|from)\s+'.$date.'\s+(?:and|to|-|hanggang|until)\s+'.$date.'/u', $tail, $p) === 1) {
            $a = $this->parseDate($p[1]);
            $b = $this->parseDate($p[2]);
            if ($a !== null && $b !== null) {
                return ['from' => $a['from'], 'to' => $b['to'], 'label' => "between {$a['label']} and {$b['label']}"];
            }
        }
        if (preg_match('/\b(?:before|bago|prior to|earlier than)\s+'.$date.'/u', $tail, $p) === 1 && ($a = $this->parseDate($p[1])) !== null) {
            return ['from' => null, 'to' => Carbon::parse($a['from'])->subDay()->toDateString(), 'label' => 'before '.$a['label']];
        }
        if (preg_match('/\b(after|since|from|mula|simula)\s+'.$date.'/u', $tail, $p) === 1 && ($a = $this->parseDate($p[2])) !== null) {
            return $p[1] === 'after'
                ? ['from' => Carbon::parse($a['to'])->addDay()->toDateString(), 'to' => null, 'label' => 'after '.$a['label']]
                : ['from' => $a['from'], 'to' => null, 'label' => 'since '.$a['label']];
        }
        if (preg_match('/^\s*(?:(?:in|on|noong|sa|of|during|year|taong|nung|last)\s+)*'.$date.'/u', $tail, $p) === 1
            || preg_match('/\b(?:in|on|noong|during|taong)\s+'.$date.'/u', $tail, $p) === 1) {
            if (preg_match('/^may$/u', trim($p[1])) === 1) {
                return null;
            }
            $a = $this->parseDate($p[1]);
            if ($a !== null) {
                return ['from' => $a['from'], 'to' => $a['to'], 'label' => 'in '.$a['label']];
            }
        }

        return null;
    }

    /** @return array{from: string, to: string, label: string}|null */
    private function parseDate(string $value): ?array
    {
        $value = trim($value);
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $value, $m) === 1 && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            $day = Carbon::create((int) $m[1], (int) $m[2], (int) $m[3]);

            return ['from' => $day->toDateString(), 'to' => $day->toDateString(), 'label' => $day->format('F j, Y')];
        }
        if (preg_match('/^(\d{4})$/', $value, $m) === 1) {
            $year = (int) $m[1];
            if ($year < 1900 || $year > 2100) {
                return null;
            }

            return ['from' => "{$year}-01-01", 'to' => "{$year}-12-31", 'label' => (string) $year];
        }
        if (preg_match('/^(\p{L}+)\s+(\d{1,2}),?\s+(\d{4})$/u', $value, $m) === 1 && isset(self::MONTHS[$m[1]]) && checkdate(self::MONTHS[$m[1]], (int) $m[2], (int) $m[3])) {
            $day = Carbon::create((int) $m[3], self::MONTHS[$m[1]], (int) $m[2]);

            return ['from' => $day->toDateString(), 'to' => $day->toDateString(), 'label' => $day->format('F j, Y')];
        }
        if (preg_match('/^(\p{L}+)(?:\s+(\d{4}))?$/u', $value, $m) === 1 && isset(self::MONTHS[$m[1]])) {
            $year = isset($m[2]) && $m[2] !== '' ? (int) $m[2] : Carbon::now('Asia/Manila')->year;
            $start = Carbon::create($year, self::MONTHS[$m[1]], 1);

            return ['from' => $start->toDateString(), 'to' => $start->copy()->endOfMonth()->toDateString(), 'label' => $start->format('F Y')];
        }

        return null;
    }

    /** @return array{min: ?int, max: ?int, label: string}|null */
    private function ageRange(string $text): ?array
    {
        if (preg_match('/\b(?:minors?|menor de edad)\b/u', $text) === 1) {
            return ['min' => null, 'max' => 17, 'label' => 'under 18'];
        }
        if (preg_match('/\b(?:seniors?|senior citizens?|matatanda|elderly)\b/u', $text) === 1) {
            return ['min' => 60, 'max' => null, 'label' => '60 or older'];
        }
        if (preg_match('/\b(?:teens|teenagers?|kabataan)\b/u', $text) === 1) {
            return ['min' => 13, 'max' => 19, 'label' => 'aged 13–19'];
        }
        if (preg_match('/\b(?:adults?|nasa hustong gulang)\b/u', $text) === 1) {
            return ['min' => 18, 'max' => null, 'label' => '18 or older'];
        }
        if (preg_match('/\b(?:in their\s+)?(\d)0s\b/u', $text, $m) === 1 && (int) $m[1] >= 1) {
            return ['min' => (int) $m[1] * 10, 'max' => (int) $m[1] * 10 + 9, 'label' => "in their {$m[1]}0s"];
        }
        if (preg_match('/\b(?:aged?|ages|edad)\s+(\d{1,3})\s*(?:to|-|and|hanggang)\s*(\d{1,3})\b|\bbetween\s+(\d{1,3})\s+(?:and|to)\s+(\d{1,3})\s+(?:years old|yrs|taong gulang|y\/o)/u', $text, $m) === 1) {
            $low = (int) ($m[1] !== '' ? $m[1] : $m[3]);
            $high = (int) ($m[2] !== '' ? $m[2] : $m[4]);

            return ['min' => min($low, $high), 'max' => max($low, $high), 'label' => 'aged '.min($low, $high).'–'.max($low, $high)];
        }
        if (preg_match('/\b(\d{1,3})\s*(?:to|-|hanggang)\s*(\d{1,3})\s*(?:years?(?: old)?|yrs|y\/o|taong gulang|anyos)\b/u', $text, $m) === 1) {
            $low = min((int) $m[1], (int) $m[2]);
            $high = max((int) $m[1], (int) $m[2]);

            return ['min' => $low, 'max' => $high, 'label' => "aged {$low}–{$high}"];
        }
        if (preg_match('/\bbetween\s+(\d{1,3})\s+(?:and|to)\s+(\d{1,3})\b/u', $text, $m) === 1 && (int) $m[1] <= 120 && (int) $m[2] <= 120
            && preg_match('/\b(?:born|baptized|baptised|baptism|entered|entry|joined|delisted|endorsed|grupos?|groups?|puroks?)\b/u', $text) !== 1) {
            $low = min((int) $m[1], (int) $m[2]);
            $high = max((int) $m[1], (int) $m[2]);

            return ['min' => $low, 'max' => $high, 'label' => "aged {$low}–{$high}"];
        }
        if (preg_match('/\b(\d{1,3})\s*(?:pataas|and up|years? and up|and above)\b/u', $text, $m) === 1 && (int) $m[1] <= 120) {
            return ['min' => (int) $m[1], 'max' => null, 'label' => $m[1].' or older'];
        }
        if (preg_match('/\b(\d{1,3})\s*(?:pababa|and below|and under|and younger)\b/u', $text, $m) === 1 && (int) $m[1] <= 120) {
            return ['min' => null, 'max' => (int) $m[1], 'label' => $m[1].' or younger'];
        }
        if (preg_match('/\b(?:under|below|younger than|less than|wala pang)\s+(\d{1,3})(?:\s*(?:years old|yrs|y\/o|taong gulang))?/u', $text, $m) === 1
            && preg_match('/\b(?:age|aged|years old|yrs|y\/o|taong gulang|edad|old|younger)\b|\b(?:under|below)\s+\d{1,3}\b(?!\s*(?:percent|records?|members?))/u', $text) === 1) {
            return ['min' => null, 'max' => (int) $m[1] - 1, 'label' => 'under '.$m[1]];
        }
        if (preg_match('/\b(?:over|above|older than|more than|lagpas|higit sa)\s+(\d{1,3})(?:\s*(?:years old|yrs|y\/o|taong gulang))?/u', $text, $m) === 1
            && (preg_match('/\b(?:age|aged|years old|yrs|y\/o|taong gulang|edad|old|older)\b/u', $text) === 1
                || (preg_match('/\b(?:over|above|older than|lagpas)\s+\d{1,3}\b(?!\s*(?:percent|records?|members?|ms|uwp|mk))/u', $text) === 1 && (int) $m[1] <= 120))) {
            return ['min' => (int) $m[1] + 1, 'max' => null, 'label' => 'over '.$m[1]];
        }
        if (preg_match('/\b(\d{1,3})\s+(?:and|or)\s+(?:above|older|up|over)\b|\b(\d{1,3})\s*\+\s*(?:years|yrs|y\/o)?/u', $text, $m) === 1
            && preg_match('/\b(?:age|aged|years|yrs|y\/o|edad|old|older)\b/u', $text) === 1) {
            $n = (int) ($m[1] !== '' ? $m[1] : $m[2]);

            return ['min' => $n, 'max' => null, 'label' => $n.' or older'];
        }
        if (preg_match('/\b(?:aged?|edad)\s+(\d{1,3})\b(?!\s*(?:to|-|and|hanggang))|\b(\d{1,3})\s*(?:years old|yrs old|y\/o|taong gulang)\b/u', $text, $m) === 1) {
            $n = (int) ($m[1] !== '' ? $m[1] : $m[2]);

            return ['min' => $n, 'max' => $n, 'label' => 'aged '.$n];
        }

        return null;
    }

    /** @return array{column: string, direction: string, label: string} */
    private function extremeTarget(string $text): array
    {
        if (preg_match('/\b(?:youngest|pinakabata)\b/u', $text) === 1) {
            return ['column' => 'date_of_birth', 'direction' => 'desc', 'label' => 'youngest'];
        }
        if (preg_match('/\b(?:oldest|eldest|pinakamatanda)\b/u', $text) === 1 && preg_match('/\b(?:baptized|baptism|bautismo|entered|entry|joined)\b/u', $text) !== 1) {
            return ['column' => 'date_of_birth', 'direction' => 'asc', 'label' => 'oldest'];
        }
        $column = preg_match('/\b(?:entered|entry|entries|joined|pumasok|members|records)\b/u', $text) === 1
            && preg_match('/\b(?:baptized|baptism|baptisms|bautismo|nabautismuhan)\b/u', $text) !== 1
            ? 'date_of_entry' : 'date_of_baptism';
        $earliest = preg_match('/\b(?:earliest|oldest|eldest|first|pinakauna)\b/u', $text) === 1;
        $what = $column === 'date_of_entry' ? 'entered' : 'baptized';

        return ['column' => $column, 'direction' => $earliest ? 'asc' : 'desc', 'label' => $earliest ? "earliest {$what}" : "most recently {$what}"];
    }

    /** @return list<array{label: string, filters: array}> */
    private function comparison(string $text, array $filters): array
    {
        $body = trim(preg_replace('/\b(?:compare|comparison(?: of| between)?|ikumpara|kumpara(?:hin)?|paghambingin|how many|ilan|ilang|the number of)\b/u', ' ', $text) ?? $text);
        $parts = preg_split('/\bvs\.?\b|\bversus\b|\bcompared? (?:to|with)\b|\bkumpara sa\b|\bagainst\b/u', $body) ?: [$body];
        if (count($parts) < 2) {
            $parts = preg_split('/\band\b|\bat\b|&|\bor\b/u', $body) ?: [$body];
        }
        $sets = array_values(array_filter(array_map(fn (string $part): array => $this->filters(trim($part)), $parts), fn (array $set): bool => $set !== []));

        if (count($sets) < 2) {
            // "compare purok 1 and 2" reaches here as one part with two puroks.
            foreach (['purok', 'status', 'kapisanan', 'grupo'] as $key) {
                if (count($filters[$key] ?? []) >= 2) {
                    return array_map(function ($value) use ($filters, $key): array {
                        $set = $filters;
                        $set[$key] = [$value];

                        return ['label' => $this->labelFor($key, [$value]), 'filters' => $set];
                    }, array_slice($filters[$key], 0, 8));
                }
            }

            return [];
        }

        $keys = [];
        foreach ($sets as $set) {
            foreach (array_keys($set) as $key) {
                $keys[$key] = ($keys[$key] ?? 0) + 1;
            }
        }
        $compared = array_keys(array_filter($keys, fn (int $n): bool => $n >= 2));
        $compared = array_values(array_filter($compared, function (string $key) use ($sets): bool {
            $values = array_map(fn (array $set) => serialize($set[$key] ?? null), $sets);

            return count(array_unique($values)) > 1;
        }));
        if ($compared === []) {
            $compared = array_keys($keys);
        }

        $out = [];
        foreach (array_slice($sets, 0, 8) as $set) {
            $merged = $filters;
            foreach ($compared as $key) {
                unset($merged[$key]);
                if (array_key_exists($key, $set)) {
                    $merged[$key] = $set[$key];
                }
            }
            if (isset($set['status'])) {
                unset($merged['all_statuses']);
            }
            $label = implode(' ', array_filter(array_map(fn (string $key): string => array_key_exists($key, $set) ? $this->labelFor($key, $set[$key]) : '', $compared)));
            $out[] = ['label' => $label !== '' ? $label : 'Group '.(count($out) + 1), 'filters' => $merged];
        }

        return $out;
    }

    /** The whole the percentage is taken of: "% of MS are female" → MS; "% of MS in purok 1" → MS. */
    private function percentBase(string $text, array $filters): array
    {
        if (preg_match('/\b(?:percent|percentage|porsyento|porsiyento|proportion|ratio)\s+(?:of\s+(?:the\s+)?|ng\s+)(.+?)\s+(?:are|is|were|who are|that are|ay|ang)\s+(.+)$/u', $text, $m) === 1) {
            $base = $this->filters($m[1]);
            if ($base !== [] || preg_match('/\b(?:members?|records?|everyone|lahat|all)\b/u', $m[1]) === 1) {
                return $base;
            }
        }
        $base = $filters;
        if (isset($base['purok']) || isset($base['grupo']) || isset($base['secretary'])) {
            unset($base['purok'], $base['grupo'], $base['secretary']);
            if ($base !== $filters) {
                return $base;
            }
        }
        $statusOnly = array_intersect_key($filters, array_flip(['status', 'all_statuses']));
        if ($statusOnly !== $filters) {
            return $statusOnly;
        }

        return in_array('INACTIVE', $filters['status'] ?? [], true) ? ['all_statuses' => true] : [];
    }

    // ---------------------------------------------------------------- SQL

    /** @return Builder<Record> */
    private function query(array $filters): Builder
    {
        $query = Record::query();
        $statuses = $filters['status'] ?? (! empty($filters['all_statuses']) ? self::STATUSES : self::ACTIVE);
        $this->whereValues($query, 'status', fn ($v): bool => in_array(mb_strtoupper(trim((string) $v)), $statuses, true));

        $puroks = $filters['purok'] ?? null;
        if (isset($filters['secretary'])) {
            $puroks = $this->secretaryPuroks($filters['secretary']['ids']);
            if ($puroks === []) {
                $query->whereRaw('1 = 0');
            }
        }
        if ($puroks !== null && $puroks !== []) {
            $query->where(fn (Builder $any) => $this->wherePuroks($any, $puroks));
        }
        if (isset($filters['grupo'])) {
            $this->whereValues($query, 'grupo', fn ($v): bool => in_array((int) $v, $filters['grupo'], true));
        }
        if (isset($filters['kapisanan'])) {
            $wanted = (array) $filters['kapisanan'];
            $this->whereValues($query, 'kapisanan', fn ($v): bool => in_array($this->kapisanan($v), $wanted, true));
        }
        $excluded = $filters['exclude'] ?? [];
        if (! empty($excluded['purok'])) {
            $query->where(fn (Builder $q) => $q->whereNot(fn (Builder $not) => $this->wherePuroks($not, $excluded['purok']))->orWhereNull('purok'));
        }
        if (! empty($excluded['grupo'])) {
            $this->whereValues($query, 'grupo', fn ($v): bool => ! in_array((int) $v, $excluded['grupo'], true));
        }
        if (! empty($excluded['kapisanan'])) {
            $this->whereValues($query, 'kapisanan', fn ($v): bool => ! in_array($this->kapisanan($v), $excluded['kapisanan'], true));
        }
        if (isset($filters['gender'])) {
            $this->whereValues($query, 'gender', fn ($v): bool => $this->gender($v) === $filters['gender']);
        }
        if (isset($filters['married'])) {
            $filters['married']
                ? $query->where('is_married', true)
                : $query->where(fn (Builder $q) => $q->where('is_married', false)->orWhereNull('is_married'));
        }
        if (isset($filters['endorsement'])) {
            $filters['endorsement']
                ? $query->whereRaw("UPPER(TRIM(r2_10_endorsement)) = 'ENDORSED'")
                : $query->where(fn (Builder $q) => $q->whereNull('r2_10_endorsement')->orWhereRaw("UPPER(TRIM(r2_10_endorsement)) <> 'ENDORSED'"));
        }
        if (! empty($filters['eligible'])) {
            $this->whereEligible($query);
        }
        foreach (['baptism' => 'date_of_baptism', 'entry' => 'date_of_entry', 'birth' => 'date_of_birth'] as $field => $column) {
            if (isset($filters[$field])) {
                $this->whereDateRange($query, $column, $filters[$field]);
            }
        }
        if (isset($filters['delisted'])) {
            $range = $filters['delisted'];
            $query->whereExists(function ($sub) use ($range): void {
                $sub->select(DB::raw(1))->from('delistment_histories')->whereColumn('delistment_histories.record_id', 'records.id');
                $this->whereDateRange($sub, 'delistment_histories.delisted_at', $range);
            });
        }
        if (isset($filters['endorsed'])) {
            $range = $filters['endorsed'];
            $query->whereExists(function ($sub) use ($range): void {
                $sub->select(DB::raw(1))->from('r2_10_endorsement_reasons')->whereColumn('r2_10_endorsement_reasons.record_id', 'records.id')
                    ->where('r2_10_endorsement_reasons.is_current', true);
                $this->whereDateRange($sub, 'r2_10_endorsement_reasons.endorsed_at', $range);
            });
        }
        if (isset($filters['birthday'])) {
            $query->whereNotNull('date_of_birth')->whereRaw($this->datePart('month', 'date_of_birth').' = ?', [$filters['birthday']['month']]);
            if ($filters['birthday']['day'] !== null) {
                $query->whereRaw($this->datePart('day', 'date_of_birth').' = ?', [$filters['birthday']['day']]);
            }
        }
        if (isset($filters['age'])) {
            $today = Carbon::now('Asia/Manila')->startOfDay();
            $query->whereNotNull('date_of_birth');
            if ($filters['age']['min'] !== null) {
                $query->whereDate('date_of_birth', '<=', $today->copy()->subYears($filters['age']['min'])->toDateString());
            }
            if ($filters['age']['max'] !== null) {
                $query->whereDate('date_of_birth', '>', $today->copy()->subYears($filters['age']['max'] + 1)->toDateString());
            }
        }
        if (isset($filters['rfid'])) {
            $filters['rfid']
                ? $query->whereRaw("UPPER(TRIM(registry)) = 'RFID'")
                : $query->where(fn (Builder $q) => $q->whereNull('registry')->orWhereRaw("UPPER(TRIM(registry)) <> 'RFID'"));
        }
        if (! empty($filters['monthly_code'])) {
            $query->whereNotNull('monthly_code')->whereRaw("TRIM(monthly_code) <> ''");
        }
        if (isset($filters['tarheta'])) {
            $none = fn (Builder $q) => $q->whereNull('tarheta_status')->orWhereRaw("UPPER(TRIM(tarheta_status)) IN ('', 'NOINFO', 'NO INFO')");
            $filters['tarheta'] === 'none' ? $query->where($none) : $query->whereNot($none);
        }
        if (isset($filters['filing'])) {
            $none = fn (Builder $q) => $q->whereNull('filing_number')->orWhereRaw("TRIM(filing_number) = ''");
            $filters['filing'] === 'none' ? $query->where($none) : $query->whereNot($none);
        }
        if (! empty($filters['incomplete'])) {
            $query->where(function (Builder $any): void {
                $any->whereNull('registry')->orWhereRaw("UPPER(TRIM(registry)) IN ('', 'RFID')");
                foreach (['gender', 'purok', 'grupo', 'kapisanan', 'date_of_baptism', 'evangelist', 'date_of_birth'] as $column) {
                    $any->orWhereNull($column)->orWhereRaw("TRIM({$column}) = ''");
                }
            });
        }
        foreach (['name' => 'full_name', 'evangelist' => 'evangelist'] as $key => $column) {
            if (isset($filters[$key])) {
                $query->whereRaw("LOWER({$column}) LIKE ? ESCAPE '!'", ['%'.$this->like($filters[$key]).'%']);
            }
        }
        foreach ($filters['missing'] ?? [] as $column) {
            if (in_array($column, ['date_of_baptism', 'date_of_birth', 'date_of_entry', 'registry', 'purok', 'grupo', 'gender', 'kapisanan', 'evangelist'], true)) {
                $query->where(fn (Builder $q) => $q->whereNull($column)->orWhereRaw("TRIM({$column}) = ''"));
            }
        }

        return $query;
    }

    /** Any of the given Puroks; a segment ("2a") also limits the Grupo range from the Purok settings. */
    private function wherePuroks(Builder $any, array $puroks): void
    {
        foreach ($puroks as $purok) {
            $any->orWhere(function (Builder $one) use ($purok): void {
                $this->whereValues($one, 'purok', fn ($v): bool => $this->digits($v) === $purok['number']);
                $segment = $purok['segment'] === '' ? null : $this->segmentFor($purok['number'], $purok['segment']);
                if (isset($purok['segment_id'])) {
                    $segment = $this->settings()->firstWhere('id', $purok['segment_id']);
                }
                if ($segment !== null && ! $segment->full_purok) {
                    $this->whereValues($one, 'grupo', fn ($v): bool => $this->digits($v) >= (int) $segment->grupo_start && $this->digits($v) <= (int) $segment->grupo_end);
                }
            });
        }
    }

    /** R2-10 eligibility (same rules as R210Eligibility) as SQL: MK always; MS/UWP after the required years. */
    private function whereEligible(Builder $query): void
    {
        $requirements = GlobalVariable::query()->first();
        $years = max(1, (int) ($requirements?->entry_years_requirement ?? 1));
        $entryCutoff = Carbon::now('Asia/Manila')->subYears($years)->toDateString();

        $query->where(function (Builder $either) use ($requirements, $entryCutoff): void {
            $either->where(fn (Builder $mk) => $this->whereValues($mk, 'status', fn ($v): bool => mb_strtoupper(trim((string) $v)) === 'MK'))
                ->orWhere(function (Builder $member) use ($requirements, $entryCutoff): void {
                    $this->whereValues($member, 'status', fn ($v): bool => in_array(mb_strtoupper(trim((string) $v)), ['MS', 'UWP'], true));
                    $member->whereNotNull('date_of_entry')->whereDate('date_of_entry', '<=', $entryCutoff);
                    if ($requirements?->dateofbaptism_requirement_boolean) {
                        $requirements->baptism_requirement === null
                            ? $member->whereRaw('1 = 0')
                            : $member->whereNotNull('date_of_baptism')->whereDate('date_of_baptism', '<=', Carbon::parse($requirements->baptism_requirement)->toDateString());
                    }
                });
        });
    }

    private function whereDateRange($query, string $column, array $range): void
    {
        $query->whereNotNull($column);
        if ($range['from'] !== null) {
            $query->whereDate($column, '>=', $range['from']);
        }
        if ($range['to'] !== null) {
            $query->whereDate($column, '<=', $range['to']);
        }
    }

    /**
     * Restricts $column to the stored spellings that $keep accepts. The few distinct values of a
     * low-cardinality column (status, purok, gender…) are read once with GROUP BY, so "ms", " MS"
     * and "Ms" all count, while the WHERE stays a plain IN list on the raw column.
     */
    private function whereValues(Builder $query, string $column, callable $keep): void
    {
        $this->distinct[$column] ??= Record::query()->select($column)->groupBy($column)->pluck($column)->all();
        $values = [];
        $withNull = false;
        foreach ($this->distinct[$column] as $value) {
            if (! $keep($value)) {
                continue;
            }
            $value === null ? $withNull = true : $values[] = $value;
        }

        if ($values === [] && ! $withNull) {
            $query->whereRaw('1 = 0');

            return;
        }
        $query->where(function (Builder $q) use ($column, $values, $withNull): void {
            if ($values !== []) {
                $q->whereIn($column, $values);
            }
            if ($withNull) {
                $q->orWhereNull($column);
            }
        });
    }

    private function datePart(string $part, string $column): string
    {
        $driver = DB::connection()->getDriverName();
        if ($driver === 'sqlite') {
            $format = ['year' => '%Y', 'month' => '%m', 'day' => '%d'][$part];

            return "CAST(strftime('{$format}', {$column}) AS INTEGER)";
        }
        if ($driver === 'pgsql') {
            return 'CAST(EXTRACT('.strtoupper($part)." FROM {$column}) AS INTEGER)";
        }

        return ['year' => 'YEAR', 'month' => 'MONTH', 'day' => 'DAYOFMONTH'][$part]."({$column})";
    }

    private function like(string $value): string
    {
        return strtr(mb_strtolower($value), ['!' => '!!', '%' => '!%', '_' => '!_']);
    }

    private function digits(mixed $value): int
    {
        return (int) preg_replace('/\D+/', '', (string) $value);
    }

    private function settings(): Collection
    {
        return $this->purokSettings ??= Purok::query()->orderBy('purok_number')->orderBy('grupo_start')->orderBy('id')
            ->get(['id', 'purok_number', 'purok_name', 'grupo_start', 'grupo_end', 'full_purok', 'secretary']);
    }

    /** @return list<array{number: int, segment: string, segment_id?: int}> */
    private function secretaryPuroks(array $userIds): array
    {
        return $this->settings()
            ->filter(fn (Purok $purok): bool => in_array((int) $purok->secretary, $userIds, true))
            ->map(fn (Purok $purok): array => ['number' => (int) $purok->purok_number, 'segment' => '', 'segment_id' => (int) $purok->id])
            ->values()
            ->all();
    }

    private function segmentFor(int $number, string $letter): ?Purok
    {
        return $this->settings()->first(fn (Purok $purok): bool => (int) $purok->purok_number === $number
            && str_ends_with(mb_strtolower(preg_replace('/\s+/', '', (string) $purok->purok_name) ?? ''), $number.$letter));
    }

    private function segmentName(mixed $purok, mixed $grupo): string
    {
        $number = $this->digits($purok);
        if ($number === 0) {
            return 'No Purok';
        }
        $segment = $this->settings()->where('purok_number', $number)
            ->first(fn (Purok $p): bool => $p->full_purok || ($this->digits($grupo) >= (int) $p->grupo_start && $this->digits($grupo) <= (int) $p->grupo_end));

        return $segment?->purok_name ?: 'Purok '.$number;
    }

    private function kapisanan(mixed $value): ?string
    {
        return match (mb_strtolower(trim((string) $value))) {
            'b', 'buklod' => 'buklod',
            'k', 'kadiwa' => 'kadiwa',
            'binhi' => 'binhi',
            default => null,
        };
    }

    private function gender(mixed $value): ?string
    {
        return match (mb_strtoupper(trim((string) $value))) {
            'L', 'M', 'MALE', 'LALAKI' => 'Male',
            'B', 'F', 'FEMALE', 'BABAE' => 'Female',
            default => null,
        };
    }

    // ---------------------------------------------------------------- answering

    /** @return list<array> every filter set the spec reads (comparisons and percentages have several) */
    private function allFilterSets(array $spec): array
    {
        $sets = [$spec['filters']];
        foreach ($spec['compare'] ?? [] as $part) {
            $sets[] = $part['filters'];
        }
        if (isset($spec['base'])) {
            $sets[] = $spec['base'];
        }

        return $sets;
    }

    private function subject(array $filters): string
    {
        $statuses = $filters['status'] ?? [];
        $status = match (true) {
            $statuses === [] && ! empty($filters['all_statuses']) => '',
            $statuses === [] => 'active',
            $statuses === self::ACTIVE => 'active',
            default => implode(' and ', $statuses),
        };
        $kapisanan = isset($filters['kapisanan']) ? implode(' and ', array_map('ucfirst', (array) $filters['kapisanan'])) : '';

        return trim($status.' '.$kapisanan);
    }

    private function labelFor(string $key, mixed $value): string
    {
        return match ($key) {
            'purok' => 'Purok '.implode(', ', array_map(fn (array $p): string => $p['number'].mb_strtoupper($p['segment']), $value)),
            'grupo' => 'Grupo '.implode(', ', $value),
            'status' => implode(' and ', $value),
            'kapisanan' => implode(' and ', array_map('ucfirst', (array) $value)),
            'gender' => $value,
            'married' => $value ? 'Married' : 'Single',
            'age' => ucfirst($value['label']),
            'secretary' => $value['name'] === 'you' ? 'Your Puroks' : $value['name'].'’s Puroks',
            'baptism' => 'Baptized '.$value['label'],
            'entry' => 'Entered '.$value['label'],
            'birth' => 'Born '.$value['label'],
            'birthday' => ucfirst($value['label']),
            default => ucfirst(str_replace('_', ' ', $key)),
        };
    }

    /** @return array{location: array<int, string>, traits: array<int, string>, r210: string} */
    private function conditions(array $filters): array
    {
        $location = [];
        if (isset($filters['secretary'])) {
            $location[] = $filters['secretary']['name'] === 'you' ? 'your Puroks' : $filters['secretary']['name'].'’s Puroks';
        }
        if (isset($filters['purok'])) {
            $location[] = $this->labelFor('purok', $filters['purok']);
        }
        if (isset($filters['grupo'])) {
            $location[] = $this->labelFor('grupo', $filters['grupo']);
        }

        $traits = [];
        foreach ($filters['exclude'] ?? [] as $key => $values) {
            $traits[] = ($key === 'kapisanan' ? 'not ' : 'not in ').$this->labelFor($key, $values);
        }
        if (isset($filters['gender'])) {
            $traits[] = mb_strtolower($filters['gender']);
        }
        if (isset($filters['married'])) {
            $traits[] = $filters['married'] ? 'married' : 'single';
        }
        if (isset($filters['age'])) {
            $traits[] = $filters['age']['label'];
        }
        if (isset($filters['birthday'])) {
            $traits[] = $filters['birthday']['label'];
        }
        if (isset($filters['birth'])) {
            $traits[] = 'born '.$filters['birth']['label'];
        }
        if (isset($filters['baptism'])) {
            $traits[] = 'baptized '.$filters['baptism']['label'];
        }
        if (isset($filters['entry'])) {
            $traits[] = 'entered '.$filters['entry']['label'];
        }
        if (isset($filters['delisted'])) {
            $traits[] = 'delisted '.$filters['delisted']['label'];
        }
        if (isset($filters['name'])) {
            $traits[] = 'name contains “'.$filters['name'].'”';
        }
        if (isset($filters['evangelist'])) {
            $traits[] = 'evangelist “'.$filters['evangelist'].'”';
        }
        if (isset($filters['rfid'])) {
            $traits[] = $filters['rfid'] ? 'with RFID' : 'without RFID';
        }
        if (! empty($filters['monthly_code'])) {
            $traits[] = 'with a pending monthly code';
        }
        if (isset($filters['tarheta'])) {
            $traits[] = $filters['tarheta'] === 'none' ? 'no Tarheta info' : 'with Tarheta info';
        }
        if (isset($filters['filing'])) {
            $traits[] = $filters['filing'] === 'none' ? 'no filing number' : 'with a filing number';
        }
        if (! empty($filters['incomplete'])) {
            $traits[] = 'with missing data';
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
        } elseif (isset($filters['endorsed'])) {
            $r210 = ' that were endorsed for R2-10 '.$filters['endorsed']['label'];
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

        $r210 = $count === 1 ? str_replace([' that are ', ' that were '], [' that is ', ' that was '], $conditions['r210']) : $conditions['r210'];

        return $text.$r210;
    }

    private function describeFilters(array $filters, int $count = 2): string
    {
        return $this->describe($count, $this->subject($filters), $this->conditions($filters));
    }

    private function countReply(int $count, string $subject, array $conditions): string
    {
        if ($count === 0) {
            return 'I found no '.$this->describe(2, $subject, $conditions).'.';
        }

        return ($count === 1 ? 'There is 1 ' : "There are {$count} ").$this->describe($count, $subject, $conditions).'.';
    }

    /**
     * "How many MS in purok 1 and 2" also shows each Purok on its own line (the same for several
     * Grupos or Kapisanan), so one question gives both the total and every part.
     */
    private function splitLines(array $filters): string
    {
        foreach (['purok', 'grupo', 'kapisanan'] as $key) {
            $values = array_values((array) ($filters[$key] ?? []));
            if (count($values) < 2 || count($values) > 12) {
                continue;
            }
            $lines = array_map(function ($value) use ($filters, $key): string {
                $one = $filters;
                $one[$key] = [$value];

                return '• '.$this->labelFor($key, [$value]).': '.$this->query($one)->count();
            }, $values);

            return "\n".implode("\n", $lines);
        }

        return '';
    }

    /** @return list<array{name: string, count: int, sort: string}> */
    private function groups(array $filters, string $dimension): array
    {
        $query = $this->query($filters);
        $driverYear = fn (string $column): string => $this->datePart('year', $column);
        $today = Carbon::now('Asia/Manila')->startOfDay();

        [$select, $bindings, $label] = match ($dimension) {
            'purok' => ['purok AS k1, grupo AS k2', [], fn ($row): string => $this->segmentName($row->k1, $row->k2)],
            'grupo' => ['grupo AS k1', [], fn ($row): string => trim((string) $row->k1) !== '' ? 'Grupo '.(int) $row->k1 : 'No Grupo'],
            'status' => ['status AS k1', [], fn ($row): string => mb_strtoupper(trim((string) $row->k1)) ?: 'No status'],
            'kapisanan' => ['kapisanan AS k1', [], fn ($row): string => ucfirst($this->kapisanan($row->k1) ?? 'not recorded')],
            'gender' => ['gender AS k1', [], fn ($row): string => $this->gender($row->k1) ?? 'Not recorded'],
            'married' => ['is_married AS k1', [], fn ($row): string => (bool) $row->k1 ? 'Married' : 'Single'],
            'tarheta' => ['tarheta_status AS k1', [], fn ($row): string => in_array(mb_strtoupper(trim((string) $row->k1)), ['', 'NOINFO', 'NO INFO'], true) ? 'No info' : mb_strtoupper(trim((string) $row->k1))],
            'endorsement' => ["CASE WHEN UPPER(TRIM(r2_10_endorsement)) = 'ENDORSED' THEN 1 ELSE 0 END AS k1", [], fn ($row): string => (int) $row->k1 === 1 ? 'Endorsed' : 'Not endorsed'],
            'birth_month' => [$this->datePart('month', 'date_of_birth').' AS k1', [], fn ($row): string => $row->k1 ? Carbon::create(2000, (int) $row->k1, 1)->format('F') : 'No birthday'],
            'baptism_year' => [$driverYear('date_of_baptism').' AS k1', [], fn ($row): string => $row->k1 ? (string) $row->k1 : 'No baptism date'],
            'entry_year' => [$driverYear('date_of_entry').' AS k1', [], fn ($row): string => $row->k1 ? (string) $row->k1 : 'No entry date'],
            'age' => [
                'CASE WHEN date_of_birth IS NULL THEN 0 WHEN date_of_birth > ? THEN 1 WHEN date_of_birth > ? THEN 2 WHEN date_of_birth > ? THEN 3 WHEN date_of_birth > ? THEN 4 ELSE 5 END AS k1',
                [$today->copy()->subYears(18)->toDateString().' 23:59:59', $today->copy()->subYears(30)->toDateString().' 23:59:59', $today->copy()->subYears(45)->toDateString().' 23:59:59', $today->copy()->subYears(60)->toDateString().' 23:59:59'],
                fn ($row): string => ['No birthday', 'Under 18', '18–29', '30–44', '45–59', '60 and older'][(int) $row->k1],
            ],
        };
        $groupBy = $dimension === 'purok' ? ['purok', 'grupo'] : ['k1'];
        $rows = $query->toBase()->selectRaw($select.', COUNT(*) AS aggregate', $bindings)
            ->when($dimension === 'age', fn ($q) => $q->groupByRaw('1'), fn ($q) => $q->groupBy($groupBy))
            ->get();

        $totals = [];
        foreach ($rows as $row) {
            $name = $label($row);
            $sort = match ($dimension) {
                'age' => (string) ((int) $row->k1 === 0 ? 9 : (int) $row->k1),
                'birth_month' => str_pad((string) ((int) $row->k1 ?: 13), 2, '0', STR_PAD_LEFT),
                'baptism_year', 'entry_year' => $row->k1 ? (string) $row->k1 : '9999',
                default => str_pad((string) $this->digits($name), 4, '0', STR_PAD_LEFT).$name,
            };
            $totals[$name] ??= ['name' => $name, 'count' => 0, 'sort' => $sort];
            $totals[$name]['count'] += (int) $row->aggregate;
        }
        usort($totals, fn (array $a, array $b): int => strcmp($a['sort'], $b['sort']));

        return array_values($totals);
    }

    private function breakdownReply(array $spec, string $subject, array $conditions): string
    {
        $dimension = in_array($spec['dimension'] ?? null, self::DIMENSIONS, true) ? $spec['dimension'] : 'purok';
        $groups = $this->groups($spec['filters'], $dimension);
        $total = array_sum(array_column($groups, 'count'));
        $label = self::DIMENSION_LABELS[$dimension];

        if ($total === 0) {
            return 'I found no '.$this->describe(2, $subject, $conditions).' to break down.';
        }

        if ($spec['intent'] === 'rank') {
            $ranked = $groups;
            usort($ranked, fn (array $a, array $b): int => ($spec['order'] ?? 'desc') === 'asc' ? $a['count'] <=> $b['count'] : $b['count'] <=> $a['count']);
            $top = $ranked[0];
            $ties = array_values(array_filter($ranked, fn (array $row): bool => $row['count'] === $top['count']));
            $names = implode(' and ', array_column($ties, 'name'));
            $word = ($spec['order'] ?? 'desc') === 'asc' ? 'the fewest' : 'the most';
            $lines = implode("\n", array_map(fn (array $row): string => '• '.$row['name'].': '.$row['count'], array_slice($ranked, 0, 8)));

            return "{$names} ".(count($ties) > 1 ? 'have' : 'has')." {$word} ".$this->describe($top['count'], $subject, $conditions)
                ." ({$top['count']} of {$total}).\nRanking by {$label}:\n".$lines;
        }

        $lines = implode("\n", array_map(
            fn (array $row): string => '• '.$row['name'].': '.$row['count'].' ('.$this->percent($row['count'], $total).')',
            $groups
        ));

        return 'Here is the breakdown of '.$this->describe($total, $subject, $conditions)
            ." by {$label} (total {$total}):\n".$lines;
    }

    private function percent(int $part, int $whole): string
    {
        if ($whole === 0) {
            return '0%';
        }
        $value = round($part * 100 / $whole, 1);

        return (floor($value) === $value ? (string) (int) $value : number_format($value, 1)).'%';
    }

    private function hasMorePages(array $spec): bool
    {
        return $this->query($spec['filters'])->count() > ($spec['page'] ?? 1) * ($spec['limit'] ?? self::LIST_LIMIT);
    }

    private function listReply(array $spec, string $subject, array $conditions): string
    {
        $query = $this->query($spec['filters']);
        $total = (clone $query)->count();
        if ($total === 0) {
            return 'I found no '.$this->describe(2, $subject, $conditions).'.';
        }
        $limit = (int) ($spec['limit'] ?? self::LIST_LIMIT);
        $page = (int) ($spec['page'] ?? 1);
        $offset = ($page - 1) * $limit;
        if ($offset >= $total) {
            return 'That is the end of the list — all '.$total.' '.$this->describe($total, $subject, $conditions).' have been shown.';
        }

        $records = $query->select(['full_name', 'status', 'purok', 'grupo'])->orderBy('full_name')->orderBy('id')->offset($offset)->limit($limit)->get();
        $shown = $records->map(fn (Record $record): string => '• '.$this->line($record))->implode("\n");

        $heading = 'I found '.$total.' '.$this->describe($total, $subject, $conditions);
        if ($page === 1 && $total <= $limit) {
            return $heading.":\n".$shown;
        }
        $last = min($total, $offset + $limit);
        $more = $last < $total ? ' Say “more” for the next '.min($limit, $total - $last).', or use Inquire / Modify for the full list.' : '';

        return $page === 1
            ? $heading.'. Here are the first '.$limit.' (use Inquire / Modify for the full list, or say “more”):'."\n".$shown
            : $heading.". Showing ".($offset + 1)."–{$last}:{$more}\n".$shown;
    }

    private function line(Record $record): string
    {
        $place = trim((string) $record->purok) !== ''
            ? 'Purok '.trim((string) $record->purok).(trim((string) $record->grupo) !== '' ? '-'.trim((string) $record->grupo) : '')
            : 'no Purok';

        return (trim((string) $record->full_name) ?: 'Unnamed record').' — '.(trim((string) $record->status) ?: 'no status').', '.$place;
    }

    private function compareReply(array $spec): string
    {
        $rows = array_map(fn (array $part): array => ['label' => $part['label'], 'count' => $this->query($part['filters'])->count()], $spec['compare']);
        $total = array_sum(array_column($rows, 'count'));
        $common = $spec['compare'][0]['filters'];
        $lines = implode("\n", array_map(fn (array $row): string => '• '.$row['label'].': '.$row['count'].($total > 0 ? ' ('.$this->percent($row['count'], $total).')' : ''), $rows));

        $sorted = $rows;
        usort($sorted, fn (array $a, array $b): int => $b['count'] <=> $a['count']);
        if ($total === 0) {
            $summary = 'None of them have any matching records.';
        } elseif ($sorted[0]['count'] === $sorted[1]['count'] && count($rows) === 2) {
            $summary = 'They are tied.';
        } else {
            $difference = $sorted[0]['count'] - $sorted[1]['count'];
            $summary = "{$sorted[0]['label']} has the most — {$difference} more than {$sorted[1]['label']}"
                .($sorted[1]['count'] > 0 ? ' ('.$this->percent($difference, $sorted[1]['count']).' more)' : '').'.';
        }

        $subject = $this->subject($common);
        $noun = trim(($subject !== '' && ! $this->comparesKey($spec, 'status') ? $subject.' ' : '').'records');

        return "Comparison of {$noun}:\n{$lines}\n{$summary}";
    }

    private function comparesKey(array $spec, string $key): bool
    {
        $values = array_map(fn (array $part): string => serialize($part['filters'][$key] ?? null), $spec['compare']);

        return count(array_unique($values)) > 1;
    }

    private function percentReply(array $spec): string
    {
        $part = $this->query($spec['filters'])->count();
        $base = $spec['base'] ?? [];
        $whole = $this->query($base)->count();
        $baseText = $this->describeFilters($base, $whole);
        if ($whole === 0) {
            return "I found no {$this->describeFilters($base)} to take a percentage of.";
        }

        $extra = array_diff_key($spec['filters'], $base);
        foreach ($spec['filters'] as $key => $value) {
            if (isset($base[$key]) && $base[$key] !== $value) {
                $extra[$key] = $value;
            }
        }
        $what = $this->extraPhrase($extra);

        return $this->percent($part, $whole)." of {$baseText} {$what} ({$part} of {$whole}).";
    }

    /** "are female", "are in Purok 1", "are MS", "are married and in Purok 2". */
    private function extraPhrase(array $extra): string
    {
        $bits = [];
        if (isset($extra['status'])) {
            $bits[] = implode(' or ', $extra['status']);
        }
        if (isset($extra['kapisanan'])) {
            $bits[] = implode(' or ', array_map('ucfirst', (array) $extra['kapisanan']));
        }
        $conditions = $this->conditions(array_diff_key($extra, ['status' => 1, 'kapisanan' => 1, 'all_statuses' => 1]));
        $bits = array_merge($bits, $conditions['traits']);
        if ($conditions['location'] !== []) {
            $bits[] = 'in '.implode(', ', $conditions['location']);
        }
        if ($conditions['r210'] !== '') {
            $bits[] = trim(str_replace(' that are ', ' ', $conditions['r210']));
        }

        return $bits === [] ? 'match' : 'are '.implode(' and ', $bits);
    }

    private function extremeReply(array $spec, string $subject, array $conditions): string
    {
        $target = $spec['extreme'] ?? ['column' => 'date_of_birth', 'direction' => 'asc', 'label' => 'oldest'];
        $limit = max(1, min(self::MAX_LIMIT, (int) ($spec['limit'] ?? 1)));
        $records = $this->query($spec['filters'])
            ->whereNotNull($target['column'])
            ->select(['full_name', 'status', 'purok', 'grupo', 'date_of_birth', 'date_of_baptism', 'date_of_entry'])
            ->orderBy($target['column'], $target['direction'])
            ->orderBy('full_name')
            ->limit($limit)
            ->get();
        if ($records->isEmpty()) {
            return 'I found no '.$this->describe(2, $subject, $conditions).' with a '.str_replace(['date_of_', '_'], ['', ' '], $target['column']).' date recorded.';
        }

        // Manila's calendar date at midnight in the app timezone, like the stored dates, so ages turn on the birthday.
        $today = Carbon::parse(Carbon::now('Asia/Manila')->toDateString());
        $detail = function (Record $record) use ($target, $today): string {
            $date = $record->{$target['column']};

            return match ($target['column']) {
                'date_of_birth' => 'age '.(int) floor($date->diffInYears($today)),
                'date_of_baptism' => 'baptized '.$date->format('F Y'),
                default => 'entered '.$date->format('F Y'),
            };
        };
        $description = $this->describe($limit === 1 ? 1 : 2, $subject, $conditions);
        if ($records->count() === 1) {
            $record = $records->first();

            return 'The '.$target['label'].' '.$description.' is '.$this->line($record).', '.$detail($record).'.';
        }

        return 'The '.$records->count().' '.$target['label'].' '.$description.":\n"
            .$records->map(fn (Record $record): string => '• '.$this->line($record).', '.$detail($record))->implode("\n");
    }

    private function averageReply(array $filters, string $subject, array $conditions): string
    {
        $query = $this->query($filters);
        $total = (clone $query)->count();
        if ($total === 0) {
            return 'I found no '.$this->describe(2, $subject, $conditions).'.';
        }
        // Only the birthdays are read (one column), not whole records. Manila date, app timezone (see above).
        $today = Carbon::parse(Carbon::now('Asia/Manila')->toDateString());
        $ages = $query->whereNotNull('date_of_birth')->pluck('date_of_birth')
            ->map(fn ($date): int => (int) floor(Carbon::parse($date)->diffInYears($today)));
        if ($ages->isEmpty()) {
            return 'None of the '.$total.' '.$this->describe($total, $subject, $conditions).' have a birthday recorded, so I cannot work out an average age.';
        }
        $average = round($ages->avg(), 1);
        $missing = $total - $ages->count();

        return 'The average age of '.$this->describe($total, $subject, $conditions).' is '.$average.' years (youngest '.$ages->min().', oldest '.$ages->max()
            .', based on '.$ages->count().' with a birthday'.($missing > 0 ? "; {$missing} ".($missing === 1 ? 'has' : 'have').' no birthday recorded' : '').').';
    }
}
