<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Queue\SerializesModels;

class MessageStatusUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $conversationId,
        public int $messageId,
        public string $status,
        public ?string $waTimestamp,
        public ?int $actorId = null
    ) {
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('conversation.'.$this->conversationId);
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'message_id' => $this->messageId,
            'status' => $this->status,
            'wa_timestamp' => $this->waTimestamp,
            'actor_id' => $this->actorId,
        ];
    }
}
