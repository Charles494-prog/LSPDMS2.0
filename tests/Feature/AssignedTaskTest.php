<?php

namespace Tests\Feature;

use App\Events\TaskFeedUpdated;
use App\Models\AssignedTask;
use App\Models\AssignedTaskMention;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class AssignedTaskTest extends TestCase
{
    use RefreshDatabase;

    public function test_head_assigns_a_task_and_only_members_see_it(): void
    {
        $head = $this->makeUser('Head Secretary');
        $ana = $this->makeUser('Secretary', 'Ana');
        $ben = $this->makeUser('Secretary', 'Ben');
        $outsider = $this->makeUser('Secretary', 'Cara');

        $this->as($head)->getJson(route('assigned-tasks.secretaries'))
            ->assertOk()
            ->assertJsonCount(3)
            ->assertJsonMissing(['id' => $head->id]);

        $taskId = $this->as($head)->postJson(route('assigned-tasks.store'), [
            'title' => 'Update the Purok 3 registry',
            'body' => 'Check the missing baptism dates.',
            'due_date' => '2026-11-05',
            'members' => [
                ['id' => $ana->id, 'can_comment' => true, 'can_complete' => true],
                ['id' => $ben->id, 'can_comment' => false, 'can_complete' => false],
            ],
        ])->assertCreated()
            ->assertJsonPath('status', 'open')
            ->assertJsonCount(2, 'members')
            ->json('id');

        $this->as($ana)->getJson(route('assigned-tasks.index'))
            ->assertOk()
            ->assertJsonPath('is_head', false)
            ->assertJsonPath('attention', 1)
            ->assertJsonPath('tasks.0.id', $taskId)
            ->assertJsonPath('tasks.0.viewer.can_comment', true)
            ->assertJsonPath('tasks.0.viewer.can_complete', true)
            ->assertJsonPath('tasks.0.viewer.can_manage', false);

        $this->as($ben)->getJson(route('assigned-tasks.index'))
            ->assertOk()
            ->assertJsonPath('attention', 0)
            ->assertJsonPath('tasks.0.viewer.can_comment', false)
            ->assertJsonPath('tasks.0.viewer.can_complete', false);

        $this->as($outsider)->getJson(route('assigned-tasks.index'))
            ->assertOk()
            ->assertJsonCount(0, 'tasks');
        $this->as($outsider)->postJson(route('assigned-tasks.comment', $taskId), ['body' => 'Hi'])
            ->assertNotFound();
    }

    public function test_comment_and_done_permissions_are_enforced_on_the_server(): void
    {
        [$head, $ana, $ben, $taskId] = $this->taskWithTwoMembers();

        $this->as($ben)->postJson(route('assigned-tasks.comment', $taskId), ['body' => 'Can I help?'])
            ->assertForbidden();
        $this->as($ben)->postJson(route('assigned-tasks.complete', $taskId))
            ->assertForbidden();

        $this->as($ana)->postJson(route('assigned-tasks.comment', $taskId), ['body' => '  On it!  '])
            ->assertCreated()
            ->assertJsonPath('comments.0.body', 'On it!')
            ->assertJsonPath('comments.0.mine', true);
        $this->as($ana)->postJson(route('assigned-tasks.comment', $taskId), ['body' => '   '])
            ->assertUnprocessable();

        // Secretaries cannot manage tasks.
        $this->as($ana)->postJson(route('assigned-tasks.store'), ['title' => 'x', 'members' => [['id' => $ben->id, 'can_comment' => true, 'can_complete' => true]]])
            ->assertForbidden();
        $this->as($ana)->postJson(route('assigned-tasks.confirm', $taskId))->assertForbidden();
        $this->as($ana)->deleteJson(route('assigned-tasks.destroy', $taskId))->assertForbidden();
    }

    public function test_secretary_done_waits_for_head_confirmation_then_the_task_closes(): void
    {
        [$head, $ana, $ben, $taskId] = $this->taskWithTwoMembers();

        // The Head cannot confirm before a secretary marks it done.
        $this->as($head)->postJson(route('assigned-tasks.confirm', $taskId))->assertUnprocessable();

        $this->as($ana)->postJson(route('assigned-tasks.complete', $taskId))
            ->assertOk()
            ->assertJsonPath('status', 'awaiting_confirmation')
            ->assertJsonPath('done_by.id', $ana->id)
            ->assertJsonPath('comments.0.kind', 'done');
        $this->as($ana)->postJson(route('assigned-tasks.complete', $taskId))->assertUnprocessable();

        // Still visible to everyone until the Head confirms.
        $this->as($ben)->getJson(route('assigned-tasks.index'))->assertJsonCount(1, 'tasks');
        $this->as($head)->getJson(route('assigned-tasks.index'))
            ->assertJsonPath('attention', 1)
            ->assertJsonPath('tasks.0.viewer.can_confirm', true);

        // Reopen sends it back; done again; then confirm closes it.
        $this->as($head)->postJson(route('assigned-tasks.reopen', $taskId))
            ->assertOk()
            ->assertJsonPath('status', 'open');
        $this->as($ana)->postJson(route('assigned-tasks.complete', $taskId))->assertOk();
        $this->as($head)->postJson(route('assigned-tasks.confirm', $taskId))->assertOk();

        $this->as($ana)->getJson(route('assigned-tasks.index'))->assertJsonCount(0, 'tasks');
        $this->as($head)->getJson(route('assigned-tasks.index'))->assertJsonCount(0, 'tasks');
        $this->assertNotNull(AssignedTask::query()->find($taskId)->confirmed_at);
        $this->assertDatabaseHas('assigned_task_comments', ['task_id' => $taskId, 'kind' => 'confirmed', 'user_id' => $head->id]);
    }

    public function test_head_can_change_who_sees_a_task_and_their_permissions(): void
    {
        [$head, $ana, $ben, $taskId] = $this->taskWithTwoMembers();

        $this->as($head)->putJson(route('assigned-tasks.update', $taskId), [
            'title' => 'Renamed task',
            'body' => null,
            'due_date' => null,
            'members' => [['id' => $ben->id, 'can_comment' => true, 'can_complete' => true]],
        ])->assertOk()
            ->assertJsonPath('title', 'Renamed task')
            ->assertJsonCount(1, 'members');

        $this->as($ana)->getJson(route('assigned-tasks.index'))->assertJsonCount(0, 'tasks');
        $this->as($ben)->getJson(route('assigned-tasks.index'))
            ->assertJsonPath('tasks.0.viewer.can_comment', true)
            ->assertJsonPath('tasks.0.viewer.can_complete', true);
    }

    public function test_a_task_shared_with_everyone_is_visible_to_all_secretaries_but_only_assignees_finish_it(): void
    {
        $head = $this->makeUser('Head Secretary');
        $ana = $this->makeUser('Secretary', 'Ana');
        $other = $this->makeUser('Secretary', 'Ben');

        $taskId = $this->as($head)->postJson(route('assigned-tasks.store'), [
            'title' => 'Team reminder',
            'visibility' => 'everyone',
            'others_can_comment' => false,
            'members' => [['id' => $ana->id, 'can_comment' => true, 'can_complete' => true]],
        ])->assertCreated()->assertJsonPath('visibility', 'everyone')->json('id');

        $this->as($other)->getJson(route('assigned-tasks.index'))
            ->assertJsonPath('tasks.0.id', $taskId)
            ->assertJsonPath('tasks.0.viewer.is_assigned', false)
            ->assertJsonPath('tasks.0.viewer.can_comment', false)
            ->assertJsonPath('tasks.0.viewer.can_complete', false);
        $this->as($other)->postJson(route('assigned-tasks.comment', $taskId), ['body' => 'Hi'])->assertForbidden();
        $this->as($other)->postJson(route('assigned-tasks.complete', $taskId))->assertForbidden();

        // The Head lets everyone comment.
        $this->as($head)->putJson(route('assigned-tasks.update', $taskId), [
            'title' => 'Team reminder',
            'visibility' => 'everyone',
            'others_can_comment' => true,
            'members' => [['id' => $ana->id, 'can_comment' => true, 'can_complete' => true]],
        ])->assertOk();
        $this->as($other)->postJson(route('assigned-tasks.comment', $taskId), ['body' => 'Noted!'])->assertCreated();

        // Back to assigned-only: Ben no longer sees it.
        $this->as($head)->putJson(route('assigned-tasks.update', $taskId), [
            'title' => 'Team reminder',
            'visibility' => 'assigned',
            'others_can_comment' => true,
            'members' => [['id' => $ana->id, 'can_comment' => true, 'can_complete' => true]],
        ])->assertOk()->assertJsonPath('others_can_comment', false);
        $this->as($other)->getJson(route('assigned-tasks.index'))->assertJsonCount(0, 'tasks');
    }

    public function test_tasks_can_only_be_shared_with_verified_secretaries(): void
    {
        $head = $this->makeUser('Head Secretary');
        $unverified = $this->makeUser('Secretary', 'Dan', false);

        $this->as($head)->postJson(route('assigned-tasks.store'), [
            'title' => 'Task',
            'members' => [['id' => $unverified->id, 'can_comment' => true, 'can_complete' => true]],
        ])->assertUnprocessable()->assertJsonValidationErrors('members');

        $this->as($head)->postJson(route('assigned-tasks.store'), ['title' => 'Task', 'members' => []])
            ->assertUnprocessable()->assertJsonValidationErrors('members');
    }

    public function test_chat_window_has_chats_and_my_to_do_only(): void
    {
        $secretary = $this->makeUser('Secretary', 'Ana');

        $this->as($secretary)->get(route('messages.index', ['widget' => 1]))
            ->assertOk()
            ->assertSee('id="workspaceTabs"', false)
            ->assertSee('data-workspace-tab="chats"', false)
            ->assertSee('data-workspace-tab="todo"', false)
            ->assertSee(route('secretary-todos.index'), false)
            ->assertDontSee('data-workspace-tab="tasks"', false)
            ->assertDontSee('data-todo-scope="shared"', false)
            ->assertDontSee(route('assigned-tasks.index'), false);
    }

    public function test_tasks_is_a_sidebar_page_for_secretaries_and_the_head_only(): void
    {
        $head = $this->makeUser('Head Secretary');
        $secretary = $this->makeUser('Secretary', 'Ana');
        $other = $this->makeUser('Member', 'Oscar');

        $this->as($secretary)->get(route('tasks'))
            ->assertOk()
            ->assertSee('id="tasksApp"', false)
            ->assertSee(route('assigned-tasks.seen'), false)
            ->assertDontSee('id="taskComposer"', false)
            ->assertSee('id="tasksNavBadge"', false)
            ->assertSee('const unseenUrl', false);
        $this->as($head)->get(route('tasks'))
            ->assertOk()
            ->assertSee('id="taskComposer"', false);
        $this->as($secretary)->get(route('home'))
            ->assertOk()
            ->assertSee('href="'.route('tasks').'"', false);

        $this->as($other)->get(route('tasks'))->assertForbidden();
        $this->as($other)->get(route('home'))
            ->assertOk()
            ->assertDontSee('href="'.route('tasks').'"', false)
            ->assertDontSee('const unseenUrl', false);
        $this->flushSession();
        $this->get(route('tasks'))->assertRedirect('/login');
    }

    public function test_new_tasks_stay_new_until_the_secretary_opens_the_tasks_page(): void
    {
        [$head, $ana, $ben, $taskId] = $this->taskWithTwoMembers();
        $outsider = $this->makeUser('Secretary', 'Cara');

        $this->as($ana)->getJson(route('assigned-tasks.unseen'))
            ->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('title', 'There are new tasks for you!');
        $this->as($outsider)->getJson(route('assigned-tasks.unseen'))->assertJsonPath('count', 0);

        // A plain list request (no seen flag) does not clear it…
        $this->as($ana)->getJson(route('assigned-tasks.index'))->assertJsonPath('tasks.0.is_new', true);
        $this->as($ana)->getJson(route('assigned-tasks.unseen'))->assertJsonPath('count', 1);

        // …and neither does a GET with a "seen" flag (a cross-site link must not clear reminders)…
        $this->as($ana)->getJson(route('assigned-tasks.index', ['seen' => 1]))->assertOk();
        $this->as($ana)->getJson(route('assigned-tasks.unseen'))->assertJsonPath('count', 1);

        // …opening the Tasks page does: it shows the list, then POSTs what it showed.
        $this->markSeenAsTasksPage($ana);
        $this->as($ana)->getJson(route('assigned-tasks.unseen'))->assertJsonPath('count', 0);
        $this->as($ana)->getJson(route('assigned-tasks.index'))->assertJsonPath('tasks.0.is_new', false);

        // Ben was not affected.
        $this->as($ben)->getJson(route('assigned-tasks.unseen'))->assertJsonPath('count', 1);
    }

    public function test_head_is_reminded_about_tasks_marked_done_until_seen(): void
    {
        [$head, $ana, $ben, $taskId] = $this->taskWithTwoMembers();

        $this->as($head)->getJson(route('assigned-tasks.unseen'))->assertJsonPath('count', 0);

        $this->as($ana)->postJson(route('assigned-tasks.complete', $taskId))->assertOk();
        $this->as($head)->getJson(route('assigned-tasks.unseen'))
            ->assertJsonPath('count', 1)
            ->assertJsonPath('is_head', true);

        // (A "done" in the very same second as the Head's view still counts as new, by design.)
        $this->travel(1)->seconds();
        $this->markSeenAsTasksPage($head);
        $this->as($head)->getJson(route('assigned-tasks.unseen'))->assertJsonPath('count', 0);

        // Reopened and marked done again later: a new reminder.
        $this->travel(1)->minutes();
        $this->as($head)->postJson(route('assigned-tasks.reopen', $taskId))->assertOk();
        $this->travel(1)->minutes();
        $this->as($ana)->postJson(route('assigned-tasks.complete', $taskId))->assertOk();
        $this->as($head)->getJson(route('assigned-tasks.unseen'))->assertJsonPath('count', 1);
    }

    public function test_mark_seen_only_touches_tasks_the_person_can_see_and_never_the_future(): void
    {
        [$head, $ana, $ben, $taskId] = $this->taskWithTwoMembers();
        $outsider = $this->makeUser('Secretary', 'Cara');

        // Cannot mark a task they cannot see.
        $this->as($outsider)->postJson(route('assigned-tasks.seen'), ['ids' => [$taskId], 'as_of' => now()->toIso8601String()])
            ->assertOk()
            ->assertJsonPath('seen', 0);
        $this->assertDatabaseMissing('assigned_task_reads', ['task_id' => $taskId, 'user_id' => $outsider->id]);

        // A future as_of is clamped to now, so a later "done" still reminds the Head.
        $this->as($head)->postJson(route('assigned-tasks.seen'), ['ids' => [$taskId], 'as_of' => now()->addYear()->toIso8601String()])
            ->assertOk()
            ->assertJsonPath('seen', 1);
        $this->travel(1)->minutes();
        $this->as($ana)->postJson(route('assigned-tasks.complete', $taskId))->assertOk();
        $this->as($head)->getJson(route('assigned-tasks.unseen'))->assertJsonPath('count', 1);

        $this->as($ana)->postJson(route('assigned-tasks.seen'), ['ids' => 'nope', 'as_of' => 'x'])->assertUnprocessable();
    }

    public function test_reactions_toggle_and_switch_on_tasks_and_comments(): void
    {
        [$head, $ana, $ben, $taskId] = $this->taskWithTwoMembers();
        $outsider = $this->makeUser('Secretary', 'Cara');

        // Ben may not comment, but anyone who can see a task can react.
        $this->as($ben)->postJson(route('assigned-tasks.react', $taskId), ['type' => 'haha'])
            ->assertOk()
            ->assertJsonPath('reactions.total', 1)
            ->assertJsonPath('reactions.mine', 'haha');
        $this->as($ana)->postJson(route('assigned-tasks.react', $taskId), ['type' => 'love'])
            ->assertJsonPath('reactions.total', 2)
            ->assertJsonPath('reactions.counts.love', 1);
        // Switching keeps one reaction per person; sending the same type again removes it.
        $this->as($ana)->postJson(route('assigned-tasks.react', $taskId), ['type' => 'like'])->assertJsonPath('reactions.total', 2);
        $this->as($ana)->postJson(route('assigned-tasks.react', $taskId), ['type' => 'like'])
            ->assertJsonPath('reactions.total', 1)
            ->assertJsonPath('reactions.mine', null);

        $commentId = $this->as($ana)->postJson(route('assigned-tasks.comment', $taskId), ['body' => 'On it'])->json('comments.0.id');
        $this->as($head)->postJson(route('assigned-tasks.react', $taskId), ['type' => 'like', 'comment_id' => $commentId])
            ->assertJsonPath('comments.0.reactions.total', 1)
            ->assertJsonPath('comments.0.reactions.mine', 'like')
            ->assertJsonPath('reactions.total', 1);   // the task's own reactions are separate

        $this->as($ana)->postJson(route('assigned-tasks.react', $taskId), ['type' => 'party'])->assertUnprocessable();
        $this->as($ana)->postJson(route('assigned-tasks.react', $taskId), ['type' => 'like', 'comment_id' => 999999])->assertUnprocessable();
        $this->as($outsider)->postJson(route('assigned-tasks.react', $taskId), ['type' => 'like'])->assertNotFound();
    }

    public function test_replies_are_one_level_deep_and_need_comment_permission(): void
    {
        [$head, $ana, $ben, $taskId] = $this->taskWithTwoMembers();
        $top = $this->as($ana)->postJson(route('assigned-tasks.comment', $taskId), ['body' => 'Question?'])->json('comments.0.id');

        $reply = $this->as($head)->postJson(route('assigned-tasks.comment', $taskId), ['body' => 'Answer', 'parent_id' => $top])
            ->assertCreated()
            ->assertJsonPath('comments.1.parent_id', $top)
            ->json('comments.1.id');
        // A reply to a reply joins the same thread.
        $this->as($ana)->postJson(route('assigned-tasks.comment', $taskId), ['body' => 'Thanks', 'parent_id' => $reply])
            ->assertCreated()
            ->assertJsonPath('comments.2.parent_id', $top);

        $this->as($ben)->postJson(route('assigned-tasks.comment', $taskId), ['body' => 'Me too', 'parent_id' => $top])->assertForbidden();
        $this->as($ana)->postJson(route('assigned-tasks.comment', $taskId), ['body' => 'x', 'parent_id' => 999999])->assertUnprocessable();
    }

    public function test_due_date_cannot_be_in_the_past_but_an_existing_one_can_stay(): void
    {
        config(['session.idle_timeout' => 0]); // the clock jumps days ahead within one signed-in session
        $this->travelTo(now('Asia/Manila')->setTime(10, 0)->utc());
        $head = $this->makeUser('Head Secretary');
        $ana = $this->makeUser('Secretary', 'Ana');
        $members = [['id' => $ana->id, 'can_comment' => true, 'can_complete' => true]];
        $yesterday = now('Asia/Manila')->subDay()->toDateString();
        $today = now('Asia/Manila')->toDateString();

        $this->as($head)->postJson(route('assigned-tasks.store'), ['title' => 'Late', 'due_date' => $yesterday, 'members' => $members])
            ->assertUnprocessable()->assertJsonValidationErrors('due_date');
        $taskId = $this->as($head)->postJson(route('assigned-tasks.store'), ['title' => 'Today', 'due_date' => $today, 'members' => $members])
            ->assertCreated()->json('id');

        // Two days later the task is overdue: saving other edits keeps the old date, but it cannot be moved further back.
        $this->travel(2)->days();
        $this->as($head)->putJson(route('assigned-tasks.update', $taskId), ['title' => 'Renamed', 'due_date' => $today, 'members' => $members])->assertOk();
        $this->as($head)->putJson(route('assigned-tasks.update', $taskId), ['title' => 'Renamed', 'due_date' => $yesterday, 'members' => $members])
            ->assertUnprocessable()->assertJsonValidationErrors('due_date');
    }

    public function test_due_soon_reminds_the_people_who_must_finish_the_task(): void
    {
        $head = $this->makeUser('Head Secretary');
        $ana = $this->makeUser('Secretary', 'Ana');
        $ben = $this->makeUser('Secretary', 'Ben');
        $tomorrow = now('Asia/Manila')->addDay()->toDateString();
        $this->as($head)->postJson(route('assigned-tasks.store'), [
            'title' => 'Due tomorrow',
            'due_date' => $tomorrow,
            'members' => [
                ['id' => $ana->id, 'can_comment' => true, 'can_complete' => true],
                ['id' => $ben->id, 'can_comment' => true, 'can_complete' => false],
            ],
        ])->assertCreated();
        $this->as($head)->postJson(route('assigned-tasks.store'), [
            'title' => 'Next month',
            'due_date' => now('Asia/Manila')->addMonth()->toDateString(),
            'members' => [['id' => $ana->id, 'can_comment' => true, 'can_complete' => true]],
        ])->assertCreated();

        $this->as($ana)->getJson(route('assigned-tasks.unseen'))
            ->assertJsonCount(1, 'due_soon')
            ->assertJsonPath('due_soon.0.title', 'Due tomorrow')
            ->assertJsonPath('due_soon.0.days_left', 1);
        $this->as($ben)->getJson(route('assigned-tasks.unseen'))->assertJsonCount(0, 'due_soon');
        $this->as($head)->getJson(route('assigned-tasks.unseen'))->assertJsonCount(1, 'due_soon');
    }

    public function test_changes_are_pushed_live_to_everyone_who_can_see_the_task(): void
    {
        config(['broadcasting.default' => 'reverb']);
        Event::fake([TaskFeedUpdated::class]);
        $head = $this->makeUser('Head Secretary');
        $ana = $this->makeUser('Secretary', 'Ana');
        $outsider = $this->makeUser('Secretary', 'Cara');

        $taskId = $this->as($head)->postJson(route('assigned-tasks.store'), [
            'title' => 'Live',
            'members' => [['id' => $ana->id, 'can_comment' => true, 'can_complete' => true]],
        ])->assertCreated()->json('id');
        Event::assertDispatched(TaskFeedUpdated::class, fn (TaskFeedUpdated $event): bool => $event->kind === 'created'
            && $event->taskId === $taskId
            && $event->recipientIds === [$ana->id]);   // not the Head who posted it, not Cara

        $this->as($ana)->postJson(route('assigned-tasks.comment', $taskId), ['body' => 'Hi'])->assertCreated();
        Event::assertDispatched(TaskFeedUpdated::class, fn (TaskFeedUpdated $event): bool => $event->kind === 'commented'
            && in_array($head->id, $event->recipientIds, true)
            && ! in_array($ana->id, $event->recipientIds, true)
            && ! in_array($outsider->id, $event->recipientIds, true));
        $this->assertSame(['private-user.'.$head->id], array_map('strval', (new TaskFeedUpdated([$head->id], $taskId, 'x', 'Live', null))->broadcastOn()));
    }

    public function test_activity_and_completed_history_respect_visibility(): void
    {
        [$head, $ana, $ben, $taskId] = $this->taskWithTwoMembers();
        $outsider = $this->makeUser('Secretary', 'Cara');
        $this->as($ana)->postJson(route('assigned-tasks.comment', $taskId), ['body' => 'Working on it'])->assertCreated();

        $this->as($ben)->getJson(route('assigned-tasks.activity'))
            ->assertOk()
            ->assertJsonPath('0.kind', 'comment')
            ->assertJsonPath('0.task_title', 'Prepare the R4-02')
            ->assertJsonPath('0.body', 'Working on it');
        $this->as($outsider)->getJson(route('assigned-tasks.activity'))->assertOk()->assertJsonCount(0);

        $this->as($ana)->postJson(route('assigned-tasks.complete', $taskId))->assertOk();
        $this->as($head)->postJson(route('assigned-tasks.confirm', $taskId))->assertOk();

        $this->as($ana)->getJson(route('assigned-tasks.completed'))
            ->assertOk()
            ->assertJsonPath('0.id', $taskId)
            ->assertJsonPath('0.done_by.id', $ana->id)
            ->assertJsonPath('0.confirmed_by.id', $head->id)
            ->assertJsonPath('0.on_time', true);
        $this->as($outsider)->getJson(route('assigned-tasks.completed'))->assertOk()->assertJsonCount(0);
        $this->as($head)->getJson(route('assigned-tasks.index'))->assertJsonPath('completed_count', 1);
    }

    public function test_people_directory_gives_each_person_a_unique_mention_handle(): void
    {
        $head = $this->makeUser('Head Secretary');
        $first = $this->makeUser('Secretary', 'Kimberly');
        $twin = $this->makeUser('Secretary', 'Kimberly');   // same name: "KimberlyTester" is taken
        $this->makeUser('Member', 'Oscar');                  // not a secretary: not mentionable

        $people = collect($this->as($first)->getJson(route('assigned-tasks.people'))->assertOk()->json());
        $this->assertSame('KimberlyTester', $people->firstWhere('id', $first->id)['handle']);
        $this->assertSame('KimberlyTester'.$twin->id, $people->firstWhere('id', $twin->id)['handle']);
        $this->assertSame('HeadTester', $people->firstWhere('id', $head->id)['handle']);
        $this->assertNull($people->firstWhere('name', 'Oscar Tester'));
    }

    public function test_mentions_notify_only_people_who_can_see_the_task_until_they_open_tasks(): void
    {
        config(['broadcasting.default' => 'reverb']);
        Event::fake([TaskFeedUpdated::class]);
        $head = $this->makeUser('Head Secretary');
        $ana = $this->makeUser('Secretary', 'Ana');
        $outsider = $this->makeUser('Secretary', 'Cara');

        $taskId = $this->as($head)->postJson(route('assigned-tasks.store'), [
            'title' => 'Check the R4-01',
            'body' => '@AnaTester please start, and @CaraTester too',
            'members' => [['id' => $ana->id, 'can_comment' => true, 'can_complete' => true]],
        ])->assertCreated()->json('id');

        // Ana is assigned, so she is notified; Cara cannot see an "assigned only" task, so she is not.
        Event::assertDispatched(TaskFeedUpdated::class, fn (TaskFeedUpdated $event): bool => $event->kind === 'mentioned' && $event->recipientIds === [$ana->id]);
        $this->as($ana)->getJson(route('assigned-tasks.unseen'))
            ->assertJsonPath('mentions.count', 1)
            ->assertJsonPath('mentions.latest.0.by', 'Head Tester')
            ->assertJsonPath('mentions.latest.0.task_title', 'Check the R4-01');
        $this->as($outsider)->getJson(route('assigned-tasks.unseen'))->assertJsonPath('mentions.count', 0);
        $this->as($ana)->getJson(route('assigned-tasks.index'))->assertJsonPath('tasks.0.mentions_me', true);

        // A secretary can mention the Head Secretary in a comment.
        $this->as($ana)->postJson(route('assigned-tasks.comment', $taskId), ['body' => 'Done with part 1, @HeadTester please check'])->assertCreated();
        $this->as($head)->getJson(route('assigned-tasks.unseen'))->assertJsonPath('mentions.count', 1);

        // Opening the Tasks page clears the mention.
        $this->travel(1)->seconds();
        $this->markSeenAsTasksPage($ana);
        $this->as($ana)->getJson(route('assigned-tasks.unseen'))->assertJsonPath('mentions.count', 0);

        // Editing the task does not notify Ana again; a newly mentioned Head would be (but not the author).
        $this->as($head)->putJson(route('assigned-tasks.update', $taskId), [
            'title' => 'Check the R4-01',
            'body' => '@AnaTester please start (edited) @HeadTester',
            'members' => [['id' => $ana->id, 'can_comment' => true, 'can_complete' => true]],
        ])->assertOk();
        $this->as($ana)->getJson(route('assigned-tasks.unseen'))->assertJsonPath('mentions.count', 0);
        $this->assertSame(0, AssignedTaskMention::query()->where('user_id', $head->id)->whereNull('comment_id')->count());
    }

    public function test_mentions_also_work_with_a_full_name_and_the_longest_name_wins(): void
    {
        $head = $this->makeUser('Head Secretary');
        $ana = $this->makeUser('Secretary', 'Ana');                                  // "Ana Tester"
        $anaCruz = tap($this->makeUser('Secretary', 'Ana'))->update(['last_name' => 'Tester Cruz']);   // "Ana Tester Cruz"
        $ben = $this->makeUser('Secretary', 'Ben');
        $members = collect([$ana, $anaCruz, $ben])->map(fn (User $user): array => ['id' => $user->id, 'can_comment' => true, 'can_complete' => true])->all();

        // The picker can match on the part of the gmail before "@", so the directory carries it (never the domain).
        $person = collect($this->as($ben)->getJson(route('assigned-tasks.people'))->json())->firstWhere('id', $ana->id);
        $this->assertSame(mb_strtolower(strstr($ana->gmail, '@', true)), $person['email_key']);
        $this->assertStringNotContainsString('@', $person['email_key']);

        // "@Ana Tester Cruz" is Ana Cruz only (not "Ana Tester" + the word "Cruz"); any case and spacing works.
        $taskId = $this->as($head)->postJson(route('assigned-tasks.store'), [
            'title' => 'Sort the minutes',
            'body' => "@ana  tester   cruz please start.\n@Ana Testers is nobody, and neither is @Ana",
            'members' => $members,
        ])->assertCreated()->json('id');
        $mentioned = fn (): array => AssignedTaskMention::query()->orderBy('id')->pluck('user_id')->map(fn ($id): int => (int) $id)->all();
        $this->assertSame([$anaCruz->id], $mentioned());

        // A full name typed in a comment notifies too, and handles still work next to it.
        $this->as($ben)->postJson(route('assigned-tasks.comment', $taskId), ['body' => 'Thanks @Ana Tester, and @HeadTester.'])->assertCreated();
        $this->assertSame([$anaCruz->id, $ana->id, $head->id], $mentioned());
    }

    public function test_a_mention_still_counts_when_a_multibyte_character_sits_far_after_it(): void
    {
        $head = $this->makeUser('Head Secretary');
        $ana = $this->makeUser('Secretary', 'Ana');
        $taskId = $this->as($head)->postJson(route('assigned-tasks.store'), [
            'title' => 'Read the long note',
            'members' => [['id' => $ana->id, 'can_comment' => true, 'can_complete' => true]],
        ])->assertCreated()->json('id');

        // After the "@", "AnaTester " is 10 bytes and 289 a's take it to byte 299, so the 2-byte ñ
        // straddles the 300-byte window the parser looks at; a byte cut would break the UTF-8.
        $body = '@AnaTester '.str_repeat('a', 289).'ñ end';
        $this->assertSame(2, strlen(mb_substr($body, 300, 1)));
        $this->as($head)->postJson(route('assigned-tasks.comment', $taskId), ['body' => $body])->assertCreated();
        $this->assertSame([$ana->id], AssignedTaskMention::query()->pluck('user_id')->map(fn ($id): int => (int) $id)->all());
    }

    /** What the Tasks page does: load the list, then POST the shown ids with the list's as_of. */
    public function test_every_assigned_secretary_marks_their_own_part_before_it_goes_to_the_head(): void
    {
        $head = $this->makeUser('Head Secretary');
        $ana = $this->makeUser('Secretary', 'Ana');
        $ben = $this->makeUser('Secretary', 'Ben');
        $taskId = $this->as($head)->postJson(route('assigned-tasks.store'), [
            'title' => 'Count the Purok 2 forms',
            'due_date' => now('Asia/Manila')->toDateString(),
            'members' => [
                ['id' => $ana->id, 'can_comment' => true, 'can_complete' => true],
                ['id' => $ben->id, 'can_comment' => true, 'can_complete' => true],
            ],
        ])->assertCreated()->assertJsonPath('progress', ['done' => 0, 'total' => 2])->json('id');

        // Ana's part: still open, Ben is still expected; Ana cannot mark twice but can undo.
        $this->as($ana)->postJson(route('assigned-tasks.complete', $taskId))
            ->assertOk()
            ->assertJsonPath('status', 'open')
            ->assertJsonPath('progress', ['done' => 1, 'total' => 2])
            ->assertJsonPath('viewer.my_done', true)
            ->assertJsonPath('viewer.can_complete', false)
            ->assertJsonPath('viewer.can_undo', true);
        $this->as($ana)->postJson(route('assigned-tasks.complete', $taskId))->assertUnprocessable();
        $this->as($ben)->getJson(route('assigned-tasks.index'))
            ->assertJsonPath('tasks.0.viewer.can_complete', true)
            ->assertJsonPath('attention', 1);
        // Due soon now nags only the one still working.
        $this->as($ana)->getJson(route('assigned-tasks.unseen'))->assertJsonCount(0, 'due_soon');
        $this->as($ben)->getJson(route('assigned-tasks.unseen'))->assertJsonCount(1, 'due_soon');
        // The Head could already close it early here (see the early-confirm test); this test lets everyone finish.
        $this->as($head)->getJson(route('assigned-tasks.index'))->assertJsonPath('tasks.0.viewer.can_confirm', true);

        $this->as($ana)->postJson(route('assigned-tasks.undo-complete', $taskId))
            ->assertOk()->assertJsonPath('progress.done', 0)->assertJsonPath('viewer.can_complete', true);
        $this->assertDatabaseMissing('assigned_task_comments', ['task_id' => $taskId, 'kind' => 'done']);
        $this->as($ana)->postJson(route('assigned-tasks.complete', $taskId))->assertOk();

        // Ben finishes last: now it waits for the Head, credited to Ben; undo is no longer possible.
        $this->as($ben)->postJson(route('assigned-tasks.complete', $taskId))
            ->assertOk()
            ->assertJsonPath('status', 'awaiting_confirmation')
            ->assertJsonPath('done_by.id', $ben->id)
            ->assertJsonPath('progress', ['done' => 2, 'total' => 2]);
        $this->as($ana)->postJson(route('assigned-tasks.undo-complete', $taskId))->assertUnprocessable();

        // Reopen clears everyone's mark.
        $this->as($head)->postJson(route('assigned-tasks.reopen', $taskId))
            ->assertOk()->assertJsonPath('status', 'open')->assertJsonPath('progress.done', 0);
    }

    public function test_head_can_confirm_early_once_one_secretary_has_marked_their_part_done(): void
    {
        $head = $this->makeUser('Head Secretary');
        $ana = $this->makeUser('Secretary', 'Ana');
        $ben = $this->makeUser('Secretary', 'Ben');
        $taskId = $this->as($head)->postJson(route('assigned-tasks.store'), [
            'title' => 'Sort the R4-02 copies',
            'members' => [
                ['id' => $ana->id, 'can_comment' => true, 'can_complete' => true],
                ['id' => $ben->id, 'can_comment' => true, 'can_complete' => true],
            ],
        ])->assertCreated()->assertJsonPath('viewer.can_confirm', false)->json('id');

        // Nobody has finished: still cannot confirm.
        $this->as($head)->postJson(route('assigned-tasks.confirm', $taskId))->assertUnprocessable();

        $this->as($ana)->postJson(route('assigned-tasks.complete', $taskId))->assertOk()->assertJsonPath('status', 'open');
        $this->as($head)->getJson(route('assigned-tasks.index'))
            ->assertJsonPath('tasks.0.status', 'open')
            ->assertJsonPath('tasks.0.viewer.can_confirm', true)
            ->assertJsonPath('tasks.0.viewer.can_reopen', false);

        // 1 of 2 done: the Head closes it; Ana is credited.
        $this->as($head)->postJson(route('assigned-tasks.confirm', $taskId))->assertOk();
        $task = AssignedTask::query()->find($taskId);
        $this->assertNotNull($task->confirmed_at);
        $this->assertSame($ana->id, (int) $task->done_by);
        $this->as($ben)->getJson(route('assigned-tasks.index'))->assertJsonCount(0, 'tasks');
        // Only Ana finished, so only Ana is credited.
        $this->as($head)->getJson(route('assigned-tasks.completed'))
            ->assertJsonCount(1, '0.finished_by')->assertJsonPath('0.finished_by.0.id', $ana->id);
    }

    public function test_completed_tasks_credit_every_secretary_who_marked_their_part_done(): void
    {
        $head = $this->makeUser('Head Secretary');
        $ana = $this->makeUser('Secretary', 'Ana');
        $ben = $this->makeUser('Secretary', 'Ben');
        $taskId = $this->as($head)->postJson(route('assigned-tasks.store'), [
            'title' => 'Count the attendance sheets',
            'members' => [
                ['id' => $ana->id, 'can_comment' => true, 'can_complete' => true],
                ['id' => $ben->id, 'can_comment' => true, 'can_complete' => true],
            ],
        ])->assertCreated()->json('id');
        $this->as($ana)->postJson(route('assigned-tasks.complete', $taskId))->assertOk();
        $this->travel(1)->minutes();
        $this->as($ben)->postJson(route('assigned-tasks.complete', $taskId))->assertOk();
        $this->as($head)->postJson(route('assigned-tasks.confirm', $taskId))->assertOk();

        // Both are credited, in the order they finished.
        $this->as($ana)->getJson(route('assigned-tasks.completed'))
            ->assertJsonCount(2, '0.finished_by')
            ->assertJsonPath('0.finished_by.0.id', $ana->id)
            ->assertJsonPath('0.finished_by.1.id', $ben->id);
    }

    public function test_changing_who_must_finish_updates_the_task_status(): void
    {
        $head = $this->makeUser('Head Secretary');
        $ana = $this->makeUser('Secretary', 'Ana');
        $ben = $this->makeUser('Secretary', 'Ben');
        $cara = $this->makeUser('Secretary', 'Cara');
        $member = fn (User $user, bool $complete = true): array => ['id' => $user->id, 'can_comment' => true, 'can_complete' => $complete];
        $taskId = $this->as($head)->postJson(route('assigned-tasks.store'), ['title' => 'File the minutes', 'members' => [$member($ana), $member($ben)]])
            ->assertCreated()->json('id');
        $this->as($ana)->postJson(route('assigned-tasks.complete', $taskId))->assertOk();

        // Ben no longer has to finish it: Ana was the only one left, so it goes to the Head.
        $this->as($head)->putJson(route('assigned-tasks.update', $taskId), ['title' => 'File the minutes', 'members' => [$member($ana), $member($ben, false)]])
            ->assertOk()->assertJsonPath('status', 'awaiting_confirmation')->assertJsonPath('done_by.id', $ana->id);

        // Adding Cara, who must finish too, sends it back to open; Ana keeps her mark.
        $this->as($head)->putJson(route('assigned-tasks.update', $taskId), ['title' => 'File the minutes', 'members' => [$member($ana), $member($ben, false), $member($cara)]])
            ->assertOk()->assertJsonPath('status', 'open')->assertJsonPath('progress', ['done' => 1, 'total' => 2]);
    }

    private function markSeenAsTasksPage(User $user): void
    {
        $list = $this->as($user)->getJson(route('assigned-tasks.index'))->assertOk();
        $this->as($user)->postJson(route('assigned-tasks.seen'), [
            'ids' => collect($list->json('tasks'))->pluck('id')->all(),
            'as_of' => $list->json('as_of'),
        ])->assertOk();
    }

    private function taskWithTwoMembers(): array
    {
        $head = $this->makeUser('Head Secretary');
        $ana = $this->makeUser('Secretary', 'Ana');
        $ben = $this->makeUser('Secretary', 'Ben');
        $taskId = $this->as($head)->postJson(route('assigned-tasks.store'), [
            'title' => 'Prepare the R4-02',
            'members' => [
                ['id' => $ana->id, 'can_comment' => true, 'can_complete' => true],
                ['id' => $ben->id, 'can_comment' => false, 'can_complete' => false],
            ],
        ])->assertCreated()->json('id');

        return [$head, $ana, $ben, $taskId];
    }

    private function as(User $user): self
    {
        return $this->withSession(['login_user' => ['id' => $user->id]]);
    }

    private function makeUser(string $role, string $firstName = 'Head', bool $verified = true): User
    {
        return User::query()->create([
            'gmail' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'first_name' => $firstName,
            'last_name' => 'Tester',
            'tungkulin' => $role,
            'is_verified' => $verified,
        ]);
    }
}
