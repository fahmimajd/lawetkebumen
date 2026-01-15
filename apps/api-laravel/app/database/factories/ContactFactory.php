<?php

namespace Database\Factories;

use App\Models\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Contact>
 */
class ContactFactory extends Factory
{
    protected $model = Contact::class;

    public function definition(): array
    {
        $phone = '62'.fake()->unique()->numerify('812#######');

        return [
            'wa_id' => $phone.'@s.whatsapp.net',
            'phone' => $phone,
            'display_name' => fake()->optional()->name(),
            'avatar_url' => fake()->optional()->imageUrl(256, 256),
        ];
    }
}
