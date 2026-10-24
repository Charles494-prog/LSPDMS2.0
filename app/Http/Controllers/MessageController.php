<?php

namespace App\Http\Controllers;

use App\Events\MessageReceiptUpdated;
use App\Events\MessageSent;
use App\Events\UserTyping;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\FileStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;
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
        $now = now();
        $conversations = Conversation::query()
            ->where(fn ($query) => $query->where('user_one_id', $user->id)->orWhere('user_two_id', $user->id))
            ->with(['userOne', 'userTwo'])
            ->withMax(['messages as last_message_created_at' => fn ($query) => $query->where('expires_at', '>', $now)], 'created_at')
            ->withCount(['messages as unread_count' => fn ($query) => $query->where('sender_id', '!=', $user->id)
                ->whereNull('read_at')->where('expires_at', '>', $now)])
            ->orderByDesc('last_message_created_at')
            ->get();

        // One query for every conversation's latest message (and one for their senders), not one per row.
        $lastMessages = $conversations->isEmpty() ? collect() : Message::query()
            ->whereIn('id', Message::query()->selectRaw('MAX(id)')
                ->whereIn('conversation_id', $conversations->modelKeys())
                ->where('expires_at', '>', $now)
                ->groupBy('conversation_id'))
            ->with('sender')
            ->get()
            ->keyBy('conversation_id');

        return response()->json($conversations->map(function (Conversation $conversation) use ($user, $lastMessages): array {
            $lastMessage = $lastMessages->get($conversation->id);

            return [
                'id' => $conversation->id,
                'user' => $this->userPayload($conversation->otherParticipant((int) $user->id)),
                'last_message' => $lastMessage ? $this->messagePayload($lastMessage) : null,
                'unread_count' => (int) $conversation->unread_count,
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
        $this->authorizeParticipant($request, $conversation);
        $messages = $conversation->messages()->where('expires_at', '>', now())->with('sender')->oldest()->get();

        return response()->json($messages->map(fn (Message $message): array => $this->messagePayload($message)));
    }

    // POST (CSRF-protected): reading the list is a GET, so marking messages read lives here instead.
    public function markConversationRead(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $this->authorizeParticipant($request, $conversation);
        $validated = $request->validate(['up_to_id' => ['nullable', 'integer', 'min:1']]);

        // up_to_id is the newest message the client rendered, so a message that arrives
        // between loading the list and this call is not marked read unseen.
        $unreadIds = $conversation->messages()->where('sender_id', '!=', $user->id)
            ->whereNull('read_at')->where('expires_at', '>', now())
            ->when(isset($validated['up_to_id']), fn ($query) => $query->where('id', '<=', $validated['up_to_id']))
            ->pluck('id');
        if ($unreadIds->isEmpty()) {
            return response()->json(['read_ids' => []]);
        }

        // Bound timestamps instead of SQL NOW(), so this works on every database driver.
        $now = now();
        DB::transaction(function () use ($unreadIds, $now): void {
            Message::query()->whereKey($unreadIds)->whereNull('delivered_at')
                ->update(['delivered_at' => $now]);
            Message::query()->whereKey($unreadIds)->update([
                'read_at' => $now,
                'updated_at' => $now,
            ]);
        });

        if (config('broadcasting.default') === 'reverb') {
            foreach (Message::query()->whereKey($unreadIds)->get() as $message) {
                $this->broadcastSafely(new MessageReceiptUpdated($message));
            }
        }

        return response()->json(['read_ids' => $unreadIds->map(fn ($id): int => (int) $id)->values()]);
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
                    // Kept on the local disk and in stored_files so SQL exports carry the image.
                    try {
                        $path = app(FileStore::class)->putUploadedFile('local', 'messages/'.$conversation->id, $attachment);
                    } catch (RuntimeException) {
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
                app(FileStore::class)->delete('local', $storedPath);
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

        $response = app(FileStore::class)->response(
            'local',
            $message->attachment_path,
            $message->attachment_name ?? 'attachment',
            $message->attachment_mime,
            'inline',
        );
        if ($response === null) {
            $notice = 'This image is no longer available on this server.';

            return $request->expectsJson()
                ? response()->json(['message' => $notice], 404)
                : response($notice, 404, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        return $response;
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

        // Served by ProfilePictureController, which falls back to the database copy.
        $avatarUrl = $user->is_verified ? ProfilePictureController::urlFor($user->id, $user->profile_picture) : null;

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