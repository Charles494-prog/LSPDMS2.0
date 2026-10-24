<?php

namespace App\Services;

/**
 * The small, offline language layer every LSPBot part shares: it cleans a message
 * (case, accents, quotes, contractions, Taglish fillers), turns number words into
 * digits where they name a Purok, Grupo or amount, fixes common typos with a
 * Damerau-Levenshtein distance, splits a message that holds several questions,
 * and tells English from Tagalog. Everything is deterministic and runs without
 * the internet.
 */
final class LspBotLanguage
{
    /** English and Tagalog words that carry no meaning for matching. */
    public const STOPWORDS = [
        'a', 'an', 'the', 'is', 'are', 'am', 'was', 'were', 'be', 'do', 'does', 'did', 'i', 'me', 'my', 'we', 'you', 'your',
        'to', 'of', 'in', 'on', 'for', 'at', 'by', 'from', 'with', 'and', 'or', 'it', 'this', 'that', 'there', 'please', 'pls',
        'ang', 'ng', 'sa', 'mga', 'po', 'opo', 'ba', 'naman', 'na', 'ay', 'yung', 'yong', 'lang', 'nga', 'kaya', 'pala', 'daw',
        'raw', 'din', 'rin', 'eh', 'ha', 'diba', 'kasi', 'nalang', 'nman', 'nmn', 'pa', 'ung',
    ];

    /** Number words (English, Tagalog, Spanish-Tagalog) up to twenty. */
    private const NUMBERS = [
        'one' => 1, 'two' => 2, 'three' => 3, 'four' => 4, 'five' => 5, 'six' => 6, 'seven' => 7, 'eight' => 8, 'nine' => 9,
        'ten' => 10, 'eleven' => 11, 'twelve' => 12, 'thirteen' => 13, 'fourteen' => 14, 'fifteen' => 15, 'sixteen' => 16,
        'seventeen' => 17, 'eighteen' => 18, 'nineteen' => 19, 'twenty' => 20, 'thirty' => 30, 'forty' => 40, 'fifty' => 50,
        'sixty' => 60, 'seventy' => 70, 'eighty' => 80, 'ninety' => 90,
        'isa' => 1, 'dalawa' => 2, 'tatlo' => 3, 'apat' => 4, 'lima' => 5, 'anim' => 6, 'pito' => 7, 'walo' => 8, 'siyam' => 9,
        'sampu' => 10,
        'uno' => 1, 'dos' => 2, 'tres' => 3, 'kwatro' => 4, 'kuwatro' => 4, 'singko' => 5, 'sais' => 6, 'syete' => 7, 'siyete' => 7,
        'otso' => 8, 'nuwebe' => 9, 'nueve' => 9, 'diyes' => 10, 'dyes' => 10,
    ];

    /** Ordinals ("first purok", "ikalawang purok"). */
    private const ORDINALS = [
        'first' => 1, 'second' => 2, 'third' => 3, 'fourth' => 4, 'fifth' => 5, 'sixth' => 6, 'seventh' => 7, 'eighth' => 8,
        'ninth' => 9, 'tenth' => 10,
        'una' => 1, 'unang' => 1, 'ikalawa' => 2, 'ikalawang' => 2, 'ikatlo' => 3, 'ikatlong' => 3, 'ikaapat' => 4, 'ikaapat na' => 4,
        'ikalima' => 5, 'ikalimang' => 5, 'ikaanim' => 6, 'ikapito' => 7, 'ikawalo' => 8, 'ikasiyam' => 9, 'ikasampu' => 10,
    ];

    /** Shorthand, chat spelling and contractions, applied word by word. */
    private const SHORTHAND = [
        "what's" => 'what is', "whats" => 'what is', "who's" => 'who is', "where's" => 'where is', "how's" => 'how is',
        "it's" => 'it is', "i'm" => 'i am', "there's" => 'there is',
        'u' => 'you', 'ur' => 'your', 'r' => 'are', 'pls' => 'please', 'plz' => 'please', 'thx' => 'thanks', 'ty' => 'thanks',
        'tnx' => 'thanks', 'pano' => 'paano', 'panu' => 'paano', 'san' => 'saan',
        'kelan' => 'kailan', 'nyo' => 'ninyo', 'nlng' => 'nalang', 'dn' => 'din', 'cno' => 'sino', 'cnu' => 'sino', 'sinu' => 'sino',
        'ilng' => 'ilang', 'w/o' => 'without', 'w/' => 'with', 'yrs' => 'years', 'bday' => 'birthday', 'bdays' => 'birthdays', 'b-day' => 'birthday', 'pruok' => 'purok', 'puork' => 'purok',
    ];

    /** Words fixed when misspelled (one edit, or two for long words). */
    public const VOCABULARY = [
        'purok', 'puroks', 'grupo', 'grupos', 'members', 'member', 'records', 'record', 'female', 'females', 'male', 'males',
        'married', 'single', 'inactive', 'active', 'baptized', 'baptism', 'kapisanan', 'buklod', 'kadiwa', 'binhi', 'breakdown',
        'percentage', 'percent', 'compare', 'versus', 'endorsed', 'endorsement', 'eligible', 'birthday', 'birthdays', 'between',
        'oldest', 'youngest', 'seniors', 'minors', 'gender', 'status', 'registry', 'missing', 'without', 'entered', 'average',
        'deliberation', 'delistment', 'delisted', 'session', 'monthly', 'weekly', 'secretary', 'secretaries', 'dashboard',
        'messages', 'message', 'tasks', 'backup', 'generator', 'snapshot', 'resumen', 'tarheta', 'subtract', 'mention',
        'reminder', 'profile', 'password', 'launcher', 'january', 'february', 'march', 'april', 'june', 'july', 'august',
        'september', 'october', 'november', 'december', 'many', 'how', 'list', 'show', 'total', 'count', 'where', 'what',
    ];

    /** Real words one letter away from a vocabulary word, never "fixed". */
    private const KEEP = [
        'match', 'sender', 'tender', 'render', 'carried', 'kissing', 'statue', 'mirrors', 'groups', 'group', 'mails', 'female',
        'males', 'mile', 'make', 'many', 'mean', 'man', 'men', 'hot', 'who', 'now', 'new', 'show', 'shot', 'snow', 'lost', 'last',
        'least', 'list', 'mist', 'cost', 'host', 'post', 'past', 'test', 'lust', 'mass', 'messages', 'passages', 'wear', 'were',
        'here', 'there', 'when', 'whet', 'what', 'that', 'chat', 'hat', 'wat', 'total', 'tonal', 'count', 'court', 'mount',
        'memebers', 'active', 'action', 'access', 'actual', 'tasks', 'masks', 'task', 'mask', 'ask', 'asks', 'birthday', 'session',
        'sessions', 'mention', 'motion', 'cession', 'messenger', 'marks', 'marked', 'tasked', 'listed', 'posted', 'mentioned',
        'session', 'single', 'singles', 'sample', 'simple', 'months', 'monthly', 'weekly', 'record', 'reword', 'genders',
    ];

    /** Clean text: lower case, no accents, plain quotes and spacing, shorthand expanded, number words as digits. */
    public static function normalize(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = self::stripAccents($text);
        $text = str_replace(['’', '‘', '`', '“', '”', '…'], ["'", "'", "'", '"', '"', ' '], $text);
        $text = preg_replace('/([!?.,])\1+/u', '$1', $text) ?? $text;
        // Stretched words in chat ("hellooo", "salamaaat") lose their repeated letters.
        $text = preg_replace('/(\p{L})\1{2,}/u', '$1', $text) ?? $text;
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        $text = preg_replace_callback("/(?<![\\p{L}\\p{N}])[\\p{L}\\/'-]+(?![\\p{L}\\p{N}])/u", fn (array $m): string => self::SHORTHAND[$m[0]] ?? $m[0], $text) ?? $text;

        return trim(self::numberWords($text));
    }

    public static function stripAccents(string $text): string
    {
        return strtr($text, [
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i', 'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ñ' => 'n', 'ç' => 'c',
        ]);
    }

    /**
     * "purok one" → "purok 1", "ikalawang purok" → "purok 2", "top five" → "top 5", "isang" → "1" before a noun.
     * Number words elsewhere ("no one", "lima" as a name) are left alone.
     */
    public static function numberWords(string $text): string
    {
        $numbers = implode('|', array_keys(self::NUMBERS));
        $ordinals = implode('|', array_map(fn (string $w): string => preg_quote($w, '/'), array_keys(self::ORDINALS)));

        // "twenty five" / "sixty-five" → "65", so the steps below see one number.
        $text = preg_replace_callback('/\b(twenty|thirty|forty|fifty|sixty|seventy|eighty|ninety)[\s-](one|two|three|four|five|six|seven|eight|nine)\b/u',
            fn (array $m): string => (string) (self::NUMBERS[$m[1]] + self::NUMBERS[$m[2]]), $text) ?? $text;
        $text = preg_replace_callback('/\b(purok|puroks|grupo|grupos|group|groups|top|first|last|list|show|ilista|ipakita)\s+('.$numbers.')(?:ng)?\b/u',
            fn (array $m): string => $m[1].' '.self::NUMBERS[$m[2]], $text) ?? $text;
        $text = preg_replace_callback('/\b(?:ika-?\s*)?('.$ordinals.')\s+(?:na\s+)?(purok|grupo)\b/u',
            fn (array $m): string => $m[2].' '.self::ORDINALS[$m[1]], $text) ?? $text;
        $text = preg_replace('/\bika-?\s*(\d{1,2})(?:ng)?\s+(?:na\s+)?(purok|grupo)\b/u', '$2 $1', $text) ?? $text;
        $text = preg_replace_callback('/\b('.$numbers.')(?:ng)?\s+(oldest|youngest|eldest|newest|latest|names|members|records|people|kapatid|ms|uwp|mk)\b/u',
            fn (array $m): string => self::NUMBERS[$m[1]].' '.$m[2], $text) ?? $text;
        // Ages written out: "over sixty" is rare, but "twenty years old" happens.
        $text = preg_replace_callback('/\b('.$numbers.')\s+(years? old|taong gulang)\b/u',
            fn (array $m): string => self::NUMBERS[$m[1]].' '.$m[2], $text) ?? $text;
        // Age conditions: "under eighteen", "above sixty", "between eighteen and thirty", "edad dalawampu".
        $text = preg_replace_callback('/\b(under|over|above|below|between|aged|ages?|edad|older than|younger than|less than|more than|at least|at most|lampas|mahigit|higit sa|kulang sa|from)\s+('.$numbers.')\b/u',
            fn (array $m): string => $m[1].' '.self::NUMBERS[$m[2]], $text) ?? $text;
        // The rest of a list or range after a number: "purok 1 and two", "between 18 and thirty", "purok 1 vs two".
        do {
            $before = $text;
            $text = preg_replace_callback('/(\b\d{1,3}[a-f]?\s*(?:,|and|at|or|o|to|hanggang|&|vs\.?|versus)\s*)('.$numbers.')\b(?!\s+pa\b)/u',
                fn (array $m): string => $m[1].self::NUMBERS[$m[2]], $text) ?? $text;
        } while ($text !== $before);
        return $text;
    }

    /** Fixes slips like "pruok", "membrs", "deliberaton", "hw many"; names and numbers are left alone. */
    public static function fixTypos(string $text, array $vocabulary = self::VOCABULARY): string
    {
        return preg_replace_callback('/\p{L}+/u', function (array $match) use ($vocabulary): string {
            $word = $match[0];
            $length = strlen($word);
            if ($length < 5 || in_array($word, $vocabulary, true) || in_array($word, self::KEEP, true) || in_array($word, self::STOPWORDS, true)) {
                return $word;
            }
            $best = null;
            $bestDistance = PHP_INT_MAX;
            foreach ($vocabulary as $target) {
                $targetLength = strlen($target);
                if ($targetLength < 5 || abs($targetLength - $length) > 2) {
                    continue;
                }
                $allowed = min($length, $targetLength) >= 8 ? 2 : 1;
                // Short words keep their first letter ("dates" never becomes "males").
                if ($allowed === 1 && $length <= 6 && $word[0] !== $target[0]) {
                    continue;
                }
                $distance = self::distance($word, $target);
                if ($distance <= $allowed && $distance < $bestDistance) {
                    $best = $target;
                    $bestDistance = $distance;
                }
            }

            return $best ?? $word;
        }, $text) ?? $text;
    }

    /** Optimal-string-alignment (Damerau-Levenshtein) distance: a swap of two neighbouring letters costs 1. */
    public static function distance(string $a, string $b): int
    {
        if ($a === $b) {
            return 0;
        }
        $n = strlen($a);
        $m = strlen($b);
        if ($n === 0 || $m === 0) {
            return max($n, $m);
        }
        $d = [];
        for ($i = 0; $i <= $n; $i++) {
            $d[$i][0] = $i;
        }
        for ($j = 0; $j <= $m; $j++) {
            $d[0][$j] = $j;
        }
        for ($i = 1; $i <= $n; $i++) {
            for ($j = 1; $j <= $m; $j++) {
                $cost = $a[$i - 1] === $b[$j - 1] ? 0 : 1;
                $d[$i][$j] = min($d[$i - 1][$j] + 1, $d[$i][$j - 1] + 1, $d[$i - 1][$j - 1] + $cost);
                if ($i > 1 && $j > 1 && $a[$i - 1] === $b[$j - 2] && $a[$i - 2] === $b[$j - 1]) {
                    $d[$i][$j] = min($d[$i][$j], $d[$i - 2][$j - 2] + 1);
                }
            }
        }

        return $d[$n][$m];
    }

    /** True when two words are the same word with a small typo (length-aware). */
    public static function similar(string $a, string $b): bool
    {
        if ($a === $b) {
            return true;
        }
        $lengthA = strlen($a);
        $lengthB = strlen($b);
        if ($lengthA < 5 || $lengthB < 5 || abs($lengthA - $lengthB) > 2) {
            return false;
        }

        return self::distance($a, $b) <= (min($lengthA, $lengthB) >= 8 ? 2 : 1);
    }

    /** @return list<string> meaningful words of an already-normalized text */
    public static function tokens(string $text): array
    {
        $words = preg_split('/[^\p{L}\p{N}+-]+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_filter($words, fn (string $w): bool => ! in_array($w, self::STOPWORDS, true)));
    }

    /** True when the message reads as Tagalog or Taglish, so the Tagalog answer can be used. */
    public static function isTagalog(string $text): bool
    {
        $hits = preg_match_all('/\b(?:ang|ng|mga|sa|po|opo|ba|naman|ilan|ilang|ano|anong|paano|saan|sino|bakit|kailan|ako|ko|mo|ka|siya|kami|tayo|sila|ito|yan|iyan|yun|wala|walang|meron|gusto|pwede|puwede|salamat|kumusta|kamusta|lahat|babae|lalaki|ngayon|bukas|kahapon|taon|buwan|araw|magandang|umaga|hapon|gabi|kumain|tulog|salamat)\b/u', mb_strtolower($text));

        return $hits >= 1;
    }

    /**
     * Splits "how many ms in purok 1? how about uwp?" or "count the ms; list the uwp" into separate questions.
     * A single question (even with "and" inside, like "ms in purok 1 and 2") stays whole.
     *
     * @return list<string>
     */
    public static function splitQuestions(string $message): array
    {
        $message = trim($message);
        $starts = '(?=(?:how|what|who|where|which|when|is|are|do|does|can|list|show|ilan|ilang|ano|anong|sino|saan|paano|may|count|give)\b)';
        $parts = preg_split('/(?<=[?])\s+'.$starts.'|\s*;\s*'.$starts.'|\s+(?:and also|and then|then also|tapos|at saka|atsaka|also,)\s+'.$starts.'/iu', $message) ?: [$message];
        $parts = array_map('trim', $parts);
        // Every part must be a real question (a few letters), or the message is kept whole.
        foreach ($parts as $part) {
            if (preg_match_all('/\p{L}{2,}/u', $part) < 2) {
                return [$message];
            }
        }

        return count($parts) > 1 ? array_slice($parts, 0, 4) : [$message];
    }
}
