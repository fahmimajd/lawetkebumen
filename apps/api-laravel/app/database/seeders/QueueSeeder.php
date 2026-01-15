<?php

namespace Database\Seeders;

use App\Models\Queue;
use Illuminate\Database\Seeder;

class QueueSeeder extends Seeder
{
    public function run(): void
    {
        Queue::query()->firstOrCreate(
            ['is_default' => true],
            ['name' => 'Default']
        );
    }
}
