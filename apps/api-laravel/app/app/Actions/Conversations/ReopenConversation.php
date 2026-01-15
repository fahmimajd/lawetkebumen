<?php

namespace App\Actions\Conversations;

use App\Enums\ConversationStatus;
use App\Models\Conversation;
use App\Models\User;
use App\Support\BroadcasterAfterCommit;
use Illuminate\Auth\Access\AuthorizationException;

class ReopenConversation
{
    /**
     * @return array<string, mixed>
     */
    public function handle(User $user, Conversation $conversation): array
    {
        if (! $user->can('reopen', $conversation)) {
            throw new AuthorizationException('Not allowed to reopen this conversation.');
        }

        $conversation->status = ConversationStatus::Open;
        $conversation->closed_at = null;
        $conversation->reopened_at = now();
        $conversation->save();

        BroadcasterAfterCommit::conversationUpdated($conversation);

        return [
            'status' => 'reopened',
        ];
    }
}
