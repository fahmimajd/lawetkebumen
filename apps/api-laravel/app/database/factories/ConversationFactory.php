<?php

namespace Database\Factories;

use App\Enums\ConversationStatus;
use App\Models\Contact;
use App\Models\Conversation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Conversation>
 */
class ConversationFactory extends Factory
{
    protected $model = Conversation::class;

    public function definition(): array
    {
        return [
            'contact_id' => Contact::factory(),
            'status' => ConversationStatus::Open,
            'assigned_to' => null,
            'assigned_at' => null,
            'closed_at' => null,
            'closed_by' => null,
            'reopened_at' => null,
            'queue_id' => null,
            'last_message_at' => now(),
            'unread_count' => 0,
            'last_message_preview' => fake()->optional()->sentence(),
        ];
    }
}
