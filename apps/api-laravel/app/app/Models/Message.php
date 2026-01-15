<?php

namespace App\Models;

use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Enums\MessageType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'conversation_id',
        'user_id',
        'direction',
        'type',
        'body',
        'wa_message_id',
        'client_message_id',
        'inbound_fingerprint',
        'wa_timestamp',
        'status',
        'error_code',
        'error_message',
        'media_mime',
        'media_size',
        'media_url',
        'storage_path',
        'sender_wa_id',
        'sender_name',
        'sender_phone',
        'reply_to_message_id',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'direction' => MessageDirection::class,
        'type' => MessageType::class,
        'status' => MessageStatus::class,
        'wa_timestamp' => 'datetime',
        'media_size' => 'integer',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'reply_to_message_id');
    }
}
