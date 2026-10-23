<?php

namespace App\Services;

/**
 * Finds the best answer in config/lspbot.php for a free-text question.
 *
 * Scores every entry two ways and keeps the stronger signal:
 *   1. keywords  — short phrases that strongly point at the entry
 *   2. questions — IDF-weighted overlap with the example questions (tolerates typos)
 * Optional entry fields: 'not' (phrases that block the entry), 'boost' (0–0.3 tie-breaker).
 */
class LspBotMatcher
{
    /** @var array<string, true> */
    private array $stopwords = [];

    /** @var array<string, string> */
    private array $synonyms = [];

    /** @var array<string, string> */
    private array $phrases = [];

    /** @var list<string> */
    private array $greetings = [];

    /** @var array<string, string> */
    private array $exact = [];

    /** @var array<string, array{entry: array, keywords: list<string>, not: list<string>, examples: list<list<string>>}> */
    private array $index = [];

    /** @var array<string, float> token => importance weight */
    private array $idf = [];

    private float $unseenWeight = 1.0;
    private float $threshold;
    private float $suggestThreshold;

    public function __construct()
    {
        $config = config('lspbot');
        $this->threshold = (float) ($config['threshold'] ?? 0.55);
        $this->suggestThreshold = (float) ($config['suggest_threshold'] ?? 0.30);

        $phrases = [];
        foreach ($config['phrases'] ?? [] as $from => $to) {
            $phrases[$this->clean((string) $from)] = (string) $to;
        }
        uksort($phrases, fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));
        $this->phrases = $phrases;

        foreach ($config['stopwords'] ?? [] as $word) {
            $this->stopwords[$this->clean((string) $word)] = true;
        }
        foreach ($config['synonyms'] ?? [] as $canonical => $variants) {
            foreach ($variants as $variant) {
                $this->synonyms[$this->clean((string) $variant)] = (string) $canonical;
            }
        }
        foreach ($config['greeting_words'] ?? [] as $greeting) {
            $this->greetings[] = $this->plain((string) $greeting);
        }
        usort($this->greetings, fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));

        $documentFrequency = [];

        foreach ($config['entries'] ?? [] as $key => $entry) {
            $keywords = [];
            $entryTokens = [];
            foreach ($entry['keywords'] ?? [] as $keyword) {
                $tokens = $this->tokens((string) $keyword);
                $canonical = implode(' ', $tokens);
                if ($canonical !== '') {
                    $keywords[] = $canonical;
                    foreach ($tokens as $t) {
                        $entryTokens[$t] = true;
                    }
                }
            }
            $not = [];
            foreach ($entry['not'] ?? [] as $phrase) {
                $canonical = implode(' ', $this->tokens((string) $phrase));
                if ($canonical !== '') {
                    $not[] = $canonical;
                }
            }
            $examples = [];
            foreach ($entry['questions'] ?? [] as $question) {
                $this->exact[$this->plain((string) $question)] ??= (string) $key;
                $tokens = array_values(array_unique($this->tokens((string) $question)));
                if ($tokens !== []) {
                    $examples[] = $tokens;
                    foreach ($tokens as $t) {
                        $entryTokens[$t] = true;
                    }
                }
            }
            foreach (array_keys($entryTokens) as $t) {
                $documentFrequency[$t] = ($documentFrequency[$t] ?? 0) + 1;
            }
            $this->index[(string) $key] = [
                'entry' => $entry + ['key' => (string) $key],
                'keywords' => $keywords,
                'not' => $not,
                'examples' => $examples,
            ];
        }

        $total = max(1, count($this->index));
        foreach ($documentFrequency as $token => $df) {
            $this->idf[$token] = 1.0 + log($total / $df);
        }
        $this->unseenWeight = 1.0 + log($total);
    }

    /**
     * @return array{entry: ?array, score: float, suggestions: list<string>}
     */
    public function match(string $message): array
    {
        $plain = $this->plain($message);
        $withoutPo = trim(preg_replace('/(?<![\p{L}\p{N}])(?:po|opo|please|pls)(?![\p{L}\p{N}])/u', ' ', $plain) ?? $plain);
        foreach ([$plain, preg_replace('/\s+/u', ' ', $withoutPo)] as $candidate) {
            if (isset($this->exact[$candidate])) {
                return ['entry' => $this->index[$this->exact[$candidate]]['entry'], 'score' => 1.0, 'suggestions' => []];
            }
        }
        $stripped = $plain;
        foreach ($this->greetings as $greeting) {
            $stripped = preg_replace($this->wordPattern($greeting), ' ', $stripped) ?? $stripped;
        }
        $stripped = trim(preg_replace('/\s+/u', ' ', $stripped) ?? $stripped);
        $hadGreeting = $stripped !== $plain;

        $queryTokens = $this->tokens($stripped);
        if ($queryTokens === []) {
            if ($hadGreeting && isset($this->index['greeting'])) {
                return ['entry' => $this->index['greeting']['entry'], 'score' => 1.0, 'suggestions' => []];
            }

            return ['entry' => null, 'score' => 0.0, 'suggestions' => []];
        }

        $unique = array_values(array_unique($queryTokens));
        $queryString = ' '.implode(' ', $queryTokens).' ';
        $ranked = [];

        foreach ($this->index as $key => $item) {
            foreach ($item['not'] as $blocked) {
                if (str_contains($queryString, ' '.$blocked.' ')) {
                    continue 2;
                }
            }

            $keywordScore = 0.0;
            foreach ($item['keywords'] as $keyword) {
                $hit = str_contains($queryString, ' '.$keyword.' ');
                $fuzzy = false;
                if (! $hit && ! str_contains($keyword, ' ') && mb_strlen($keyword) >= 5) {
                    foreach ($unique as $token) {
                        if ($this->similar($token, $keyword)) {
                            $hit = $fuzzy = true;
                            break;
                        }
                    }
                }
                if (! $hit) {
                    continue;
                }
                $score = min(1.0, 0.35 + 0.08 * mb_strlen($keyword));
                $coverage = min(1.0, count(explode(' ', $keyword)) / count($unique));
                $score *= 0.6 + 0.4 * min(1.0, $coverage * 2.5);
                $keywordScore = max($keywordScore, $fuzzy ? $score * 0.8 : $score);
            }

            $exampleScore = 0.0;
            foreach ($item['examples'] as $example) {
                $exampleScore = max($exampleScore, $this->exampleScore($unique, $example));
            }

            $score = max($keywordScore, $exampleScore) + 0.15 * min($keywordScore, $exampleScore);
            if ($score > 0) {
                $score += (float) ($item['entry']['boost'] ?? 0);
            }
            $score = min(1.0, $score);
            if ($score > 0) {
                $ranked[$key] = $score;
            }
        }

        arsort($ranked);
        $bestKey = array_key_first($ranked);

        if ($bestKey !== null && $ranked[$bestKey] >= $this->threshold) {
            return ['entry' => $this->index[$bestKey]['entry'], 'score' => $ranked[$bestKey], 'suggestions' => []];
        }

        $suggestions = [];
        foreach ($ranked as $key => $score) {
            $entry = $this->index[$key]['entry'];
            if ($score >= $this->suggestThreshold && ($entry['suggest'] ?? true) && ! empty($entry['title'])) {
                $suggestions[] = (string) $entry['title'];
            }
            if (count($suggestions) === 3) {
                break;
            }
        }

        return ['entry' => null, 'score' => $bestKey !== null ? $ranked[$bestKey] : 0.0, 'suggestions' => $suggestions];
    }

  public function answerFor(array $entry, string $message = ''): string
{
    $tagalog = $message !== '' && preg_match(
        '/\b(saan|paano|pano|bakit|kailan|kelan|ano|anong|ako|ko|mo|po|opo|naman|puwede|pwede|ayaw|kulang|gusto|mga|salamat|walang|nakapag|ulit|daw|ang|ng|magpalit|makikita|makakabasa)\b/iu',
        $message
    ) === 1;

    $answer = ($tagalog && ! empty($entry['answer_tl'])) ? $entry['answer_tl'] : ($entry['answer'] ?? '');

    return is_array($answer) && $answer !== [] ? (string) $answer[array_rand($answer)] : (string) $answer;
}

    private function weight(string $token): float
    {
        return $this->idf[$token] ?? $this->unseenWeight;
    }

    /** Weighted F-score between the question's words and an example's words (typos count 0.8). */
    private function exampleScore(array $query, array $example): float
    {
        $common = 0.0;
        $queryTotal = 0.0;
        foreach ($query as $token) {
            $w = $this->weight($token);
            $queryTotal += $w;
            if (in_array($token, $example, true)) {
                $common += $w;
                continue;
            }
            foreach ($example as $other) {
                if ($this->similar($token, $other)) {
                    $common += 0.8 * $w;
                    break;
                }
            }
        }
        if ($common <= 0) {
            return 0.0;
        }
        $exampleTotal = 0.0;
        foreach ($example as $token) {
            $exampleTotal += $this->weight($token);
        }
        $coverage = min(1.0, $common / $queryTotal);
        $precision = min(1.0, $common / $exampleTotal);

        return 2 * $coverage * $precision / ($coverage + $precision);
    }

    private function similar(string $a, string $b): bool
    {
        $lengthA = strlen($a);
        $lengthB = strlen($b);
        if ($lengthA < 5 || $lengthB < 5 || abs($lengthA - $lengthB) > 2 || $a === $b) {
            return $a === $b;
        }

        return levenshtein($a, $b) <= (min($lengthA, $lengthB) >= 8 ? 2 : 1);
    }

    /** @return list<string> */
    private function tokens(string $text): array
    {
        $out = [];
        foreach (explode(' ', $this->plain($text)) as $raw) {
            if ($raw === '' || isset($this->stopwords[$raw])) {
                continue;
            }
            $out[] = $this->canonical($raw);
        }

        return $out;
    }

    private function canonical(string $word): string
    {
        if (isset($this->synonyms[$word])) {
            return $this->synonyms[$word];
        }
        $stem = $this->stem($word);

        return $this->synonyms[$stem] ?? $stem;
    }

    private function stem(string $word): string
    {
        $length = mb_strlen($word);
        if ($length > 5 && str_ends_with($word, 'ing')) {
            return mb_substr($word, 0, -3);
        }
        if ($length > 4 && str_ends_with($word, 'ed')) {
            return mb_substr($word, 0, -2);
        }
        if ($length > 3 && str_ends_with($word, 's') && ! str_ends_with($word, 'ss') && ! str_ends_with($word, 'us')) {
            return mb_substr($word, 0, -1);
        }

        return $word;
    }

    private function clean(string $text): string
    {
        $text = mb_strtolower($text);
        $text = preg_replace('/[\'’‘`]/u', '', $text) ?? $text;
        $text = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    private function plain(string $text): string
    {
        $text = $this->clean($text);
        foreach ($this->phrases as $from => $to) {
            $text = preg_replace($this->wordPattern($from), $to, $text) ?? $text;
        }

        return $text;
    }

    private function wordPattern(string $phrase): string
    {
        return '/(?<![\p{L}\p{N}])'.preg_quote($phrase, '/').'(?![\p{L}\p{N}])/u';
    }
}