<?php

namespace App\Actions\Messages;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\CursorPaginator;
use App\Support\MessagePayload;

class ListConversationMessages
{
    /**
     * @return array<string, mixed>
     */
    public function handle(User $user, Conversation $conversation, $request): array
    {
        if (! $this->canAccess($user, $conversation)) {
            throw new AuthorizationException('Not allowed to view messages.');
        }

        $limit = (int) ($request->input('limit') ?? 30);
        $cursor = $request->input('cursor');

        $paginator = $this->paginate($conversation, $limit, $cursor);

        return [
            'data' => collect($paginator->items())
                ->map(fn (Message $message) => MessagePayload::from($message))
                ->all(),
            'next_cursor' => $paginator->nextCursor() ? $paginator->nextCursor()->encode() : null,
        ];
    }

    private function paginate(Conversation $conversation, int $limit, ?string $cursor): CursorPaginator
    {
        return $conversation->messages()
            ->with(['sender', 'replyTo', 'replyTo.sender'])
            ->orderByDesc('wa_timestamp')
            ->orderByDesc('id')
            ->cursorPaginate($limit, ['*'], 'cursor', $cursor);
    }

    private function canAccess(User $user, Conversation $conversation): bool
    {
        return $user->can('view', $conversation);
    }
}
