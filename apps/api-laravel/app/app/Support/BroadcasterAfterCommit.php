<?php

namespace App\Support;

use App\Events\ConversationUpdated;
use App\Events\MessageCreated;
use App\Events\MessageStatusUpdated;
use App\Models\Conversation;
use App\Models\Message;
use App\Support\MessagePayload;
use BackedEnum;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;

class BroadcasterAfterCommit
{
    public static function conversationUpdated(Conversation $conversation): void
    {
        $conversation->loadMissing('assignee');

        $status = $conversation->status instanceof BackedEnum
            ? $conversation->status->value
            : (string) $conversation->status;
        $lastDirection = Message::query()
            ->where('conversation_id', $conversation->id)
            ->orderByDesc('wa_timestamp')
            ->orderByDesc('id')
            ->value('direction');
        if ($lastDirection instanceof BackedEnum) {
            $lastDirection = $lastDirection->value;
        }

        $payload = [
            'id' => $conversation->id,
            'status' => $status,
            'assigned_to' => $conversation->assigned_to,
            'assigned_name' => $conversation->assignee?->name,
            'last_message_at' => optional($conversation->last_message_at)->toISOString(),
            'unread_count' => (int) $conversation->unread_count,
            'last_message_preview' => $conversation->last_message_preview,
            'last_message_direction' => $lastDirection,
        ];

        DB::afterCommit(function () use ($payload) {
            event(new ConversationUpdated(
                $payload['id'],
                $payload['status'],
                $payload['assigned_to'],
                $payload['assigned_name'],
                $payload['last_message_at'],
                $payload['unread_count'],
                $payload['last_message_preview'],
                $payload['last_message_direction']
            ));
        });
    }

    public static function messageCreated(
        Message $message,
        ?int $actorId = null,
        bool $toOthers = false,
        ?int $delayMs = null
    ): void
    {
        $message->loadMissing(['sender', 'replyTo', 'replyTo.sender']);
        $payload = MessagePayload::from($message);

        DB::afterCommit(function () use ($message, $payload, $actorId, $toOthers, $delayMs) {
            $event = new MessageCreated($message->conversation_id, $payload, $actorId);
            self::dispatchBroadcast($event, $toOthers, $delayMs);
        });
    }

    public static function messageStatusUpdated(
        Message $message,
        ?int $actorId = null,
        bool $toOthers = false,
        ?int $delayMs = null
    ): void
    {
        $status = $message->status instanceof BackedEnum
            ? $message->status->value
            : (string) $message->status;

        $payload = [
            'message_id' => $message->id,
            'status' => $status,
            'wa_timestamp' => optional($message->wa_timestamp)->toISOString(),
        ];

        DB::afterCommit(function () use ($message, $payload, $actorId, $toOthers, $delayMs) {
            $event = new MessageStatusUpdated(
                $message->conversation_id,
                $payload['message_id'],
                $payload['status'],
                $payload['wa_timestamp'],
                $actorId
            );
            self::dispatchBroadcast($event, $toOthers, $delayMs);
        });
    }

    private static function dispatchBroadcast(object $event, bool $toOthers, ?int $delayMs): void
    {
        if ($toOthers) {
            $socketId = Broadcast::socket();
            if ($socketId) {
                $event->socket = $socketId;
            }
        }

        if ($delayMs && $delayMs > 0) {
            dispatch(function () use ($event) {
                broadcast($event);
            })->delay(now()->addMilliseconds($delayMs));
            return;
        }

        $pending = broadcast($event);
        if ($toOthers) {
            $pending->toOthers();
        }
    }
}
