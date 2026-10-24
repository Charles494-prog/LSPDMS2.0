<?php

namespace App\Http\Controllers;

use App\Events\TaskFeedUpdated;
use App\Models\AssignedTask;
use App\Models\AssignedTaskComment;
use App\Models\AssignedTaskMember;
use App\Models\AssignedTaskMention;
use App\Models\AssignedTaskReaction;
use App\Models\User;
use App\Support\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Tasks the Head Secretary assigns to secretaries.
 *
 * - Only the Head Secretary creates, edits, deletes, reopens and confirms tasks, and decides
 *   which secretaries see each task and whether each may comment and/or mark it done.
 * - A secretary sees the tasks they are assigned to, plus tasks the Head shared with everyone
 *   (others can only view those, or also comment when the Head allows it).
 * - "Done" by a secretary waits for the Head Secretary's confirmation; confirming closes the task
 *   and it leaves the feed.
 * - A task is "new" for a secretary until they open the Tasks page; for the Head Secretary, a task
 *   a secretary has marked done is new until the Head sees it. New tasks drive the pop-up reminder.
 * - Facebook-style: one level of replies under a comment, and reactions on tasks and comments
 *   (anyone who can see a task can react; replying needs comment permission).
 * - Due dates cannot be set in the past (Manila date). Open tasks due within two days drive a
 *   "due soon" reminder for the people who must finish them.
 * - Every change is pushed live (Reverb) to the people who can see the task.
 * - @mentions (e.g. @KimberlyOstil) in a task or comment notify that person, if they can see the task,
 *   until they open the Tasks page.
 */
class AssignedTaskController extends Controller
{
    public function page(Request $request)
    {
        $user = $this->currentUser($request);

        return view('tasks', ['tasksIsHead' => Role::isHeadSecretary($user->tungkulin)]);
    }

    public function index(Request $request): JsonResponse
    {
        $user = $this->currentUser($request);
        $isHead = Role::isHeadSecretary($user->tungkulin);
        // Taken before the query: the Tasks page sends it back to mark exactly this list as seen.
        $asOf = now();

        $tasks = $this->visibleTasks($user, $isHead)
            ->with(self::RELATIONS)
            ->orderByRaw('done_at IS NULL')   // awaiting confirmation first
            ->orderByRaw('due_date IS NULL')
            ->orderBy('due_date')
            ->orderByDesc('created_at')
            ->get();

        $reads = $this->readsFor($user, $tasks->pluck('id'));
        $mentionedIn = $this->mentionsReady()
            ? AssignedTaskMention::query()->where('user_id', $user->id)->whereNull('seen_at')->pluck('task_id')->map(fn ($id): int => (int) $id)->all()
            : [];
        $payload = $tasks->map(fn (AssignedTask $task): array => $this->payload($task, $user, $isHead) + [
            'is_new' => $this->isNew($task, $isHead, $reads->get($task->id)),
            'mentions_me' => in_array((int) $task->id, $mentionedIn, true),
        ]);

        return response()->json([
            'is_head' => $isHead,
            'as_of' => $asOf->toIso8601String(),
            'completed_count' => $this->completedQuery($user, $isHead)->where('confirmed_at', '>=', now()->subDays(30))->count(),
            // What needs this person's action: confirmations for the Head, open work for a secretary.
            'attention' => $payload->filter(fn (array $task): bool => $isHead
                ? $task['status'] === 'awaiting_confirmation'
                : $task['status'] === 'open' && $task['viewer']['can_complete'])->count(),
            'tasks' => $payload->values(),
        ]);
    }

    /**
     * The Tasks page calls this (POST, so it is CSRF-protected) after showing the list: the given
     * tasks count as seen as of the moment that list was loaded. Only tasks this person can see are marked.
     */
    public function markSeenRequest(Request $request): JsonResponse
    {
        $user = $this->currentUser($request);
        $isHead = Role::isHeadSecretary($user->tungkulin);
        $validated = $request->validate([
            'ids' => ['present', 'array', 'max:500'],
            'ids.*' => ['integer'],
            'as_of' => ['required', 'date'],
        ]);
        // Never later than now, so a client cannot mark future events as already seen.
        $asOf = Carbon::parse($validated['as_of'])->min(now());
        $ids = $this->visibleTasks($user, $isHead)->whereIn('id', $validated['ids'])->pluck('id');
        $this->markSeen($user, $ids, $asOf);
        if ($ids->isNotEmpty() && $this->mentionsReady()) {
            AssignedTaskMention::query()->where('user_id', $user->id)->whereIn('task_id', $ids)
                ->whereNull('seen_at')->where('created_at', '<=', $asOf)->update(['seen_at' => now()]);
        }

        return response()->json(['seen' => $ids->count()]);
    }

    /** Closed (confirmed) tasks of the last 60 days that this person could see, newest first; read-only. */
    public function completed(Request $request): JsonResponse
    {
        $user = $this->currentUser($request);
        $isHead = Role::isHeadSecretary($user->tungkulin);
        $tasks = $this->completedQuery($user, $isHead)
            ->where('confirmed_at', '>=', now()->subDays(60))
            ->with(['author', 'doneBy', 'confirmedBy', 'members.user'])
            ->orderByDesc('confirmed_at')
            ->limit(50)
            ->get();

        return response()->json($tasks->map(fn (AssignedTask $task): array => [
            'id' => $task->id,
            'title' => $task->title,
            'body' => $task->body,
            'due_date' => $task->due_date?->format('Y-m-d'),
            'created_at' => $task->created_at?->toIso8601String(),
            'done_at' => $task->done_at?->toIso8601String(),
            'confirmed_at' => $task->confirmed_at?->toIso8601String(),
            'author' => $task->author ? $this->person($task->author) : null,
            'done_by' => $task->doneBy ? $this->person($task->doneBy) : null,
            'confirmed_by' => $task->confirmedBy ? $this->person($task->confirmedBy) : null,
            'members' => $task->members->filter(fn (AssignedTaskMember $member) => $member->user)->map(fn (AssignedTaskMember $member): array => $this->person($member->user))->values(),
            // Everyone who marked their part done, in the order they finished (credit goes to all of them).
            'finished_by' => $this->memberDoneReady()
                ? $task->members->filter(fn (AssignedTaskMember $member) => $member->user && $member->done_at)
                    ->sortBy('done_at')->map(fn (AssignedTaskMember $member): array => $this->person($member->user))->values()
                : [],
            'on_time' => $task->due_date === null || $task->done_at === null
                || $task->done_at->copy()->timezone('Asia/Manila')->toDateString() <= $task->due_date->format('Y-m-d'),
        ])->values());
    }

    /** The latest comments and events (done, reopened, confirmed) on open tasks this person can see. */
    public function activity(Request $request): JsonResponse
    {
        $user = $this->currentUser($request);
        $isHead = Role::isHeadSecretary($user->tungkulin);
        $taskIds = $this->visibleTasks($user, $isHead)->pluck('id');
        $items = AssignedTaskComment::query()
            ->whereIn('task_id', $taskIds)
            ->with(['user', 'task:id,title'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(15)
            ->get();

        return response()->json($items->map(fn (AssignedTaskComment $item): array => [
            'id' => $item->id,
            'task_id' => $item->task_id,
            'task_title' => $item->task?->title,
            'kind' => $item->kind,
            'is_reply' => $item->parent_id !== null,
            'body' => $item->body === null ? null : mb_strimwidth($item->body, 0, 120, '…'),
            'created_at' => $item->created_at?->toIso8601String(),
            'author' => $item->user ? $this->person($item->user) : null,
        ])->values());
    }

    /** For the pop-up reminder and the sidebar badge: how many tasks are new for this person. */
    public function unseen(Request $request): JsonResponse
    {
        $user = $this->currentUser($request);
        $isHead = Role::isHeadSecretary($user->tungkulin);
        $tasks = $this->visibleTasks($user, $isHead)
            ->when($isHead, fn ($query) => $query->whereNotNull('done_at'))
            ->get(['id', 'done_at']);
        $reads = $this->readsFor($user, $tasks->pluck('id'));
        $count = $tasks->filter(fn (AssignedTask $task): bool => $this->isNew($task, $isHead, $reads->get($task->id)))->count();

        $visibleIds = $this->visibleTasks($user, $isHead)->pluck('id');
        $mentions = ! $this->mentionsReady() ? collect() : AssignedTaskMention::query()
            ->where('user_id', $user->id)->whereNull('seen_at')->whereIn('task_id', $visibleIds)
            ->with(['task:id,title', 'mentionedBy:id,first_name,last_name,gmail', 'comment:id,body'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'count' => $count,
            'mentions' => [
                'count' => $mentions->count(),
                'latest' => $mentions->take(5)->map(fn (AssignedTaskMention $mention): array => [
                    'task_id' => $mention->task_id,
                    'task_title' => $mention->task?->title,
                    'by' => trim((string) $mention->mentionedBy?->full_name) ?: 'Someone',
                    'excerpt' => mb_strimwidth((string) ($mention->comment?->body ?? $mention->task?->title), 0, 90, '…'),
                ])->values(),
            ],
            'due_soon' => $this->dueSoon($user, $isHead),
            'is_head' => $isHead,
            'title' => $isHead
                ? ($count === 1 ? 'A task is waiting for your confirmation!' : "{$count} tasks are waiting for your confirmation!")
                : 'There are new tasks for you!',
            'message' => $isHead
                ? 'A secretary marked it as done. Open the Tasks menu to confirm it.'
                : ($count === 1 ? '1 new task is waiting. Open the Tasks menu.' : "{$count} new tasks are waiting. Open the Tasks menu."),
        ]);
    }

    /** Everyone who can be @mentioned (verified secretaries and Head Secretaries) with their @handle. */
    public function people(Request $request): JsonResponse
    {
        $this->currentUser($request);

        return response()->json($this->mentionDirectory()->values());
    }

    public function secretaries(Request $request): JsonResponse
    {
        $this->headOnly($request);

        return response()->json($this->assignableSecretaries()->map(fn (User $user): array => $this->person($user))->values());
    }

    public function store(Request $request): JsonResponse
    {
        $head = $this->headOnly($request);
        $validated = $this->validateTask($request);
        $this->assertDueDateNotPast($validated['due_date']);

        $task = DB::transaction(function () use ($validated, $head): AssignedTask {
            $task = AssignedTask::query()->create([
                'created_by' => $head->id,
                'title' => $validated['title'],
                'body' => $validated['body'],
                'due_date' => $validated['due_date'],
                'visibility' => $validated['visibility'],
                'others_can_comment' => $validated['others_can_comment'],
            ]);
            $this->syncMembers($task, $validated['members']);

            return $task;
        });
        $this->notify($task, 'created', $head);
        $this->recordMentions($task, null, $task->title."\n".$task->body, $head);

        return response()->json($this->freshPayload($task, $head), 201);
    }

    public function update(Request $request, AssignedTask $task): JsonResponse
    {
        $head = $this->headOnly($request);
        abort_if($task->confirmed_at !== null, 404);
        $validated = $this->validateTask($request);
        // An existing past due date may be kept as it is; a changed one cannot go back in time.
        if ($validated['due_date'] !== $task->due_date?->format('Y-m-d')) {
            $this->assertDueDateNotPast($validated['due_date']);
        }
        $before = $this->recipients($task, null);
        $alreadyMentioned = $this->mentionsReady()
            ? $task->mentions()->whereNull('comment_id')->pluck('user_id')->map(fn ($id): int => (int) $id)->all()
            : [];

        DB::transaction(function () use ($task, $validated, $head): void {
            $task->fill([
                'title' => $validated['title'],
                'body' => $validated['body'],
                'due_date' => $validated['due_date'],
                'visibility' => $validated['visibility'],
                'others_can_comment' => $validated['others_can_comment'],
            ])->save();
            $this->syncMembers($task, $validated['members']);
            // Adding someone who must finish sends a done task back to open; removing the last one
            // still working sends it to the Head.
            $this->syncCompletion($task, $head);
        });
        // People who were just added get it as a new task; everyone else as an update.
        $after = $this->recipients($task->refresh(), $head);
        $this->broadcast(array_values(array_diff($after, $before)), $task, 'created', $head);
        $this->broadcast(array_values(array_intersect($after, $before)), $task, 'updated', $head);
        $this->recordMentions($task, null, $task->title."\n".$task->body, $head, $alreadyMentioned);

        return response()->json($this->freshPayload($task, $head));
    }

    public function destroy(Request $request, AssignedTask $task): JsonResponse
    {
        $head = $this->headOnly($request);
        $recipients = $this->recipients($task, $head);
        $task->delete();
        $this->broadcast($recipients, $task, 'deleted', $head);

        return response()->json(['deleted' => true]);
    }

    public function comment(Request $request, AssignedTask $task): JsonResponse
    {
        $user = $this->currentUser($request);
        $isHead = Role::isHeadSecretary($user->tungkulin);
        abort_if($task->confirmed_at !== null, 404);
        $membership = $this->membership($task, $user, $isHead);
        abort_unless($this->canComment($task, $membership, $isHead), 403, 'You can view this task but not comment on it.');

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
            'parent_id' => ['nullable', 'integer'],
        ]);
        $body = trim($validated['body']);
        if ($body === '') {
            throw ValidationException::withMessages(['body' => 'Write a comment first.']);
        }

        // Replies are one level deep, like Facebook: a reply to a reply joins the same thread.
        $parentId = null;
        if (! empty($validated['parent_id'])) {
            $parent = $task->comments()->where('kind', 'comment')->whereKey($validated['parent_id'])->first();
            if (! $parent) {
                throw ValidationException::withMessages(['parent_id' => 'That comment no longer exists.']);
            }
            $parentId = $parent->parent_id ?? $parent->id;
        }

        $comment = $task->comments()->create(['user_id' => $user->id, 'parent_id' => $parentId, 'kind' => 'comment', 'body' => $body]);
        $task->touch();
        $this->notify($task, $parentId ? 'replied' : 'commented', $user);
        $this->recordMentions($task, (int) $comment->id, $body, $user);

        return response()->json($this->freshPayload($task, $user), 201);
    }

    /** Toggle a reaction on the task or on one of its comments; sending the same type again removes it. */
    public function react(Request $request, AssignedTask $task): JsonResponse
    {
        $user = $this->currentUser($request);
        $isHead = Role::isHeadSecretary($user->tungkulin);
        abort_if($task->confirmed_at !== null, 404);
        $this->membership($task, $user, $isHead);   // 404 unless this person can see the task

        $validated = $request->validate([
            'type' => ['required', 'in:'.implode(',', AssignedTaskReaction::TYPES)],
            'comment_id' => ['nullable', 'integer'],
        ]);
        $commentId = null;
        if (! empty($validated['comment_id'])) {
            $comment = $task->comments()->where('kind', 'comment')->whereKey($validated['comment_id'])->first();
            if (! $comment) {
                throw ValidationException::withMessages(['comment_id' => 'That comment no longer exists.']);
            }
            $commentId = $comment->id;
        }
        $key = $commentId ? 'c:'.$commentId : 't:'.$task->id;

        $existing = AssignedTaskReaction::query()->where('user_id', $user->id)->where('target_key', $key)->first();
        if ($existing && $existing->type === $validated['type']) {
            $existing->delete();
        } else {
            AssignedTaskReaction::query()->updateOrCreate(
                ['user_id' => $user->id, 'target_key' => $key],
                ['task_id' => $task->id, 'comment_id' => $commentId, 'type' => $validated['type']],
            );
        }
        $this->notify($task, 'reacted', $user);

        return response()->json($this->freshPayload($task, $user));
    }

    public function complete(Request $request, AssignedTask $task): JsonResponse
    {
        $user = $this->currentUser($request);
        abort_if($task->confirmed_at !== null, 404);
        $membership = $this->membership($task, $user, false);
        abort_unless($membership?->can_complete, 403, 'You are not allowed to mark this task as done.');
        if ($task->done_at !== null) {
            throw ValidationException::withMessages(['task' => 'This task is already marked as done and is waiting for the Head Secretary.']);
        }

        if (! $this->memberDoneReady()) {
            // Before the per-member migration: the first secretary to finish closes it for everyone.
            DB::transaction(function () use ($task, $user): void {
                $task->forceFill(['done_by' => $user->id, 'done_at' => now()])->save();
                $task->comments()->create(['user_id' => $user->id, 'kind' => 'done']);
            });
            $this->notify($task, 'done', $user);

            return response()->json($this->freshPayload($task, $user));
        }

        // Each secretary who may mark it done marks their own part; the task goes to the Head
        // only when all of them have. The row lock keeps two people finishing at once from racing.
        DB::transaction(function () use ($task, $user): void {
            $locked = AssignedTask::query()->whereKey($task->id)->lockForUpdate()->firstOrFail();
            $member = $locked->members()->where('user_id', $user->id)->lockForUpdate()->firstOrFail();
            if ($locked->done_at !== null || $member->done_at !== null) {
                throw ValidationException::withMessages(['task' => 'You already marked your part of this task as done.']);
            }
            $member->forceFill(['done_at' => now()])->save();
            $locked->comments()->create(['user_id' => $user->id, 'kind' => 'done']);
            $this->syncCompletion($locked, $user);
        });
        $this->notify($task, 'done', $user);

        return response()->json($this->freshPayload($task, $user));
    }

    /** A secretary takes back their own "done" while the task is still open (not yet sent to the Head). */
    public function undoComplete(Request $request, AssignedTask $task): JsonResponse
    {
        $user = $this->currentUser($request);
        abort_if($task->confirmed_at !== null || ! $this->memberDoneReady(), 404);
        $membership = $this->membership($task, $user, false);
        abort_unless($membership?->can_complete, 403, 'You are not allowed to mark this task as done.');

        DB::transaction(function () use ($task, $user): void {
            $locked = AssignedTask::query()->whereKey($task->id)->lockForUpdate()->firstOrFail();
            $member = $locked->members()->where('user_id', $user->id)->lockForUpdate()->firstOrFail();
            if ($locked->done_at !== null) {
                throw ValidationException::withMessages(['task' => 'Everyone has finished; only the Head Secretary can reopen it now.']);
            }
            if ($member->done_at === null) {
                throw ValidationException::withMessages(['task' => 'You have not marked this task as done.']);
            }
            $member->forceFill(['done_at' => null])->save();
            $locked->comments()->where('kind', 'done')->where('user_id', $user->id)->latest('id')->first()?->delete();
        });
        $this->notify($task, 'updated', $user);

        return response()->json($this->freshPayload($task, $user));
    }

    public function reopen(Request $request, AssignedTask $task): JsonResponse
    {
        $head = $this->headOnly($request);
        abort_if($task->confirmed_at !== null, 404);
        if ($task->done_at === null) {
            throw ValidationException::withMessages(['task' => 'This task is still open.']);
        }

        DB::transaction(function () use ($task, $head): void {
            $task->forceFill(['done_by' => null, 'done_at' => null])->save();
            if ($this->memberDoneReady()) {
                // Reopened means the work was not accepted: everyone marks their part again.
                $task->members()->update(['done_at' => null]);
            }
            $task->comments()->create(['user_id' => $head->id, 'kind' => 'reopened']);
        });
        $this->notify($task, 'reopened', $head);

        return response()->json($this->freshPayload($task, $head));
    }

    public function confirm(Request $request, AssignedTask $task): JsonResponse
    {
        $head = $this->headOnly($request);
        abort_if($task->confirmed_at !== null, 404);
        // The Head may close it as soon as at least one secretary has marked their part done, without waiting for the rest.
        $lastDone = $this->memberDoneReady()
            ? $task->members()->whereNotNull('done_at')->orderByDesc('done_at')->first()
            : null;
        if ($task->done_at === null && ! $lastDone) {
            throw ValidationException::withMessages(['task' => 'A secretary has to mark this task as done before you can confirm it.']);
        }

        DB::transaction(function () use ($task, $head, $lastDone): void {
            if ($task->done_at === null) {
                // Closed early: credit the latest secretary who finished.
                $task->forceFill(['done_by' => $lastDone->user_id, 'done_at' => $lastDone->done_at])->save();
            }
            $task->forceFill(['confirmed_by' => $head->id, 'confirmed_at' => now()])->save();
            $task->comments()->create(['user_id' => $head->id, 'kind' => 'confirmed']);
        });
        $this->notify($task, 'confirmed', $head);

        return response()->json(['confirmed' => true, 'id' => $task->id]);
    }

    /** False until the per-member done migration has run; until then the first secretary to finish closes the task. */
    private function memberDoneReady(): bool
    {
        static $ready = null;

        return $ready ??= Schema::hasColumn('assigned_task_members', 'done_at');
    }

    /**
     * Matches the task's status to its members' marks: done (waiting for the Head) once every member who may
     * mark it done has, open again while anyone is still working. $actor is credited when it becomes done.
     */
    private function syncCompletion(AssignedTask $task, User $actor): void
    {
        if (! $this->memberDoneReady()) {
            return;
        }
        $finishers = $task->members()->where('can_complete', true)->get(['user_id', 'done_at']);
        $allDone = $finishers->isNotEmpty() && $finishers->every(fn (AssignedTaskMember $member): bool => $member->done_at !== null);

        if ($allDone && $task->done_at === null) {
            $last = $finishers->sortByDesc('done_at')->first();
            $doneBy = Role::isHeadSecretary($actor->tungkulin) ? $last->user_id : $actor->id;
            $task->forceFill(['done_by' => $doneBy, 'done_at' => now()])->save();
        } elseif (! $allDone && $task->done_at !== null) {
            $task->forceFill(['done_by' => null, 'done_at' => null])->save();
        }
    }

    /** False until the mentions migration has run, so a database that is not migrated yet keeps working. */
    private function mentionsReady(): bool
    {
        static $ready = null;

        return $ready ??= Schema::hasTable('assigned_task_mentions');
    }

    /**
     * Verified secretaries and Head Secretaries with an @handle: their name without spaces or symbols
     * (e.g. "Kimberly Ostil" -> KimberlyOstil); a second person with the same name gets their id appended.
     */
    private function mentionDirectory(): Collection
    {
        $taken = [];

        return User::query()->where('is_verified', true)->orderBy('id')
            ->get(['id', 'first_name', 'last_name', 'gmail', 'tungkulin', 'is_verified', 'profile_picture'])
            ->filter(fn (User $user): bool => Role::isSecretary($user->tungkulin) || Role::isHeadSecretary($user->tungkulin))
            ->map(function (User $user) use (&$taken): array {
                $base = preg_replace('/[^\p{L}\p{N}]+/u', '', (string) $user->full_name)
                    ?: preg_replace('/[^\p{L}\p{N}]+/u', '', strstr((string) $user->gmail, '@', true) ?: (string) $user->gmail)
                    ?: 'User';
                $handle = isset($taken[mb_strtolower($base)]) ? $base.$user->id : $base;
                $taken[mb_strtolower($handle)] = true;

                // email_key (the part of the gmail before "@") lets the picker match "@formal". The Messages
                // account search already shows every verified user's full gmail, so this reveals nothing new.
                return $this->person($user) + [
                    'handle' => $handle,
                    'email_key' => mb_strtolower(strstr((string) $user->gmail, '@', true) ?: (string) $user->gmail),
                    'is_head' => Role::isHeadSecretary($user->tungkulin),
                ];
            })
            ->values();
    }

    /**
     * Saves and announces the @mentions in a task or comment. Only people who can see the task are notified;
     * the author and anyone in $exclude (already mentioned before an edit) are skipped.
     *
     * @return list<int> the people notified
     */
    private function recordMentions(AssignedTask $task, ?int $commentId, string $text, User $actor, array $exclude = []): array
    {
        if (! $this->mentionsReady() || ! str_contains($text, '@')) {
            return [];
        }
        $memberIds = $task->members()->pluck('user_id')->map(fn ($id): int => (int) $id)->all();
        $ids = collect($this->findMentions($text, $this->mentionDirectory()))
            ->filter(fn (array $person): bool => $person['is_head'] || $task->visibility === 'everyone' || in_array((int) $person['id'], $memberIds, true))
            ->map(fn (array $person): int => (int) $person['id'])
            ->unique()
            ->reject(fn (int $id): bool => $id === (int) $actor->id || in_array($id, $exclude, true))
            ->values()
            ->all();

        foreach ($ids as $id) {
            AssignedTaskMention::query()->create([
                'task_id' => $task->id,
                'comment_id' => $commentId,
                'user_id' => $id,
                'mentioned_by' => $actor->id,
            ]);
        }
        $this->broadcast($ids, $task, 'mentioned', $actor);

        return $ids;
    }

    /**
     * The people @mentioned in a text, by handle ("@CharlesAndreiGalvan") or by exact full name
     * ("@Charles Andrei Galvan", any case, any spacing between the words). When both could apply
     * at one "@", the longer match wins, so "@Kim Ostil" beats a person named just "Kim".
     *
     * @return list<array<string, mixed>>
     */
    private function findMentions(string $text, Collection $directory): array
    {
        if (! preg_match_all('/(?<![\p{L}\p{N}@])@/u', $text, $ats, PREG_OFFSET_CAPTURE)) {
            return [];
        }
        $byHandle = $directory->keyBy(fn (array $person): string => mb_strtolower($person['handle']));
        $names = $directory
            ->filter(fn (array $person): bool => trim((string) $person['name']) !== '')
            ->map(function (array $person): array {
                $words = preg_split('/\s+/u', trim((string) $person['name']), -1, PREG_SPLIT_NO_EMPTY);
                $pattern = implode('\s+', array_map(fn (string $word): string => preg_quote($word, '/'), $words));

                return ['person' => $person, 'pattern' => '/^'.$pattern.'(?![\p{L}\p{N}])/iu'];
            });

        $found = [];
        foreach ($ats[0] as [, $offset]) {
            // mb_strcut never splits a character, so the /u patterns below can't fail on a cut ñ or emoji.
            $rest = mb_strcut($text, $offset + 1, 300, 'UTF-8');
            $best = null;
            $length = 0;
            if (preg_match('/^[\p{L}\p{N}]+/u', $rest, $handle) && ($person = $byHandle->get(mb_strtolower($handle[0])))) {
                [$best, $length] = [$person, strlen($handle[0])];
            }
            foreach ($names as $entry) {
                if (preg_match($entry['pattern'], $rest, $name) && strlen($name[0]) > $length) {
                    [$best, $length] = [$entry['person'], strlen($name[0])];
                }
            }
            if ($best) {
                $found[] = $best;
            }
        }

        return $found;
    }

    private const RELATIONS = ['author', 'doneBy', 'members.user', 'reactions.user', 'comments.user'];

    /** Due dates are calendar days in Manila; "today" there is the earliest allowed. */
    private function assertDueDateNotPast(?string $dueDate): void
    {
        if ($dueDate !== null && $dueDate < Carbon::now('Asia/Manila')->toDateString()) {
            throw ValidationException::withMessages(['due_date' => 'The due date cannot be in the past.']);
        }
    }

    /**
     * Open tasks due within two days (or overdue) that need this person: for a secretary, the ones they
     * must finish (assigned and allowed to mark done, not done yet); for the Head, every open task.
     */
    private function dueSoon(User $user, bool $isHead): array
    {
        $today = Carbon::now('Asia/Manila')->startOfDay();
        $tasks = AssignedTask::query()
            ->whereNull('confirmed_at')
            ->whereNull('done_at')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<=', $today->copy()->addDays(2)->toDateString())
            ->unless($isHead, fn ($query) => $query->whereHas('members', fn ($members) => $members
                ->where('user_id', $user->id)->where('can_complete', true)
                ->when($this->memberDoneReady(), fn ($mine) => $mine->whereNull('done_at'))))
            ->orderBy('due_date')
            ->limit(20)
            ->get(['id', 'title', 'due_date']);

        return $tasks->map(fn (AssignedTask $task): array => [
            'id' => $task->id,
            'title' => $task->title,
            'due_date' => $task->due_date->format('Y-m-d'),
            'days_left' => (int) $today->diffInDays(Carbon::parse($task->due_date->format('Y-m-d'), 'Asia/Manila'), false),
        ])->all();
    }

    /** Everyone who can see the task (the Head Secretaries, the members, and every secretary when shared with everyone). */
    private function recipients(AssignedTask $task, ?User $except): array
    {
        $ids = User::query()->where('is_verified', true)->get(['id', 'tungkulin'])
            ->filter(fn (User $user): bool => Role::isHeadSecretary($user->tungkulin)
                || ($task->visibility === 'everyone' && Role::isSecretary($user->tungkulin)))
            ->pluck('id')
            ->merge($task->members()->pluck('user_id'))
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->reject(fn (int $id): bool => $except && $id === (int) $except->id)
            ->values()
            ->all();

        return $ids;
    }

    private function notify(AssignedTask $task, string $kind, User $actor): void
    {
        $this->broadcast($this->recipients($task, $actor), $task, $kind, $actor);
    }

    /** Live update over Reverb; never lets a broadcasting problem break the request (polling still works). */
    private function broadcast(array $recipientIds, AssignedTask $task, string $kind, User $actor): void
    {
        if ($recipientIds === [] || config('broadcasting.default') !== 'reverb') {
            return;
        }

        try {
            broadcast(new TaskFeedUpdated($recipientIds, (int) $task->id, $kind, (string) $task->title, trim((string) $actor->full_name) ?: null));
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function validateTask(Request $request): array
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'body' => ['nullable', 'string', 'max:5000'],
            'due_date' => ['nullable', 'date_format:Y-m-d'],
            'visibility' => ['sometimes', 'in:assigned,everyone'],
            'others_can_comment' => ['sometimes', 'boolean'],
            'members' => ['required', 'array', 'min:1', 'max:100'],
            'members.*.id' => ['required', 'integer', 'distinct'],
            'members.*.can_comment' => ['required', 'boolean'],
            'members.*.can_complete' => ['required', 'boolean'],
        ], [
            'members.required' => 'Choose at least one secretary who can see this task.',
            'members.min' => 'Choose at least one secretary who can see this task.',
        ]);

        $validated['title'] = trim($validated['title']);
        $validated['body'] = trim((string) ($validated['body'] ?? '')) ?: null;
        $validated['due_date'] ??= null;
        $validated['visibility'] ??= 'assigned';
        $validated['others_can_comment'] = $validated['visibility'] === 'everyone' && (bool) ($validated['others_can_comment'] ?? false);
        if ($validated['title'] === '') {
            throw ValidationException::withMessages(['title' => 'Give the task a title.']);
        }

        $allowed = $this->assignableSecretaries()->pluck('id')->map(fn ($id) => (int) $id)->all();
        foreach ($validated['members'] as $member) {
            if (! in_array((int) $member['id'], $allowed, true)) {
                throw ValidationException::withMessages(['members' => 'Tasks can only be shared with verified secretaries.']);
            }
        }

        return $validated;
    }

    private function syncMembers(AssignedTask $task, array $members): void
    {
        $ids = array_map(fn (array $member): int => (int) $member['id'], $members);
        $task->members()->whereNotIn('user_id', $ids)->delete();
        foreach ($members as $member) {
            AssignedTaskMember::query()->updateOrCreate(
                ['task_id' => $task->id, 'user_id' => (int) $member['id']],
                ['can_comment' => (bool) $member['can_comment'], 'can_complete' => (bool) $member['can_complete']],
            );
        }
    }

    private function assignableSecretaries(): Collection
    {
        return User::query()
            ->where('is_verified', true)
            ->orderBy('first_name')->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name', 'gmail', 'tungkulin', 'is_verified', 'profile_picture'])
            ->filter(fn (User $user): bool => Role::isSecretary($user->tungkulin))
            ->values();
    }

    /**
     * Open tasks this person can see: all for the Head; assigned or shared-with-everyone for a secretary.
     *
     * @return Builder<AssignedTask>
     */
    private function visibleTasks(User $user, bool $isHead): Builder
    {
        return AssignedTask::query()
            ->whereNull('confirmed_at')
            ->unless($isHead, fn ($query) => $query->where(fn ($visible) => $visible
                ->where('visibility', 'everyone')
                ->orWhereHas('members', fn ($members) => $members->where('user_id', $user->id))));
    }

    /**
     * Confirmed tasks this person could see while they were open.
     *
     * @return Builder<AssignedTask>
     */
    private function completedQuery(User $user, bool $isHead): Builder
    {
        return AssignedTask::query()
            ->whereNotNull('confirmed_at')
            ->unless($isHead, fn ($query) => $query->where(fn ($visible) => $visible
                ->where('visibility', 'everyone')
                ->orWhereHas('members', fn ($members) => $members->where('user_id', $user->id))));
    }

    /** @return Collection<int, string> seen_at by task id */
    private function readsFor(User $user, Collection $taskIds): Collection
    {
        if ($taskIds->isEmpty()) {
            return collect();
        }

        return DB::table('assigned_task_reads')
            ->where('user_id', $user->id)
            ->whereIn('task_id', $taskIds)
            ->pluck('seen_at', 'task_id');
    }

    /**
     * New for a secretary: never opened. New for the Head: marked done at or after the moment the Head's
     * last viewed list was loaded (same-second counts as new: better one extra reminder than a missed one).
     */
    private function isNew(AssignedTask $task, bool $isHead, ?string $seenAt): bool
    {
        if ($isHead) {
            return $task->done_at !== null && ($seenAt === null || $task->done_at->greaterThanOrEqualTo(Carbon::parse($seenAt)));
        }

        return $seenAt === null;
    }

    private function markSeen(User $user, Collection $taskIds, Carbon $seenAt): void
    {
        if ($taskIds->isEmpty()) {
            return;
        }
        DB::table('assigned_task_reads')->upsert(
            $taskIds->map(fn ($id): array => ['task_id' => $id, 'user_id' => $user->id, 'seen_at' => $seenAt])->all(),
            ['task_id', 'user_id'],
            ['seen_at'],
        );
    }

    private function membership(AssignedTask $task, User $user, bool $isHead): ?AssignedTaskMember
    {
        $membership = $task->members()->where('user_id', $user->id)->first();
        // Not assigned, not shared with everyone, and not the Head: the task does not exist for this person.
        abort_if(! $isHead && ! $membership && $task->visibility !== 'everyone', 404);

        return $membership;
    }

    private function canComment(AssignedTask $task, ?AssignedTaskMember $membership, bool $isHead): bool
    {
        if ($isHead) {
            return true;
        }

        return $membership
            ? $membership->can_comment
            : $task->visibility === 'everyone' && $task->others_can_comment;
    }

    private function currentUser(Request $request): User
    {
        $user = User::query()
            ->whereKey($request->session()->get('login_user.id'))
            ->where('is_verified', true)
            ->firstOrFail();
        abort_unless(Role::isSecretary($user->tungkulin) || Role::isHeadSecretary($user->tungkulin), 403);

        return $user;
    }

    private function headOnly(Request $request): User
    {
        $user = $this->currentUser($request);
        abort_unless(Role::isHeadSecretary($user->tungkulin), 403, 'Only the Head Secretary can manage assigned tasks.');

        return $user;
    }

    private function freshPayload(AssignedTask $task, User $viewer): array
    {
        $task = $task->fresh(self::RELATIONS);

        return $this->payload($task, $viewer, Role::isHeadSecretary($viewer->tungkulin));
    }

    private function payload(AssignedTask $task, User $viewer, bool $isHead): array
    {
        $membership = $task->members->firstWhere('user_id', $viewer->id);
        $status = $task->done_at === null ? 'open' : 'awaiting_confirmation';
        $finishers = $task->members->where('can_complete', true);
        $myDone = $this->memberDoneReady() && $membership?->done_at !== null;

        return [
            'id' => $task->id,
            'title' => $task->title,
            'body' => $task->body,
            'due_date' => $task->due_date?->format('Y-m-d'),
            'created_at' => $task->created_at?->toIso8601String(),
            'updated_at' => $task->updated_at?->toIso8601String(),
            'status' => $status,
            'visibility' => $task->visibility,
            'others_can_comment' => $task->others_can_comment,
            'author' => $task->author ? $this->person($task->author) : null,
            'done_by' => $task->doneBy ? $this->person($task->doneBy) : null,
            'done_at' => $task->done_at?->toIso8601String(),
            'members' => $task->members->filter(fn (AssignedTaskMember $member) => $member->user)->map(fn (AssignedTaskMember $member): array => $this->person($member->user) + [
                'can_comment' => $member->can_comment,
                'can_complete' => $member->can_complete,
                'done_at' => $member->done_at?->toIso8601String(),
            ])->values(),
            // How many of the people who must finish it have marked their part done.
            'progress' => [
                'done' => $this->memberDoneReady()
                    ? $finishers->whereNotNull('done_at')->count()
                    : ($status === 'open' ? 0 : $finishers->count()),
                'total' => $finishers->count(),
            ],
            'reactions' => $this->reactionSummary($task->reactions->whereNull('comment_id'), $viewer),
            'comments' => $task->comments->sortBy([['created_at', 'asc'], ['id', 'asc']])->map(fn (AssignedTaskComment $comment): array => [
                'id' => $comment->id,
                'parent_id' => $comment->parent_id,
                'kind' => $comment->kind,
                'body' => $comment->body,
                'created_at' => $comment->created_at?->toIso8601String(),
                'author' => $comment->user ? $this->person($comment->user) : null,
                'mine' => (int) $comment->user_id === (int) $viewer->id,
                'reactions' => $this->reactionSummary($task->reactions->where('comment_id', $comment->id), $viewer),
            ])->values(),
            'viewer' => [
                'is_assigned' => (bool) $membership,
                'can_comment' => $this->canComment($task, $membership, $isHead),
                'can_complete' => ! $isHead && (bool) $membership?->can_complete && $status === 'open' && ! $myDone,
                'my_done' => $myDone,
                'can_undo' => ! $isHead && (bool) $membership?->can_complete && $status === 'open' && $myDone,
                'can_manage' => $isHead,
                // Also while still open, once anyone has marked their part done (the Head can close it early).
                'can_confirm' => $isHead && ($status === 'awaiting_confirmation'
                    || ($this->memberDoneReady() && $finishers->whereNotNull('done_at')->isNotEmpty())),
                'can_reopen' => $isHead && $status === 'awaiting_confirmation',
            ],
        ];
    }

    /** Counts per type (most used first), the total, the viewer's own reaction, and who reacted (names). */
    private function reactionSummary(Collection $reactions, User $viewer): array
    {
        $counts = $reactions->countBy('type')->sortDesc();

        return [
            'total' => $reactions->count(),
            'types' => $counts->keys()->values()->all(),
            'counts' => $counts->all(),
            'mine' => $reactions->firstWhere('user_id', $viewer->id)?->type,
            'names' => $reactions->map(fn (AssignedTaskReaction $reaction): string => trim((string) $reaction->user?->full_name) ?: 'Someone')->take(15)->values()->all(),
        ];
    }

    private function person(User $user): array
    {
        $name = trim((string) $user->full_name);

        return [
            'id' => $user->id,
            'name' => $name !== '' ? $name : $user->gmail,
            'role' => $user->tungkulin,
            'avatar_url' => $user->is_verified ? ProfilePictureController::urlFor($user->id, $user->profile_picture) : null,
        ];
    }
}
