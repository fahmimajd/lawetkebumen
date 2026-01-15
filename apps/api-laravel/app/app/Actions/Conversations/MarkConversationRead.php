<?php

namespace App\Actions\Conversations;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use App\Support\BroadcasterAfterCommit;

class MarkConversationRead
{
    /**
     * @return array<string, mixed>
     */
    public function handle(User $user, Conversation $conversation): array
    {
        if (! $this->canAccess($user, $conversation)) {
            throw new AuthorizationException('Not allowed to read this conversation.');
        }

        $conversation->unread_count = 0;
        $conversation->save();

        BroadcasterAfterCommit::conversationUpdated($conversation);

        return [
            'status' => 'read',
            'unread_count' => $conversation->unread_count,
        ];
    }

    private function canAccess(User $user, Conversation $conversation): bool
    {
        return $user->can('read', $conversation);
    }
}
