<?php

namespace Database\Factories;

use App\Enums\WebhookEventStatus;
use App\Models\WebhookEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WebhookEvent>
 */
class WebhookEventFactory extends Factory
{
    protected $model = WebhookEvent::class;

    public function definition(): array
    {
        return [
            'source' => 'wa-gateway',
            'event_type' => fake()->randomElement([
                'message.incoming',
                'message.ack',
                'connection.status',
                'qr.updated',
            ]),
            'event_id' => Str::uuid()->toString(),
            'payload' => ['sample' => true],
            'received_at' => now(),
            'processed_at' => null,
            'status' => WebhookEventStatus::Received,
            'error' => null,
        ];
    }
}
