<?php

namespace App\Actions\Conversations;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use App\Support\BroadcasterAfterCommit;

class AssignConversation
{
    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function handle(User $user, Conversation $conversation, array $payload): array
    {
        $assignedToInput = (string) ($payload['assigned_to'] ?? '');
        $assignedToId = $assignedToInput === 'me' ? $user->id : (int) $assignedToInput;

        if ($assignedToId <= 0) {
            throw new AuthorizationException('Invalid assignee.');
        }

        if (! $user->can('assign', [$conversation, $assignedToId])) {
            throw new AuthorizationException('Agents may only self-assign.');
        }

        if (! User::whereKey($assignedToId)->exists()) {
            throw new AuthorizationException('Assignee not found.');
        }

        $conversation->assigned_to = $assignedToId;
        $conversation->assigned_at = now();
        $conversation->save();

        BroadcasterAfterCommit::conversationUpdated($conversation);

        return [
            'status' => 'assigned',
            'assigned_to' => $conversation->assigned_to,
        ];
    }
}
