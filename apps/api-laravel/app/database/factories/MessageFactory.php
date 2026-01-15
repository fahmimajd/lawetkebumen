<?php

namespace Database\Factories;

use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Enums\MessageType;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Message>
 */
class MessageFactory extends Factory
{
    protected $model = Message::class;

    public function definition(): array
    {
        return [
            'conversation_id' => Conversation::factory(),
            'user_id' => null,
            'direction' => fake()->randomElement([MessageDirection::Inbound, MessageDirection::Outbound]),
            'type' => fake()->randomElement([
                MessageType::Text,
                MessageType::Image,
                MessageType::Video,
                MessageType::Audio,
                MessageType::Document,
                MessageType::Sticker,
            ]),
            'body' => fake()->optional()->sentence(),
            'wa_message_id' => Str::uuid()->toString(),
            'client_message_id' => Str::uuid()->toString(),
            'inbound_fingerprint' => fake()->optional()->regexify('[a-f0-9]{64}'),
            'wa_timestamp' => now(),
            'status' => MessageStatus::Pending,
            'error_code' => null,
            'error_message' => null,
            'media_mime' => null,
            'media_size' => null,
            'media_url' => null,
            'storage_path' => null,
        ];
    }
}
