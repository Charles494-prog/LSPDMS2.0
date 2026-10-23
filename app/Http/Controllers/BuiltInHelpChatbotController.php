<?php

namespace App\Http\Controllers;

use App\Models\Record;
use App\Services\LspBotMatcher;
use App\Services\LspBotRecordQuery;
use App\Support\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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

    public function reply(Request $request, LspBotMatcher $matcher, LspBotRecordQuery $records): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:500'],
        ]);
        $user = $request->session()->get('login_user', []);
        $permissions = $this->permissions($user);
        $originalMessage = trim($validated['message']);
        $message = mb_strtolower($originalMessage);

        $clock = $this->clockReply($message);
        if ($clock !== null) {
            return response()->json(['reply' => $clock]);
        }

        $previous = $request->session()->get('lspbot_last_query');
        $query = $records->parse($originalMessage, is_array($previous) ? $previous : null);
        $name = $query === null ? $this->lookupName($originalMessage) : null;

        if ($query !== null || $name !== null) {
            if (! $permissions['chatbot_records_allow']) {
                return response()->json([
                    'reply' => 'You do not have permission to look up member records through this chatbot. Ask your Head Secretary to enable “Allow chatbot record lookups” for your account.',
                ]);
            }

            if ($query !== null) {
                if ($records->needsDelistmentAccess($query) && ! $permissions['Delistment_allow']) {
                    return response()->json([
                        'reply' => 'Your account cannot view R2-10 endorsement information. Ask your Head Secretary to enable De-Listment access.',
                    ]);
                }
                if ($records->listsMkNames($query) && ! $permissions['Delistment_allow']) {
                    return response()->json([
                        'reply' => 'MK member names are confidential. I can tell you how many there are, but listing names needs De-Listment access.',
                    ]);
                }
                // Remembered so a follow-up like “how about Purok 2?” keeps the other conditions.
                $request->session()->put('lspbot_last_query', $query);

                return response()->json(['reply' => $records->answer($query)]);
            }

            if ($name !== null) {
                return response()->json([
                    'reply' => $this->lookupReply($name, $permissions['Delistment_allow']),
                ]);
            }
        }

        $result = $matcher->match($originalMessage);
        $topic = $result['entry'];

        if ($topic === null) {
            // Unanswered questions are logged so you can see what to teach LSPBot next. The text itself is
            // not logged: questions can contain member names.
            Log::info('LSPBot could not match a question.', [
                'length' => mb_strlen($originalMessage),
                'score' => round($result['score'], 2),
                'closest_topics' => array_slice($result['suggestions'], 0, 3),
            ]);

            if ($result['suggestions'] !== []) {
                $options = implode("\n", array_map(fn (string $title): string => '• '.$title, $result['suggestions']));

                return response()->json([
                    'reply' => "I’m not completely sure what you meant. Did you mean one of these?\n{$options}\nTry asking again with a few more details about what you want to do.",
                ]);
            }

            return response()->json(['reply' => (string) config('lspbot.fallback')]);
        }

        $feature = $topic['feature'] ?? null;
        $answer = $matcher->answerFor($topic, $originalMessage);

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
        $isHeadSecretary = Role::isHeadSecretary($user['tungkulin'] ?? '');

        return [
            'head_secretary' => $isHeadSecretary,
            'dashboard_allow' => $isHeadSecretary || (bool) ($user['dashboard_allow'] ?? false),
            'Delistment_allow' => $isHeadSecretary || (bool) ($user['Delistment_allow'] ?? false),
            'Inquire_Allow' => $isHeadSecretary || (bool) ($user['Inquire_Allow'] ?? false),
            'form_generator_allow' => $isHeadSecretary || (bool) ($user['form_generator_allow'] ?? false),
            'filter_bnh_helper_allow' => $isHeadSecretary || (bool) ($user['filter_bnh_helper_allow'] ?? false),
            'tarheta_status_allow' => $isHeadSecretary || (bool) ($user['tarheta_status_allow'] ?? false),
            'chatbot_records_allow' => $isHeadSecretary || (bool) ($user['chatbot_records_allow'] ?? false),
            'note_checker_allow' => $isHeadSecretary || (bool) ($user['note_checker_allow'] ?? false),
        ];
    }

    /** Live answers for the date and time (Manila), which a static answer cannot give. */
    private function clockReply(string $message): ?string
    {
        $now = Carbon::now('Asia/Manila');
        if (preg_match('/\b(?:what time is it|what\'?s the time|current time|anong oras na|anong oras ngayon)\b/u', $message) === 1) {
            return 'It is '.$now->format('g:i A').' in the Philippines right now.';
        }
        if (preg_match('/\b(?:what\'?s the date|what is the date|today\'?s date|what day is it|what day is today|anong petsa|anong araw ngayon|anong araw na)\b/u', $message) === 1) {
            return 'Today is '.$now->format('l, F j, Y').'.';
        }

        return null;
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