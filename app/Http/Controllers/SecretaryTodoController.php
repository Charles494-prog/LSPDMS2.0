<?php

namespace App\Http\Controllers;

use App\Models\SecretaryTodo;
use App\Models\User;
use App\Support\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SecretaryTodoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'scope' => ['required', Rule::in(['private', 'shared'])],
        ]);

        $todos = SecretaryTodo::query()
            ->where('scope', $validated['scope'])
            ->whereNull('completed_at')
            ->when($validated['scope'] === 'private', fn ($query) => $query->where('user_id', $this->currentUser($request)->id))
            ->orderByRaw('deadline IS NULL')
            ->orderBy('deadline')
            ->orderByDesc('created_at')
            ->get();

        return response()->json($todos->map(fn (SecretaryTodo $todo): array => $this->payload($todo)));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'scope' => ['required', Rule::in(['private', 'shared'])],
            'title' => ['required', 'string', 'max:10000'],
            'deadline' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $title = trim($validated['title']);
        abort_if($title === '', 422, 'Enter a task before adding it.');

        $todo = SecretaryTodo::query()->create([
            'user_id' => $this->currentUser($request)->id,
            'scope' => $validated['scope'],
            'title' => $title,
            'deadline' => $validated['deadline'] ?? null,
        ]);

        return response()->json($this->payload($todo), 201);
    }

    public function update(Request $request, SecretaryTodo $todo): JsonResponse
    {
        $user = $this->currentUser($request);
        $this->authorizeTodo($todo, $user);
        abort_if($todo->completed_at !== null, 404);

        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:10000'],
            'deadline' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
        ]);

        if (array_key_exists('title', $validated)) {
            $validated['title'] = trim($validated['title']);
            abort_if($validated['title'] === '', 422, 'A task cannot be blank.');
        }

        $todo->fill($validated)->save();

        return response()->json($this->payload($todo->refresh()));
    }

    public function complete(Request $request, SecretaryTodo $todo): JsonResponse
    {
        $this->authorizeTodo($todo, $this->currentUser($request));
        abort_if($todo->completed_at !== null, 404);
        $todo->forceFill(['completed_at' => now()])->save();

        return response()->json(['completed' => true]);
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

    private function authorizeTodo(SecretaryTodo $todo, User $user): void
    {
        abort_if($todo->scope === 'private' && (int) $todo->user_id !== (int) $user->id, 403);
    }

    private function payload(SecretaryTodo $todo): array
    {
        return [
            'id' => $todo->id,
            'scope' => $todo->scope,
            'title' => $todo->title,
            'deadline' => $todo->deadline?->format('Y-m-d'),
            'created_at' => $todo->created_at?->toIso8601String(),
            'updated_at' => $todo->updated_at?->toIso8601String(),
        ];
    }
}
