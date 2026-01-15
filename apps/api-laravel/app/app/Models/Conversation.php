<?php

namespace App\Models;

use App\Enums\ConversationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'contact_id',
        'status',
        'assigned_to',
        'assigned_at',
        'closed_at',
        'closed_by',
        'reopened_at',
        'queue_id',
        'last_message_at',
        'unread_count',
        'last_message_preview',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'status' => ConversationStatus::class,
        'assigned_at' => 'datetime',
        'closed_at' => 'datetime',
        'reopened_at' => 'datetime',
        'last_message_at' => 'datetime',
        'unread_count' => 'integer',
    ];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function queue(): BelongsTo
    {
        return $this->belongsTo(Queue::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }
}
