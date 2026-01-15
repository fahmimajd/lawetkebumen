<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConversationUpdated implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $conversationId,
        public string $status,
        public ?int $assignedTo,
        public ?string $assignedName,
        public ?string $lastMessageAt,
        public int $unreadCount,
        public ?string $lastMessagePreview,
        public ?string $lastMessageDirection
    ) {
    }

    public function broadcastOn(): Channel
    {
        return new Channel('conversations');
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->conversationId,
            'status' => $this->status,
            'assigned_to' => $this->assignedTo,
            'assigned_name' => $this->assignedName,
            'last_message_at' => $this->lastMessageAt,
            'unread_count' => $this->unreadCount,
            'last_message_preview' => $this->lastMessagePreview,
            'last_message_direction' => $this->lastMessageDirection,
        ];
    }
}
