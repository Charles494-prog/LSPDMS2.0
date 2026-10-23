<?php

namespace App\Http\Controllers;

use App\Models\GlobalVariable;
use App\Models\Record;
use App\Services\LspBotMatcher;
use App\Services\R210Eligibility;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BuiltInHelpChatbotController extends Controller
{
    // What LSPBot knows (questions, answers, links, permissions) now lives in config/lspbot.php.

    public function index(Request $request): mixed
    {
        $user = $request->session()->get('login_user', []);

        return view('messages.assistant', [
            'authenticatedUser' => $user,
        ]);
    }

    public function reply(Request $request, R210Eligibility $eligibility, LspBotMatcher $matcher): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:500'],
        ]);
        $user = $request->session()->get('login_user', []);
        $permissions = $this->permissions($user);
        $originalMessage = trim($validated['message']);
        $message = mb_strtolower($originalMessage);

        if ($this->isRecordQuery($message)) {
            if (! $permissions['chatbot_records_allow']) {
                return response()->json([
                    'reply' => 'You do not have permission to look up member records through this chatbot. Ask your Head Secretary to enable “Allow chatbot record lookups” for your account.',
                ]);
            }

            $aggregate = $this->aggregateRequest($message);
            if ($aggregate !== null) {
                if ($aggregate['r210'] && ! $permissions['Delistment_allow']) {
                    return response()->json([
                        'reply' => 'Your account cannot view R2-10 endorsement information. Ask your Head Secretary to enable De-Listment access.',
                    ]);
                }

                return response()->json([
                    'reply' => $this->aggregateReply($aggregate, $eligibility),
                ]);
            }

            $name = $this->lookupName($originalMessage);
            if ($name !== null) {
                return response()->json([
                    'reply' => $this->lookupReply($name, $permissions['Delistment_allow']),
                ]);
            }
        }

        $result = $matcher->match($originalMessage);
        $topic = $result['entry'];

        if ($topic === null) {
            // Unanswered questions are logged (storage/logs/laravel.log) so you can see what to teach LSPBot next.
            Log::info('LSPBot could not match a question.', ['message' => $originalMessage, 'score' => round($result['score'], 2)]);

            if ($result['suggestions'] !== []) {
                $options = implode("\n", array_map(fn (string $title): string => '• '.$title, $result['suggestions']));

                return response()->json([
                    'reply' => "I’m not completely sure what you meant. Did you mean one of these?\n{$options}\nTry asking again with a few more details about what you want to do.",
                ]);
            }

            return response()->json(['reply' => (string) config('lspbot.fallback')]);
        }

        $feature = $topic['feature'] ?? null;
        $answer = $matcher->answerFor($topic);

        if ($feature !== null && ! ($permissions[$feature] ?? false)) {
            $title = $topic['title'] ?? 'this feature';

            return response()->json([
                'reply' => "You do not currently have permission to open {$title}. Ask your Head Secretary to enable the required access. I have not provided a link because your account cannot access this feature.",
            ]);
        }

        $response = ['reply' => $answer];
        if (! empty($topic['route'])) {
            $response['link'] = [
                'label' => $topic['link_label'] ?? 'Open '.($topic['title'] ?? 'this page'),
                'url' => route($topic['route']),
            ];
        }

        return response()->json($response);
    }

    private function permissions(array $user): array
    {
        $role = mb_strtolower(trim(preg_replace('/\s+/', ' ', (string) ($user['tungkulin'] ?? '')) ?? ''));
        $isHeadSecretary = in_array($role, ['head secretary', 'secretary head'], true);

        return [
            'head_secretary' => $isHeadSecretary,
            'dashboard_allow' => $isHeadSecretary || (bool) ($user['dashboard_allow'] ?? false),
            'Delistment_allow' => $isHeadSecretary || (bool) ($user['Delistment_allow'] ?? false),
            'Inquire_Allow' => $isHeadSecretary || (bool) ($user['Inquire_Allow'] ?? false),
            'form_generator_allow' => $isHeadSecretary || (bool) ($user['form_generator_allow'] ?? false),
            'filter_bnh_helper_allow' => $isHeadSecretary || (bool) ($user['filter_bnh_helper_allow'] ?? false),
            'tarheta_status_allow' => $isHeadSecretary || (bool) ($user['tarheta_status_allow'] ?? false),
            'chatbot_records_allow' => $isHeadSecretary || (bool) ($user['chatbot_records_allow'] ?? false),
        ];
    }

    private function isRecordQuery(string $message): bool
    {
        return $this->aggregateRequest($message) !== null
            || $this->lookupName($message) !== null;
    }

    private function aggregateRequest(string $message): ?array
    {
        $isCountQuestion = preg_match('/\b(?:how many|count|number of|total)\b/i', $message) === 1;
        $eligible = preg_match('/\beligible\b/i', $message) === 1;
        $mentionsR210 = preg_match('/\br2[\s-]?10\b|\bendorse(?:d|ment)?\b|\bunendorsed\b/i', $message) === 1
            || $eligible;
        $status = null;
        if (preg_match('/\b(MS|UWP|MK)\b/i', $message, $matches) === 1) {
            $status = mb_strtoupper($matches[1]);
        }
        $kapisanan = match (true) {
            preg_match('/\bbinhis?\b/i', $message) === 1 => 'binhi',
            preg_match('/\bkadiwas?\b/i', $message) === 1 => 'kadiwa',
            preg_match('/\bbuklod\b/i', $message) === 1 => 'buklod',
            default => null,
        };

        if (! $isCountQuestion || ($status === null && $kapisanan === null && ! $mentionsR210
            && preg_match('/\b(?:records?|members?|active)\b/i', $message) !== 1)) {
            return null;
        }

        $notEndorsed = preg_match('/\b(?:not|never|haven.t|hasn.t|has not|have not|pending|unendorsed)\b/i', $message) === 1
            && $mentionsR210;
        $endorsed = preg_match('/\bendorsed\b/i', $message) === 1 && ! $notEndorsed;

        return [
            'status' => $status,
            'kapisanan' => $kapisanan,
            'r210' => $mentionsR210,
            'not_endorsed' => $notEndorsed,
            'endorsed' => $endorsed,
            'eligible' => $eligible,
        ];
    }

    private function aggregateReply(array $query, R210Eligibility $eligibility): string
    {
        $records = Record::query();
        if ($query['status'] !== null) {
            $records->whereRaw('UPPER(TRIM(COALESCE(status, \'\'))) = ?', [$query['status']]);
        } else {
            $records->whereIn(DB::raw('UPPER(TRIM(COALESCE(status, \'\')))'), ['MS', 'UWP', 'MK']);
        }

        if ($query['kapisanan'] !== null) {
            $groupValues = match ($query['kapisanan']) {
                'binhi' => ['binhi'],
                'kadiwa' => ['k', 'kadiwa'],
                'buklod' => ['b', 'buklod'],
                default => [],
            };
            $records->whereIn(DB::raw('LOWER(TRIM(COALESCE(kapisanan, \'\')))'), $groupValues);
        }

        if ($query['r210'] && $query['not_endorsed']) {
            $records->whereRaw('UPPER(TRIM(COALESCE(r2_10_endorsement, \'\'))) <> ?', ['ENDORSED']);
        } elseif ($query['r210'] && ($query['endorsed'] || ! $query['eligible'])) {
            $records->whereRaw('UPPER(TRIM(COALESCE(r2_10_endorsement, \'\'))) = ?', ['ENDORSED']);
        }

        if ($query['eligible']) {
            $requirements = GlobalVariable::query()->first();
            $count = $records
                ->get()
                ->filter(fn (Record $record): bool => $eligibility->forRecord($record, $requirements))
                ->count();
        } else {
            $count = $records->count();
        }

        $status = $query['status'] ?? 'active';
        if ($query['r210']) {
            if ($query['eligible'] && $query['not_endorsed']) {
                return "There are {$count} {$status} record(s) eligible for R2-10 that are not yet endorsed.";
            }
            if ($query['eligible']) {
                return "There are {$count} {$status} record(s) eligible for R2-10.";
            }

            $endorsement = $query['not_endorsed'] ? 'not yet endorsed' : 'endorsed';

            return "There are {$count} {$status} record(s) that are {$endorsement} for R2-10.";
        }
        if ($query['kapisanan'] !== null) {
            $group = ucfirst($query['kapisanan']);

            return "There are {$count} {$status} {$group} record(s).";
        }

        return "There are {$count} {$status} record(s).";
    }

    private function lookupName(string $message): ?string
    {
        if (preg_match('/\b(?:edit|modify|change|update|correct|delete|remove|delist)\b/i', $message) === 1) {
            return null;
        }

        $patterns = [
            '/^\s*(?:please\s+)?(?:find|search|look\s*up|lookup)\s+(?:for\s+)?(.+?)(?:\s+(?:in|from)\s+(?:(?:our|the)\s+)?(?:records?|database))?[?.!]*\s*$/iu',
            '/^\s*(?:is|do we have)\s+(.+?)\s+(?:in|on)\s+(?:(?:our|the)\s+)?(?:records?|database)[?.!]*\s*$/iu',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $message, $matches) === 1) {
                $name = trim($matches[1], " \t\n\r\0\x0B?.!,");
                if (mb_strlen($name) >= 2 && preg_match('/\b(?:member|record|records|database)\b/i', $name) !== 1) {
                    return $name;
                }
            }
        }

        return null;
    }

    private function lookupReply(string $name, bool $canViewEndorsement): string
    {
        $escapedName = strtr(mb_strtolower($name), ['!' => '!!', '%' => '!%', '_' => '!_']);
        $records = Record::query()
            ->select(['full_name', 'status', 'purok', 'kapisanan', 'r2_10_endorsement'])
            ->whereRaw('LOWER(full_name) LIKE ? ESCAPE \'!\'', ['%'.$escapedName.'%'])
            ->orderBy('full_name')
            ->limit(6)
            ->get();

        if ($records->isEmpty()) {
            return "I couldn't find a record matching “{$name}”. Check the spelling or try a more complete name.";
        }

        $shown = $records->take(5)->map(function (Record $record) use ($canViewEndorsement): string {
            $details = [
                trim((string) $record->status) ?: 'status not recorded',
                trim((string) $record->purok) !== '' ? 'Purok '.trim((string) $record->purok) : 'Purok not recorded',
            ];
            if (trim((string) $record->kapisanan) !== '') {
                $details[] = trim((string) $record->kapisanan);
            }
            if ($canViewEndorsement) {
                $details[] = mb_strtoupper(trim((string) $record->r2_10_endorsement)) === 'ENDORSED'
                    ? 'R2-10 endorsed'
                    : 'R2-10 not endorsed';
            }

            return '• '.(trim((string) $record->full_name) ?: 'Unnamed record').' — '.implode(', ', $details);
        })->implode("\n");

        $prefix = $records->count() > 5
            ? "I found more than 5 matches for “{$name}”. Showing the first 5; please refine the name:\n"
            : 'Matching record(s):'."\n";

        return $prefix.$shown;
    }
}
