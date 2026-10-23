<?php

namespace App\Http\Controllers;

use App\Events\MessageReceiptUpdated;
use App\Events\MessageSent;
use App\Events\UserTyping;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class MessageController extends Controller
{
    private function currentUser(Request $request): User
    {
        return User::query()->whereKey($request->session()->get('login_user.id'))
            ->where('is_verified', true)->firstOrFail();
    }

    public function index(): mixed
    {
        return view('messages.index', [
            'reverbConfigured' => config('broadcasting.default') === 'reverb'
                && filled(config('broadcasting.connections.reverb.key'))
                && filled(config('broadcasting.connections.reverb.secret'))
                && filled(config('broadcasting.connections.reverb.app_id')),
        ]);
    }

    public function accounts(Request $request): JsonResponse
    {
        $user = $this->currentUser($request);
        $validated = $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $term = trim((string) ($validated['q'] ?? ''));
        $searchTerms = preg_split('/\s+/', $term, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $accounts = User::query()->whereKeyNot($user->getKey())
            ->when($searchTerms !== [], function ($query) use ($searchTerms): void {
                foreach ($searchTerms as $searchTerm) {
                    $query->where(function ($query) use ($searchTerm): void {
                        $pattern = '%'.$searchTerm.'%';
                        $query->where('first_name', 'like', $pattern)
                            ->orWhere('last_name', 'like', $pattern)
                            ->orWhere('gmail', 'like', $pattern)
                            ->orWhere('tungkulin', 'like', $pattern);
                    });
                }
            })
            ->orderBy('first_name')->orderBy('last_name')
            ->limit(50)
            ->get(['id', 'first_name', 'last_name', 'gmail', 'tungkulin', 'is_verified', 'profile_picture']);

        return response()->json($accounts->map(fn (User $account): array => $this->userPayload($account)));
    }

    public function conversations(Request $request): JsonResponse
    {
        $user = $this->currentUser($request);
        $conversations = Conversation::query()
            ->where(fn ($query) => $query->where('user_one_id', $user->id)->orWhere('user_two_id', $user->id))
            ->with(['userOne', 'userTwo'])
            ->withMax(['messages as last_message_created_at' => fn ($query) => $query->where('expires_at', '>', now())], 'created_at')
            ->orderByDesc('last_message_created_at')
            ->get();

        return response()->json($conversations->map(function (Conversation $conversation) use ($user): array {
            $lastMessage = $conversation->messages()->where('expires_at', '>', now())->latest('id')->first();

            return [
                'id' => $conversation->id,
                'user' => $this->userPayload($conversation->otherParticipant((int) $user->id)),
                'last_message' => $lastMessage ? $this->messagePayload($lastMessage) : null,
                'unread_count' => $conversation->messages()->where('sender_id', '!=', $user->id)
                    ->whereNull('read_at')->where('expires_at', '>', now())->count(),
            ];
        }));
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $user = $this->currentUser($request);
        $unreadCount = Message::query()
            ->where('sender_id', '!=', $user->id)
            ->whereNull('read_at')
            ->where('expires_at', '>', now())
            ->whereHas('conversation', fn ($query) => $query
                ->where('user_one_id', $user->id)
                ->orWhere('user_two_id', $user->id))
            ->count();

        return response()->json(['unread_count' => $unreadCount]);
    }

    public function createConversation(Request $request): JsonResponse
    {
        $validated = $request->validate(['target_user_id' => ['required', 'integer']]);
        $user = $this->currentUser($request);
        $target = User::query()->whereKey($validated['target_user_id'])->firstOrFail();

        abort_if((int) $user->id === (int) $target->id, 422, 'You cannot message yourself.');
        abort_unless($target->is_verified, 403, "Can't message this user, this account has been suspended.");

        [$one, $two] = [(int) min($user->id, $target->id), (int) max($user->id, $target->id)];
        $conversation = Conversation::query()->firstOrCreate(
            ['user_one_id' => $one, 'user_two_id' => $two],
        );

        return response()->json([
            'id' => $conversation->id,
            'user' => $this->userPayload($target),
        ], 201);
    }

    public function messages(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $this->authorizeParticipant($request, $conversation);
        $messages = $conversation->messages()->where('expires_at', '>', now())->with('sender')->oldest()->get();

        $unread = $conversation->messages()->where('sender_id', '!=', $user->id)
            ->whereNull('read_at')->where('expires_at', '>', now());
        $unreadIds = $unread->pluck('id');
        $unread->update([
            'delivered_at' => DB::raw('COALESCE(delivered_at, NOW())'),
            'read_at' => now(),
            'updated_at' => now(),
        ]);

        if ($unreadIds->isNotEmpty()) {
            foreach ($messages->whereIn('id', $unreadIds) as $message) {
                $message->refresh();
                $this->broadcastSafely(new MessageReceiptUpdated($message));
            }
        }

        return response()->json($messages->map(fn (Message $message): array => $this->messagePayload($message)));
    }

    public function send(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $this->authorizeParticipant($request, $conversation);
        $validated = $request->validate([
            'body' => ['nullable', 'string', 'max:5000'],
            'attachment' => ['nullable', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:10240'],
        ]);
        $body = trim((string) ($validated['body'] ?? ''));
        $file = $request->file('attachment');
        $attachment = $file instanceof UploadedFile ? $file : null;

        if ($body === '' && ! $attachment) {
            throw ValidationException::withMessages(['body' => 'Add a message or choose an image to send.']);
        }

        $storedPath = null;

        try {
            $message = DB::transaction(function () use ($conversation, $user, $body, $attachment, &$storedPath): Message {
                $message = $conversation->messages()->create([
                    'sender_id' => $user->id,
                    'body' => $body !== '' ? $body : null,
                    'expires_at' => now()->addYear(),
                ]);

                if ($attachment) {
                    $path = $attachment->store('messages/'.$conversation->id, 'local');
                    if ($path === false) {
                        throw ValidationException::withMessages(['attachment' => 'The image could not be saved. Please try again.']);
                    }
                    $storedPath = $path;

                    $message->forceFill([
                        'attachment_path' => $path,
                        'attachment_name' => $attachment->getClientOriginalName(),
                        'attachment_mime' => $attachment->getMimeType(),
                        'attachment_size' => $attachment->getSize(),
                    ])->save();
                }

                return $message->load('sender');
            });
        } catch (Throwable $exception) {
            if ($storedPath !== null) {
                Storage::disk('local')->delete($storedPath);
            }
            throw $exception;
        }

        $this->broadcastSafely(new MessageSent($message));

        return response()->json($this->messagePayload($message), 201);
    }

    public function delivered(Request $request, Message $message): JsonResponse
    {
        $user = $this->authorizeMessageParticipant($request, $message);
        abort_if((int) $message->sender_id === (int) $user->id, 404);

        if (! $message->delivered_at) {
            $message->forceFill(['delivered_at' => now()])->save();
            $this->broadcastSafely(new MessageReceiptUpdated($message));
        }

        return response()->json(['delivered_at' => $message->delivered_at]);
    }

    public function read(Request $request, Message $message): JsonResponse
    {
        $user = $this->authorizeMessageParticipant($request, $message);
        abort_if((int) $message->sender_id === (int) $user->id, 404);

        if (! $message->read_at) {
            $message->forceFill(['delivered_at' => $message->delivered_at ?? now(), 'read_at' => now()])->save();
            $this->broadcastSafely(new MessageReceiptUpdated($message));
        }

        return response()->json(['read_at' => $message->read_at]);
    }

    public function attachment(Request $request, Message $message): mixed
    {
        $this->authorizeMessageParticipant($request, $message);
        abort_if(! $message->attachment_path, 404);
        abort_unless(Storage::disk('local')->exists($message->attachment_path), 404);

        return Storage::disk('local')->response(
            $message->attachment_path,
            $message->attachment_name ?? 'attachment',
            ['Content-Type' => $message->attachment_mime ?? 'application/octet-stream'],
            'inline',
        );
    }

    public function typing(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $this->authorizeParticipant($request, $conversation);
        $name = trim($user->full_name) ?: $user->gmail;
        $this->broadcastSafely(new UserTyping((int) $conversation->id, (int) $user->id, $name));

        return response()->json(['ok' => true]);
    }

    private function authorizeParticipant(Request $request, Conversation $conversation): User
    {
        $user = $this->currentUser($request);
        abort_unless($conversation->includes((int) $user->id), 404);
        $otherParticipantId = (int) $conversation->user_one_id === (int) $user->id
            ? $conversation->user_two_id
            : $conversation->user_one_id;
        abort_unless(User::query()->whereKey($otherParticipantId)->where('is_verified', true)->exists(), 403, "Can't message this user, this account has been suspended.");

        return $user;
    }

    private function broadcastSafely(object $event): void
    {
        if (config('broadcasting.default') !== 'reverb') {
            return;
        }

        try {
            broadcast($event);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function authorizeMessageParticipant(Request $request, Message $message): User
    {
        abort_if($message->expires_at->isPast(), 404);

        return $this->authorizeParticipant($request, $message->conversation);
    }

    private function userPayload(User $user): array
    {
        $name = trim($user->full_name);

        $avatarUrl = null;
        if ($user->profile_picture) {
            $segments = array_map('rawurlencode', explode('/', ltrim((string) $user->profile_picture, '/')));
            $avatarUrl = asset('storage/'.implode('/', $segments));
        }

        return [
            'id' => $user->id,
            'name' => $name !== '' ? $name : $user->gmail,
            'email' => $user->gmail,
            'role' => $user->tungkulin,
            'can_message' => (bool) $user->is_verified,
            'status' => $user->is_verified ? 'Active' : 'Suspended',
            'avatar' => $user->profile_picture,
            'avatar_url' => $avatarUrl,
        ];
    }

    private function messagePayload(Message $message): array
    {
        return [
            'id' => $message->id,
            'conversation_id' => $message->conversation_id,
            'sender_id' => $message->sender_id,
            'sender_name' => $message->sender?->full_name,
            'body' => $message->body,
            'attachment_url' => $message->attachment_path ? route('messages.attachment', $message) : null,
            'attachment_name' => $message->attachment_name,
            'created_at' => $message->created_at?->toISOString(),
            'delivered_at' => $message->delivered_at?->toISOString(),
            'read_at' => $message->read_at?->toISOString(),
        ];
    }
}