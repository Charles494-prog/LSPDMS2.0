<?php

namespace App\Http\Controllers;

use App\Models\Record;
use App\Services\LspBotLanguage;
use App\Services\LspBotMatcher;
use App\Services\LspBotRecordQuery;
use App\Services\LspBotSystemQuery;
use App\Support\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class BuiltInHelpChatbotController extends Controller
{
    // What LSPBot knows (questions, answers, links, permissions) lives in config/lspbot.php and
    // App\Services\LspBotKnowledge. A question is tried, in order, as: the clock, a question about
    // the system itself (tasks, messages, Monthly Session…), a record question, a name lookup, and
    // finally the knowledge base.

    public function index(Request $request): mixed
    {
        $user = $request->session()->get('login_user', []);

        return view('messages.assistant', [
            'authenticatedUser' => $user,
        ]);
    }

    public function reply(Request $request, LspBotMatcher $matcher, LspBotRecordQuery $records, LspBotSystemQuery $system): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:500'],
            'fresh' => ['sometimes', 'boolean'],
        ]);
        // The chat panel starts over each time it is opened, so its first message forgets the last question.
        if ($request->boolean('fresh')) {
            $request->session()->forget('lspbot_last_query');
        }
        $user = $request->session()->get('login_user', []);
        $permissions = $this->permissions($user);
        $originalMessage = trim($validated['message']);

        // "How many MS in purok 1? How about UWP?" is answered part by part, each part able to follow up the one before.
        $parts = LspBotLanguage::splitQuestions($originalMessage);
        if (count($parts) === 1) {
            return response()->json($this->answer($originalMessage, $request, $user, $permissions, $matcher, $records, $system));
        }

        $answers = [];
        $link = null;
        $suggestions = [];
        foreach ($parts as $part) {
            $answer = $this->answer($part, $request, $user, $permissions, $matcher, $records, $system);
            $answers[] = '“'.trim($part).'”'."\n".$answer['reply'];
            $link = $answer['link'] ?? $link;
            $suggestions = $answer['suggestions'] ?? $suggestions;
        }
        $response = ['reply' => implode("\n\n", $answers)];
        if ($link !== null) {
            $response['link'] = $link;
        }
        if ($suggestions !== []) {
            $response['suggestions'] = $suggestions;
        }

        return response()->json($response);
    }

    /** @return array{reply: string, link?: array{label: string, url: string}, suggestions?: list<string|array{label: string, message: string}>} */
    private function answer(string $originalMessage, Request $request, array $user, array $permissions, LspBotMatcher $matcher, LspBotRecordQuery $records, LspBotSystemQuery $system): array
    {
        $message = LspBotLanguage::normalize($originalMessage);

        $clock = $this->clockReply($message);
        if ($clock !== null) {
            return ['reply' => $clock];
        }

        // Live facts about the system: your tasks, to-dos, unread messages, the Monthly Session, Puroks.
        $systemAnswer = $system->answer($originalMessage, $user, $permissions);
        if ($systemAnswer !== null) {
            return $systemAnswer;
        }

        $previous = $request->session()->get('lspbot_last_query');
        $query = $records->parse($originalMessage, is_array($previous) ? $previous : null, ['user_id' => $user['id'] ?? null]);
        $name = $query === null ? $this->lookupName($originalMessage) : null;

        if ($query !== null || $name !== null) {
            if (! $permissions['chatbot_records_allow']) {
                return [
                    'reply' => 'You do not have permission to look up member records through this chatbot. Ask your Head Secretary to enable “Allow chatbot record lookups” for your account.',
                ];
            }

            if ($query !== null) {
                foreach ($records->requiredPermissions($query) as $permission => $reason) {
                    if (! ($permissions[$permission] ?? false)) {
                        return ['reply' => $reason];
                    }
                }
                if ($records->listsMkNames($query) && ! $permissions['Delistment_allow']) {
                    // "List the members" still works without MK names; "list the MK" cannot.
                    $withoutMk = $records->withoutMkNames($query);
                    if ($withoutMk === null) {
                        return ['reply' => 'MK member names are confidential. I can tell you how many there are, but listing names needs De-Listment access.'];
                    }
                    $query = $withoutMk;
                }
                // Remembered so a follow-up like “how about Purok 2?” keeps the other conditions.
                $request->session()->put('lspbot_last_query', $query);

                $response = ['reply' => $records->answer($query)];
                $suggestions = $records->suggestions($query);
                if ($suggestions !== []) {
                    $response['suggestions'] = $suggestions;
                }
                if (in_array($query['intent'], ['list', 'extreme'], true) && $permissions['Inquire_Allow']) {
                    $response['link'] = ['label' => 'Open Inquire / Modify', 'url' => route('inquire-modify')];
                }

                return $response;
            }

            return ['reply' => $this->lookupReply($name, $permissions['Delistment_allow'])];
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

                return [
                    'reply' => "I’m not completely sure what you meant. Did you mean one of these?\n{$options}\nTap one, or try asking again with a few more details about what you want to do.",
                    'suggestions' => $result['options'] ?? $result['suggestions'],
                ];
            }

            return [
                'reply' => (string) config('lspbot.fallback'),
                'suggestions' => ['What can you do?', 'Give me examples', 'What is new?'],
            ];
        }

        $feature = $topic['feature'] ?? null;
        $answer = $matcher->answerFor($topic, $originalMessage);

        if ($feature !== null && ! ($permissions[$feature] ?? false)) {
            $title = $topic['title'] ?? 'this feature';

            return [
                'reply' => "You do not currently have permission to open {$title}. Ask your Head Secretary to enable the required access. I have not provided a link because your account cannot access this feature.",
            ];
        }

        if (($topic['key'] ?? '') === 'greeting') {
            // Greets by the time of day in Manila, in the language the person used.
            $hour = Carbon::now('Asia/Manila')->hour;
            $part = $hour < 12 ? 0 : ($hour < 18 ? 1 : 2);
            $answer = (LspBotLanguage::isTagalog($originalMessage) ? ['Magandang umaga!', 'Magandang hapon!', 'Magandang gabi!'][$part] : ['Good morning!', 'Good afternoon!', 'Good evening!'][$part])
                .' '.(preg_replace('/^Hello!\s*/u', '', $answer) ?? $answer);
        }
        $response = ['reply' => $answer];
        if (! empty($topic['route'])) {
            $response['link'] = [
                'label' => $topic['link_label'] ?? 'Open '.($topic['title'] ?? 'this page'),
                'url' => route($topic['route']),
            ];
        }
        if (in_array($topic['key'] ?? '', ['capabilities', 'help_menu', 'data_questions_help', 'greeting'], true)) {
            $response['suggestions'] = ['How many MS are there?', 'What is new?', 'How do I assign a task?'];
        }

        return $response;
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
        if (preg_match('/\b(?:what time is it|what is the time|current time|time now|anong oras na|anong oras ngayon|oras na ba)\b/u', $message) === 1) {
            return 'It is '.$now->format('g:i A').' in the Philippines right now.';
        }
        if (preg_match('/\b(?:what is the date tomorrow|what day is tomorrow|date tomorrow|anong petsa bukas|anong araw bukas)\b/u', $message) === 1) {
            return 'Tomorrow is '.$now->copy()->addDay()->format('l, F j, Y').'.';
        }
        if (preg_match('/\b(?:what is the date|today\'?s date|what day is it|what day is today|date today|anong petsa|anong araw ngayon|anong araw na)\b/u', $message) === 1) {
            return 'Today is '.$now->format('l, F j, Y').'.';
        }
        if (preg_match('/\b(?:what month is it|what month is this|anong buwan na|anong buwan ngayon)\b/u', $message) === 1) {
            return 'It is '.$now->format('F Y').'.';
        }
        if (preg_match('/\b(?:what year is it|anong taon na|anong taon ngayon)\b/u', $message) === 1) {
            return 'It is '.$now->year.'.';
        }

        return null;
    }

    private function lookupName(string $message): ?string
    {
        if (preg_match('/\b(?:edit|modify|change|update|correct|delete|remove|delist)\b/i', $message) === 1) {
            return null;
        }

        $patterns = [
            '/^\s*(?:please\s+)?(?:find|search|look\s*up|lookup|hanapin|hanap)\s+(?:for\s+|si\s+|ang\s+)?(.+?)(?:\s+(?:in|from|sa)\s+(?:(?:our|the)\s+)?(?:records?|database))?[?.!]*\s*$/iu',
            '/^\s*(?:is|do we have)\s+(.+?)\s+(?:in|on)\s+(?:(?:our|the)\s+)?(?:records?|database)[?.!]*\s*$/iu',
            '/^\s*(?:nasa|may)\s+(?:records?|database)\s+(?:ba\s+)?(?:si|ang)\s+(.+?)[?.!]*\s*$/iu',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $message, $matches) === 1) {
                $name = trim($matches[1], " \t\n\r\0\x0B?.!,");
                if (mb_strlen($name) >= 2 && preg_match('/\b(?:member|record|records|database|page|feature|form|button|task|message)\b/i', $name) !== 1) {
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
            // MK names are confidential: only accounts with De-Listment access see MK records by name.
            ->when(! $canViewEndorsement, fn ($query) => $query->where(fn ($q) => $q->whereNull('status')->orWhereRaw("UPPER(TRIM(status)) <> 'MK'")))
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
