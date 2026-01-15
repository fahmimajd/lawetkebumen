<?php

namespace App\Actions\Conversations;

use App\Enums\ConversationStatus;
use App\Models\Conversation;
use App\Models\User;
use App\Support\BroadcasterAfterCommit;
use Illuminate\Auth\Access\AuthorizationException;

class CloseConversation
{
    /**
     * @return array<string, mixed>
     */
    public function handle(User $user, Conversation $conversation): array
    {
        if (! $user->can('close', $conversation)) {
            throw new AuthorizationException('Not allowed to close this conversation.');
        }

        $conversation->status = ConversationStatus::Closed;
        $conversation->closed_at = now();
        $conversation->closed_by = $user->id;
        $conversation->save();

        BroadcasterAfterCommit::conversationUpdated($conversation);

        return [
            'status' => 'closed',
            'closed_at' => optional($conversation->closed_at)->toISOString(),
        ];
    }
}
