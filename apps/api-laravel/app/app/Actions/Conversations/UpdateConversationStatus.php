<?php

namespace App\Actions\Conversations;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use App\Support\BroadcasterAfterCommit;

class UpdateConversationStatus
{
    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function handle(User $user, Conversation $conversation, array $payload): array
    {
        if (! $this->canAccess($user, $conversation)) {
            throw new AuthorizationException('Not allowed to update this conversation.');
        }

        $conversation->status = $payload['status'];
        if ($payload['status'] === 'closed') {
            $conversation->closed_at = now();
        } else {
            $conversation->closed_at = null;
        }
        $conversation->save();

        BroadcasterAfterCommit::conversationUpdated($conversation);

        return [
            'status' => 'updated',
            'conversation_status' => $conversation->status,
        ];
    }

    private function canAccess(User $user, Conversation $conversation): bool
    {
        return $user->can('update', $conversation);
    }
}
