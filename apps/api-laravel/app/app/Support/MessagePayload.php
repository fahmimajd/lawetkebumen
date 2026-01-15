<?php

namespace App\Support;

use App\Models\Message;
use BackedEnum;

class MessagePayload
{
    /**
     * @return array<string, mixed>
     */
    public static function from(Message $message): array
    {
        $direction = $message->direction instanceof BackedEnum
            ? $message->direction->value
            : $message->direction;
        $type = $message->type instanceof BackedEnum ? $message->type->value : $message->type;
        $status = $message->status instanceof BackedEnum
            ? $message->status->value
            : $message->status;
        $mediaUrl = $message->media_url;
        if (! $mediaUrl && $message->storage_path) {
            $mediaUrl = asset('storage/'.$message->storage_path);
        }
        $mediaName = $message->storage_path
            ? basename($message->storage_path)
            : ($mediaUrl ? basename(parse_url($mediaUrl, PHP_URL_PATH) ?? '') : null);
        $senderName = $message->sender_name ?: $message->sender?->name;
        $replyTo = $message->replyTo;
        $replySenderName = $replyTo?->sender_name ?: $replyTo?->sender?->name;
        $replyType = $replyTo?->type instanceof BackedEnum ? $replyTo->type->value : $replyTo?->type;
        $replyBody = $replyTo?->body ?: ($replyType ? '['.$replyType.']' : null);

        return [
            'id' => $message->id,
            'client_message_id' => $message->client_message_id,
            'conversation_id' => $message->conversation_id,
            'direction' => $direction,
            'type' => $type,
            'body' => $message->body,
            'media_url' => $mediaUrl,
            'media_mime' => $message->media_mime,
            'media_size' => $message->media_size,
            'media_name' => $mediaName,
            'sender_name' => $senderName,
            'sender_wa_id' => $message->sender_wa_id,
            'sender_phone' => $message->sender_phone,
            'reply_to_message_id' => $message->reply_to_message_id,
            'reply_to' => $replyTo ? [
                'id' => $replyTo->id,
                'body' => $replyBody,
                'type' => $replyType,
                'sender_name' => $replySenderName,
                'sender_wa_id' => $replyTo->sender_wa_id,
            ] : null,
            'status' => $status,
            'wa_timestamp' => optional($message->wa_timestamp)->toISOString(),
            'created_at' => optional($message->created_at)->toISOString(),
        ];
    }
}
