<?php

namespace App\Actions\Conversations;

use App\Models\Conversation;
use App\Models\Queue;
use App\Models\User;
use App\Support\BroadcasterAfterCommit;
use Illuminate\Auth\Access\AuthorizationException;

class TransferConversation
{
    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function handle(User $user, Conversation $conversation, array $payload): array
    {
        $assignedToId = array_key_exists('assigned_to', $payload)
            ? ($payload['assigned_to'] !== null ? (int) $payload['assigned_to'] : null)
            : null;
        $queueId = array_key_exists('queue_id', $payload)
            ? ($payload['queue_id'] !== null ? (int) $payload['queue_id'] : null)
            : null;

        if (! $user->can('transfer', [$conversation, $assignedToId, $queueId])) {
            throw new AuthorizationException('Not allowed to transfer this conversation.');
        }

        if (array_key_exists('assigned_to', $payload)) {
            $conversation->assigned_to = $assignedToId;
            $conversation->assigned_at = $assignedToId ? now() : null;
        }

        if ($queueId !== null) {
            $queueExists = Queue::query()->whereKey($queueId)->exists();
            if (! $queueExists) {
                throw new AuthorizationException('Queue not found.');
            }
            $conversation->queue_id = $queueId;
        }

        $conversation->save();

        BroadcasterAfterCommit::conversationUpdated($conversation);

        return [
            'status' => 'transferred',
            'assigned_to' => $conversation->assigned_to,
            'assigned_name' => $conversation->assignee?->name,
            'queue_id' => $conversation->queue_id,
        ];
    }
}
