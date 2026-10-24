<?php

namespace App\Services;

use App\Models\AssignedTask;
use App\Models\Message;
use App\Models\MonthlyMovementSession;
use App\Models\MonthlyRecordCodeHistory;
use App\Models\Purok;
use App\Models\SecretaryTodo;
use App\Models\User;
use App\Support\Role;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * LSPBot answers about the system itself rather than member records: your tasks and
 * to-dos, Puroks and their secretaries, the Monthly Session window, this month's
 * movement codes and the latest backup. Every answer only uses what the asking
 * account can already see on the matching page.
 *
 * answer() returns null when the question is not one of these.
 */
class LspBotSystemQuery
{
    /**
     * @param  array<string, bool>  $permissions  as built by BuiltInHelpChatbotController
     * @return array{reply: string, link?: array{label: string, url: string}, suggestions?: list<string>}|null
     */
    public function answer(string $message, array $sessionUser, array $permissions): ?array
    {
        $text = $this->normalize($message);
        $user = isset($sessionUser['id']) ? User::query()->find($sessionUser['id']) : null;

        return match (true) {
            $this->asksOverview($text) => $this->overview($user, $permissions),
            $this->asksUnread($text) => $this->unread($user),
            $this->asksTasks($text) => $this->tasks($text, $user, $permissions),
            $this->asksTodos($text) => $this->todos($user),
            $this->asksSessionStatus($text) => $this->monthlySession($permissions),
            $this->asksMovements($text) => $this->movements($text, $permissions),
            $this->asksPurokSecretary($text) !== null => $this->purokSecretary($this->asksPurokSecretary($text)),
            $this->asksPuroks($text) => $this->puroks(),
            $this->asksSecretaries($text) => $this->secretaries($text),
            $this->asksBackup($text) => $this->latestBackup($permissions),
            default => null,
        };
    }

    private function normalize(string $message): string
    {
        return LspBotLanguage::fixTypos(LspBotLanguage::normalize($message));
    }

    // ---------------------------------------------------------------- recognisers

    /** "What's new?", "any updates for me?", "ano ang bago?", "my summary", "what should I do today?". */
    private function asksOverview(string $text): bool
    {
        return preg_match('/^(?:(?:hi|hello|hey|lspbot|bot)\s+)?(?:what is new|anything new|any updates?|updates?|what did i miss|my summary|summary for me|my day|status ko|ano(?:ng)? (?:ang )?bago|may bago ba|anong balita|what should i do(?: today)?|what do i need to do(?: today)?|ano(?:ng)? gagawin ko(?: ngayon)?|catch me up|brief me|daily summary|overview)\b/u', $text) === 1;
    }

    /** "Do I have unread messages?", "may message ba ako?", "how many unread". */
    private function asksUnread(string $text): bool
    {
        return preg_match('/\b(?:unread|new messages?|unseen messages?|di pa nababasa|hindi pa nababasa)\b|\b(?:do i have|may|meron|any)\s+(?:(?:new|bagong)\s+)?(?:messages?|mensahe|chats?)\b(?:\s+(?:ba|ako|for me|po))*\s*\??$/u', $text) === 1
            && preg_match('/\b(?:how (?:do|can|to)|paano|where|saan|send|delete|why|bakit)\b/u', $text) !== 1;
    }

    private function asksTasks(string $text): bool
    {
        return preg_match('/\b(?:my tasks?|tasks? (?:assigned to me|for me|ko|ko po)|do i have (?:any )?(?:new |open |pending )?tasks?|what are my tasks|anong tasks?|ano(?:ng)? (?:mga )?tasks? ko|ilan(?:g)? (?:ang )?(?:mga )?tasks?|how many (?:open |pending |overdue )?tasks?|(?:pending|open|overdue|unfinished) tasks?|tasks? (?:due|overdue|waiting)|tasks? to confirm|waiting for (?:my )?confirmation|may tasks? ba)\b/u', $text) === 1
            && preg_match('/\b(?:how (?:do|can|to)|paano|where|saan|assign|create|post|mention)\b/u', $text) !== 1;
    }

    private function asksTodos(string $text): bool
    {
        return preg_match('/\b(?:my to-?dos?|my to-?do list|to-?dos? (?:ko|due|today|for today)|ano(?:ng)? (?:mga )?to-?dos? ko|mga gagawin ko|do i have (?:any )?to-?dos?|how many to-?dos?|ilan(?:g)? (?:ang )?to-?dos?|pending to-?dos?|open to-?dos?)\b/u', $text) === 1
            && preg_match('/\b(?:how (?:do|can|to)|paano|where|saan|add|create|delete|edit)\b/u', $text) !== 1;
    }

    private function asksSessionStatus(string $text): bool
    {
        return preg_match('/\b(?:monthly session|monthly sessions|buwanang session)\b/u', $text) === 1
            && preg_match('/\b(?:open|available|status|active|now|today|ngayon|bukas|current|which month|what month|anong buwan|para saan|for what month|is there|may (?:active|bukas)|started|ongoing|last|latest|huling)\b/u', $text) === 1
            && preg_match('/\b(?:how (?:do|can|to)|paano|why|bakit|disabled|undo)\b/u', $text) !== 1;
    }

    private function asksMovements(string $text): bool
    {
        return preg_match('/\b(?:movement codes?|movements?|deliberated|deliberations?|na-deliberate|monthly codes? (?:this|last)|codes? this month)\b/u', $text) === 1
            && preg_match('/\b(?:how many|ilan|ilang|count|total|summary|list|show|what|which|anong)\b/u', $text) === 1
            && preg_match('/\b(?:how (?:do|can|to)|paano|where|saan|why|bakit)\b/u', $text) !== 1;
    }

    /** The Purok number (and optional segment letter) in "who is the secretary of purok 2a?". */
    private function asksPurokSecretary(string $text): ?array
    {
        if (preg_match('/\b(?:who(?:\'s| is)?|sino(?: ang)?)\b.*\b(?:secretary|kalihim|manggagawa)\b.*\bpurok\s*(\d{1,3})\s*([a-f])?\b|\b(?:secretary|kalihim|manggagawa) (?:of|ng|sa) purok\s*(\d{1,3})\s*([a-f])?\b/u', $text, $m) !== 1) {
            return null;
        }
        $number = $m[1] !== '' ? $m[1] : $m[3];
        $segment = $m[1] !== '' ? ($m[2] ?? '') : ($m[4] ?? '');

        return ['number' => (int) $number, 'segment' => $segment];
    }

    private function asksPuroks(string $text): bool
    {
        return preg_match('/\b(?:how many puroks?|ilan(?:g)? (?:ang )?purok|list (?:of |all |the )?puroks|what (?:are the )?puroks|which puroks|anong mga purok|(?:what|which) grupos? (?:are )?(?:in|under|belong)|grupo range|sakop na grupo)\b/u', $text) === 1
            && preg_match('/\b(?:ms|uwp|mk|members?|records?|kapatid|male|female|buklod|kadiwa|binhi)\b/u', $text) !== 1;
    }

    private function asksSecretaries(string $text): bool
    {
        return preg_match('/\b(?:how many secretaries|ilan(?:g)? (?:ang )?(?:mga )?secretar(?:y|ies)|who are the secretaries|list (?:of |the |all )?secretaries|sino(?:-sino)? ang (?:mga )?secretar(?:y|ies)|who is the head secretary|who\'s the head secretary|sino ang head secretary|sino ang head)\b/u', $text) === 1;
    }

    private function asksBackup(string $text): bool
    {
        return preg_match('/\b(?:last|latest|recent|newest|huling) (?:database )?backups?\b|\bwhen (?:was|is) the (?:last|latest|next) backup\b|\bhow many backups\b|\bilan(?:g)? (?:ang )?backups?\b/u', $text) === 1;
    }

    // ---------------------------------------------------------------- answers

    private function unreadCount(User $user): int
    {
        // Same count as the chat button badge (MessageController::unreadCount).
        return Message::query()
            ->where('sender_id', '!=', $user->id)
            ->whereNull('read_at')
            ->where('expires_at', '>', now())
            ->whereHas('conversation', fn ($query) => $query->where('user_one_id', $user->id)->orWhere('user_two_id', $user->id))
            ->count();
    }

    private function unread(?User $user): array
    {
        if ($user === null) {
            return ['reply' => 'I could not find your account. Please sign in again.'];
        }
        $count = $this->unreadCount($user);

        return [
            'reply' => $count === 0
                ? 'You have no unread messages. 📭'
                : "You have {$count} unread message".($count === 1 ? '' : 's').'. Open the chat button at the bottom-right to read '.($count === 1 ? 'it' : 'them').'.',
            'link' => ['label' => 'Open Messages', 'url' => route('messages.index')],
        ];
    }

    /** One short "what needs me" summary: messages, tasks, to-dos and the Monthly Session. */
    private function overview(?User $user, array $permissions): array
    {
        if ($user === null) {
            return ['reply' => 'I could not find your account. Please sign in again.'];
        }
        $now = Carbon::now('Asia/Manila');
        $lines = [];

        $unread = $this->unreadCount($user);
        $lines[] = $unread === 0 ? '• No unread messages' : "• {$unread} unread message".($unread === 1 ? '' : 's');

        $isHead = $permissions['head_secretary'] ?? false;
        if ($isHead || Role::isSecretary($user->tungkulin)) {
            $soon = $now->copy()->startOfDay()->addDays(2)->toDateString();
            if ($isHead) {
                $waiting = AssignedTask::query()->whereNull('confirmed_at')->whereNotNull('done_at')->count();
                $open = AssignedTask::query()->whereNull('confirmed_at')->whereNull('done_at')->count();
                $lines[] = "• {$open} open task".($open === 1 ? '' : 's').", {$waiting} waiting for your confirmation";
            } else {
                $memberDone = Schema::hasColumn('assigned_task_members', 'done_at');
                $mine = AssignedTask::query()
                    ->whereNull('confirmed_at')->whereNull('done_at')
                    ->whereHas('members', fn ($members) => $members->where('user_id', $user->id)->where('can_complete', true)
                        ->when($memberDone, fn ($q) => $q->whereNull('done_at')));
                $total = (clone $mine)->count();
                $dueSoon = (clone $mine)->whereNotNull('due_date')->whereDate('due_date', '<=', $soon)->count();
                $lines[] = "• {$total} task".($total === 1 ? '' : 's').' waiting for your “Mark as done”'.($dueSoon > 0 ? " ({$dueSoon} due within 2 days)" : '');
            }
        }

        $todos = SecretaryTodo::query()->where('user_id', $user->id)->whereNull('completed_at');
        $todoCount = (clone $todos)->count();
        $todoDue = (clone $todos)->whereNotNull('deadline')->whereDate('deadline', '<=', $now->toDateString())->count();
        $lines[] = "• {$todoCount} unfinished to-do".($todoCount === 1 ? '' : 's').($todoDue > 0 ? " ({$todoDue} due today or overdue)" : '');

        if ($permissions['form_generator_allow'] ?? false) {
            $active = MonthlyMovementSession::query()->where('active_key', 'active')->latest('id')->first();
            $lines[] = $active !== null
                ? '• Monthly Session for '.($active->period_month?->format('F Y') ?? 'this period').' is active'
                : '• No Monthly Session is active';
        }

        return [
            'reply' => 'Here is your summary for '.$now->format('l, F j').":\n".implode("\n", $lines),
            'suggestions' => ['What are my tasks?', 'My to-dos', 'Is the monthly session open?'],
        ];
    }

    private function tasks(string $text, ?User $user, array $permissions): array
    {
        $isHead = $permissions['head_secretary'] ?? false;
        if ($user === null || (! $isHead && ! Role::isSecretary($user->tungkulin))) {
            return ['reply' => 'Tasks are for Secretaries and the Head Secretary. Your account does not have a Tasks list.'];
        }
        $link = ['label' => 'Open Tasks', 'url' => route('tasks')];
        $today = Carbon::now('Asia/Manila')->startOfDay();
        $memberDone = Schema::hasColumn('assigned_task_members', 'done_at');

        // Same visibility rule as the Tasks page: everything for the Head; assigned or shared tasks for a secretary.
        $tasks = AssignedTask::query()
            ->whereNull('confirmed_at')
            ->unless($isHead, fn ($query) => $query->where(fn ($visible) => $visible
                ->where('visibility', 'everyone')
                ->orWhereHas('members', fn ($members) => $members->where('user_id', $user->id))))
            ->with(['members' => fn ($members) => $members->where('user_id', $user->id)])
            ->orderByRaw('due_date IS NULL')
            ->orderBy('due_date')
            ->orderByDesc('id')
            ->limit(50)
            ->get(['id', 'title', 'due_date', 'done_at']);

        if ($isHead) {
            $waiting = $tasks->filter(fn (AssignedTask $task): bool => $task->done_at !== null);
            $open = $tasks->filter(fn (AssignedTask $task): bool => $task->done_at === null);
            if ($tasks->isEmpty()) {
                return ['reply' => 'There are no open tasks right now. Post one from Tasks → New task.', 'link' => $link];
            }
            $reply = "There are {$open->count()} open task".($open->count() === 1 ? '' : 's')
                ." and {$waiting->count()} waiting for your confirmation.";
            if ($waiting->isNotEmpty()) {
                $reply .= "\nWaiting for confirmation:\n".$this->taskLines($waiting, $today);
            }
            if ($open->isNotEmpty()) {
                $reply .= "\nOpen:\n".$this->taskLines($open, $today);
            }

            return ['reply' => $reply, 'link' => $link];
        }

        // For a secretary: what still needs *their* Mark as done.
        $mine = $tasks->filter(function (AssignedTask $task) use ($memberDone): bool {
            $membership = $task->members->first();
            if ($membership === null || ! $membership->can_complete || $task->done_at !== null) {
                return false;
            }

            return ! $memberDone || $membership->done_at === null;
        });
        $overdue = $mine->filter(fn (AssignedTask $task): bool => $task->due_date !== null && $task->due_date->lt($today));
        if ($tasks->isEmpty()) {
            return ['reply' => 'You have no open tasks right now. 🎉', 'link' => $link];
        }
        if (preg_match('/\boverdue\b/u', $text) === 1) {
            return [
                'reply' => $overdue->isEmpty() ? 'None of your tasks are overdue.' : 'Overdue tasks:'."\n".$this->taskLines($overdue, $today),
                'link' => $link,
            ];
        }
        $reply = "You can see {$tasks->count()} open task".($tasks->count() === 1 ? '' : 's')
            .'; '.$mine->count().' still need'.($mine->count() === 1 ? 's' : '').' your “Mark as done”'
            .($overdue->isNotEmpty() ? " ({$overdue->count()} overdue)" : '').'.';
        if ($mine->isNotEmpty()) {
            $reply .= "\n".$this->taskLines($mine, $today);
        }

        return ['reply' => $reply, 'link' => $link];
    }

    private function taskLines(Collection $tasks, Carbon $today): string
    {
        $lines = $tasks->take(5)->map(function (AssignedTask $task) use ($today): string {
            $due = '';
            if ($task->due_date !== null) {
                $days = (int) $today->diffInDays(Carbon::parse($task->due_date->format('Y-m-d'), 'Asia/Manila'), false);
                $due = match (true) {
                    $days < 0 => ' — overdue by '.abs($days).' day'.(abs($days) === 1 ? '' : 's'),
                    $days === 0 => ' — due today',
                    $days === 1 => ' — due tomorrow',
                    default => ' — due '.$task->due_date->format('M j'),
                };
            }

            return '• '.mb_strimwidth((string) $task->title, 0, 80, '…').$due;
        })->implode("\n");

        return $lines.($tasks->count() > 5 ? "\n…and ".($tasks->count() - 5).' more.' : '');
    }

    private function todos(?User $user): array
    {
        $link = ['label' => 'Open your To-Do list', 'url' => route('messages.index')];
        if ($user === null) {
            return ['reply' => 'I could not find your account. Please sign in again.'];
        }
        $today = Carbon::now('Asia/Manila')->toDateString();
        $todos = SecretaryTodo::query()
            ->where('user_id', $user->id)
            ->whereNull('completed_at')
            ->orderByRaw('deadline IS NULL')
            ->orderBy('deadline')
            ->limit(50)
            ->get(['title', 'deadline']);
        if ($todos->isEmpty()) {
            return ['reply' => 'Your To-Do list is empty. Add one from the chat box → To-Do tab (only you can see it).', 'link' => $link];
        }
        $overdue = $todos->filter(fn (SecretaryTodo $todo): bool => $todo->deadline !== null && $todo->deadline->toDateString() < $today)->count();
        $dueToday = $todos->filter(fn (SecretaryTodo $todo): bool => $todo->deadline?->toDateString() === $today)->count();
        $lines = $todos->take(5)->map(fn (SecretaryTodo $todo): string => '• '.mb_strimwidth(strtok((string) $todo->title, "\n") ?: '', 0, 80, '…')
            .($todo->deadline !== null ? ' — '.($todo->deadline->toDateString() < $today ? 'overdue, ' : '').'due '.$todo->deadline->format('M j') : ''))->implode("\n");

        return [
            'reply' => "You have {$todos->count()} unfinished to-do".($todos->count() === 1 ? '' : 's')
                .($dueToday > 0 ? ", {$dueToday} due today" : '').($overdue > 0 ? ", {$overdue} overdue" : '').":\n".$lines
                .($todos->count() > 5 ? "\n…and ".($todos->count() - 5).' more.' : ''),
            'link' => $link,
        ];
    }

    private function monthlySession(array $permissions): array
    {
        $now = Carbon::now('Asia/Manila');
        // Same rule as DeliberationController: from the 15th the session is for next month.
        $target = $now->copy()->startOfMonth();
        if ($now->day >= 15) {
            $target->addMonthNoOverflow();
        }
        $schedule = "Right now (".$now->format('F j').") a Monthly Session would be for {$target->format('F Y')}. From the 15th of each month the session is for the following month, and only one session is allowed per month.";
        if (! ($permissions['form_generator_allow'] ?? false)) {
            return ['reply' => $schedule.' Your account cannot open Monthly Session; ask your Head Secretary for Form Generator access if you need it.'];
        }

        $existing = MonthlyMovementSession::query()->whereDate('period_month', $target->toDateString())->orderBy('id')->first();
        $active = MonthlyMovementSession::query()->where('active_key', 'active')->latest('id')->first();
        $last = MonthlyMovementSession::query()->where('status', 'completed')->orderByDesc('completed_at')->first();

        $state = match (true) {
            $active !== null => 'A session for '.($active->period_month?->format('F Y') ?? 'this period').' is active now (started by '.$this->who($active->started_by).').',
            $existing !== null => "The {$target->format('F Y')} session already exists, so no new session can be started until the next period.",
            default => "No session exists yet for {$target->format('F Y')}, so you can start one now.",
        };
        $lastText = $last !== null ? ' The last completed session was for '.($last->period_month?->format('F Y') ?? 'an earlier month').'.' : '';

        return [
            'reply' => $schedule."\n".$state.$lastText,
            'link' => ['label' => 'Open Monthly Session', 'url' => route('forms.monthly.session')],
        ];
    }

    private function who(?string $actor): string
    {
        $actor = trim((string) $actor);
        if ($actor === '') {
            return 'someone';
        }
        $user = str_contains($actor, '@') ? User::query()->where('gmail', $actor)->first(['first_name', 'last_name']) : null;

        return $user !== null ? trim($user->first_name.' '.$user->last_name) : $actor;
    }

    private function movements(string $text, array $permissions): array
    {
        if (! ($permissions['chatbot_records_allow'] ?? false)) {
            return ['reply' => 'You do not have permission to look up member records through this chatbot. Ask your Head Secretary to enable “Allow chatbot record lookups” for your account.'];
        }
        if (! ($permissions['form_generator_allow'] ?? false) && ! ($permissions['dashboard_allow'] ?? false)) {
            return ['reply' => 'Monthly movement codes are shown on the Dashboard and in Form Generator. Your account cannot open either, so ask your Head Secretary for access.'];
        }

        $now = Carbon::now('Asia/Manila');
        [$from, $to, $label] = match (true) {
            preg_match('/\blast month\b|\bnakaraang buwan\b/u', $text) === 1 => [$now->copy()->startOfMonth()->subMonthNoOverflow(), $now->copy()->startOfMonth()->subMonthNoOverflow()->endOfMonth(), null],
            preg_match('/\b(?:this year|ngayong taon)\b/u', $text) === 1 => [$now->copy()->startOfYear(), $now->copy()->endOfYear(), 'in '.$now->year],
            default => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth(), null],
        };
        if (preg_match('/\b(january|february|march|april|may|june|july|august|september|october|november|december)\b(?:\s+(\d{4}))?/u', $text, $m) === 1 && ($m[1] !== 'may' || isset($m[2]))) {
            $from = Carbon::parse('1 '.$m[1].' '.($m[2] ?? $now->year), 'Asia/Manila');
            $to = $from->copy()->endOfMonth();
        }
        $label ??= 'in '.$from->format('F Y');

        $rows = MonthlyRecordCodeHistory::query()
            ->whereDate('deliberated_at', '>=', $from->toDateString())
            ->whereDate('deliberated_at', '<=', $to->toDateString())
            ->selectRaw('monthly_code, COUNT(*) AS aggregate')
            ->groupBy('monthly_code')
            ->orderBy('monthly_code')
            ->get();
        $total = (int) $rows->sum('aggregate');
        $link = ($permissions['dashboard_allow'] ?? false) ? ['label' => 'Open Dashboard', 'url' => route('dashboard')] : ['label' => 'Open Monthly Session', 'url' => route('forms.monthly.session')];
        if ($total === 0) {
            return ['reply' => "No movements were deliberated {$label}.", 'link' => $link];
        }

        return [
            'reply' => "{$total} movement".($total === 1 ? ' was' : 's were')." deliberated {$label}:\n"
                .$rows->map(fn ($row): string => '• '.(trim((string) $row->monthly_code) ?: 'No code').': '.$row->aggregate)->implode("\n"),
            'link' => $link,
        ];
    }

    private function puroks(): array
    {
        $puroks = Purok::query()->orderBy('purok_number')->orderBy('grupo_start')->orderBy('id')
            ->get(['purok_number', 'purok_name', 'grupo_start', 'grupo_end', 'full_purok']);
        if ($puroks->isEmpty()) {
            return ['reply' => 'No Puroks are set up yet. The Head Secretary adds them in Superadmin Dashboard → Purok signatories.'];
        }
        $numbers = $puroks->pluck('purok_number')->unique()->count();
        $lines = $puroks->map(fn (Purok $purok): string => '• '.($purok->purok_name ?: 'Purok '.$purok->purok_number)
            .': Grupo '.($purok->full_purok ? '1–'.$purok->grupo_end.' (whole Purok)' : $purok->grupo_start.'–'.$purok->grupo_end))->implode("\n");

        return [
            'reply' => "There are {$numbers} Puroks".($puroks->count() > $numbers ? " ({$puroks->count()} segments, because some Puroks are split)" : '').":\n".$lines,
        ];
    }

    private function purokSecretary(array $wanted): array
    {
        $segments = Purok::query()->where('purok_number', $wanted['number'])->orderBy('grupo_start')
            ->get(['purok_name', 'secretary', 'manggagawa']);
        if ($wanted['segment'] !== '') {
            $segments = $segments->filter(fn (Purok $purok): bool => str_ends_with(mb_strtolower(preg_replace('/\s+/', '', (string) $purok->purok_name) ?? ''), $wanted['number'].$wanted['segment']));
        }
        if ($segments->isEmpty()) {
            return ['reply' => "I couldn't find Purok {$wanted['number']}".mb_strtoupper($wanted['segment']).' in the Purok settings.'];
        }
        $names = User::query()->whereIn('id', $segments->pluck('secretary')->merge($segments->pluck('manggagawa'))->filter()->all())
            ->get(['id', 'first_name', 'last_name'])->keyBy('id');
        $name = fn ($id): string => $id && isset($names[$id]) ? trim($names[$id]->first_name.' '.$names[$id]->last_name) : 'not assigned';
        $lines = $segments->map(fn (Purok $purok): string => '• '.($purok->purok_name ?: 'Purok '.$wanted['number'])
            .' — Secretary: '.$name($purok->secretary).'; Manggagawa: '.$name($purok->manggagawa))->implode("\n");

        return ['reply' => $lines];
    }

    private function secretaries(string $text): array
    {
        $users = User::query()->where('is_verified', true)->orderBy('first_name')->orderBy('last_name')
            ->get(['first_name', 'last_name', 'tungkulin']);
        $heads = $users->filter(fn (User $user): bool => Role::isHeadSecretary($user->tungkulin));
        $secretaries = $users->filter(fn (User $user): bool => Role::isSecretary($user->tungkulin));
        $full = fn (User $user): string => trim($user->first_name.' '.$user->last_name) ?: 'Unnamed account';

        if (preg_match('/\bhead\b/u', $text) === 1) {
            return ['reply' => $heads->isEmpty()
                ? 'No Head Secretary is set right now.'
                : 'The Head Secretary is '.$heads->map($full)->implode(' and ').'.'];
        }

        return ['reply' => 'There '.($secretaries->count() === 1 ? 'is 1 Secretary' : "are {$secretaries->count()} Secretaries")
            .($heads->isNotEmpty() ? ' plus the Head Secretary ('.$heads->map($full)->implode(', ').')' : '').'.'
            .($secretaries->isNotEmpty() ? "\n".$secretaries->take(20)->map(fn (User $user): string => '• '.$full($user))->implode("\n") : '')];
    }

    private function latestBackup(array $permissions): array
    {
        if (! ($permissions['head_secretary'] ?? false)) {
            return ['reply' => 'Database backups are managed by the Head Secretary in Superadmin Dashboard → Database backups. A backup is made automatically every month and right before every database import.'];
        }
        $directory = app(DatabaseBackupService::class)->backupDirectory();
        $files = collect(glob($directory.DIRECTORY_SEPARATOR.'*.sql') ?: [])
            ->sortByDesc(fn (string $path): int => (int) @filemtime($path))
            ->values();
        $link = ['label' => 'Open Superadmin Dashboard', 'url' => route('superadmin-dashboard')];
        if ($files->isEmpty()) {
            return ['reply' => 'There are no saved backups yet. Add one in Superadmin Dashboard → Database backups (the monthly automatic backup also lands there).', 'link' => $link];
        }
        $latest = $files->first();
        $when = Carbon::createFromTimestamp((int) @filemtime($latest))->timezone('Asia/Manila');

        return [
            'reply' => "There are {$files->count()} saved backup".($files->count() === 1 ? '' : 's').'. The newest is '.basename($latest)
                .' from '.$when->format('F j, Y g:i A').' ('.$when->diffForHumans().').',
            'link' => $link,
        ];
    }
}
