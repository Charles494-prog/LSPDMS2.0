<?php

namespace Tests\Feature;

use App\Models\SecretaryTodo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecretaryTodoTest extends TestCase
{
    use RefreshDatabase;

    public function test_secretaries_can_create_private_and_shared_tasks_with_server_saved_dates(): void
    {
        $secretary = $this->makeSecretary();

        $private = $this->withSession(['login_user' => ['id' => $secretary->id]])
            ->postJson(route('secretary-todos.store'), [
                'scope' => 'private',
                'title' => '  Review weekly reports  ',
                'deadline' => '2026-10-15',
            ])
            ->assertCreated()
            ->assertJsonPath('scope', 'private')
            ->assertJsonPath('title', 'Review weekly reports')
            ->assertJsonPath('deadline', '2026-10-15')
            ->assertJsonStructure(['created_at', 'updated_at']);

        $shared = $this->withSession(['login_user' => ['id' => $secretary->id]])
            ->postJson(route('secretary-todos.store'), [
                'scope' => 'shared',
                'title' => 'Prepare the monthly meeting',
            ])
            ->assertCreated()
            ->assertJsonPath('scope', 'shared')
            ->assertJsonPath('deadline', null);

        $this->assertDatabaseHas('secretary_todos', [
            'id' => $private->json('id'),
            'user_id' => $secretary->id,
            'scope' => 'private',
            'title' => 'Review weekly reports',
        ]);
        $this->assertSame('2026-10-15', $private->json('deadline'));
        $this->assertDatabaseHas('secretary_todos', [
            'id' => $shared->json('id'),
            'user_id' => $secretary->id,
            'scope' => 'shared',
        ]);
    }

    public function test_private_tasks_are_only_visible_to_their_creator_and_shared_tasks_are_visible_to_secretaries(): void
    {
        $owner = $this->makeSecretary();
        $otherSecretary = $this->makeSecretary();
        $privateTask = $this->makeTask($owner, 'private', 'Personal task');
        $sharedTask = $this->makeTask($owner, 'shared', 'Common task');

        $this->withSession(['login_user' => ['id' => $otherSecretary->id]])
            ->getJson(route('secretary-todos.index', ['scope' => 'private']))
            ->assertOk()
            ->assertExactJson([]);

        $this->withSession(['login_user' => ['id' => $otherSecretary->id]])
            ->getJson(route('secretary-todos.index', ['scope' => 'shared']))
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $sharedTask->id);

        $this->withSession(['login_user' => ['id' => $otherSecretary->id]])
            ->patchJson(route('secretary-todos.update', $privateTask), ['title' => 'Not allowed'])
            ->assertForbidden();
    }

    public function test_secretary_can_autosave_edits_and_complete_tasks_so_they_disappear_from_active_list(): void
    {
        $secretary = $this->makeSecretary();
        $task = $this->makeTask($secretary, 'shared', 'Old task');
        $createdAt = $task->created_at->toDateTimeString();

        $this->withSession(['login_user' => ['id' => $secretary->id]])
            ->patchJson(route('secretary-todos.update', $task), [
                'title' => 'Edited shared task',
                'deadline' => '2026-10-20',
            ])
            ->assertOk()
            ->assertJsonPath('title', 'Edited shared task')
            ->assertJsonPath('deadline', '2026-10-20');

        $this->assertSame($createdAt, $task->fresh()->created_at->toDateTimeString());
        $this->assertNotNull($task->fresh()->updated_at);

        $this->withSession(['login_user' => ['id' => $secretary->id]])
            ->postJson(route('secretary-todos.complete', $task))
            ->assertOk()
            ->assertJsonPath('completed', true);

        $this->assertNotNull($task->fresh()->completed_at);
        $this->withSession(['login_user' => ['id' => $secretary->id]])
            ->getJson(route('secretary-todos.index', ['scope' => 'shared']))
            ->assertOk()
            ->assertExactJson([]);
    }

    public function test_long_note_content_can_be_saved_for_the_separate_editable_detail_view(): void
    {
        $secretary = $this->makeSecretary();
        $content = str_repeat('Long note details. ', 100).'END';

        $this->withSession(['login_user' => ['id' => $secretary->id]])
            ->postJson(route('secretary-todos.store'), [
                'scope' => 'private',
                'title' => $content,
            ])
            ->assertCreated()
            ->assertJsonPath('title', $content);
    }

    public function test_to_do_widget_is_not_embedded_in_the_notes_checker(): void
    {
        $secretary = $this->makeSecretary(['note_checker_allow' => true]);

        $this->withSession(['login_user' => ['id' => $secretary->id]])
            ->get(route('messages.index'))
            ->assertOk()
            ->assertDontSee('Secretary To-Do');

        $this->withSession(['login_user' => ['id' => $secretary->id]])
            ->get(route('note-checker'))
            ->assertOk()
            ->assertDontSee('Secretary To-Do')
            ->assertSee('id="notesApp"', false)
            ->assertSee('Note Checker');
    }

    private function makeSecretary(array $attributes = []): User
    {
        return User::query()->create($attributes + [
            'gmail' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'tungkulin' => 'Secretary',
            'is_verified' => true,
        ]);
    }

    private function makeTask(User $user, string $scope, string $title): SecretaryTodo
    {
        return SecretaryTodo::query()->create([
            'user_id' => $user->id,
            'scope' => $scope,
            'title' => $title,
        ]);
    }
}
