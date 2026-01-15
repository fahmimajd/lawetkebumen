<?php

namespace Tests\Feature\Broadcasting;

use App\Events\ConversationUpdated;
use App\Models\Conversation;
use App\Support\BroadcasterAfterCommit;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class BroadcastAfterCommitTest extends TestCase
{
    use DatabaseMigrations;

    public function test_broadcast_is_delayed_until_after_commit(): void
    {
        Event::fake([ConversationUpdated::class]);

        $conversation = Conversation::factory()->create();

        DB::beginTransaction();

        BroadcasterAfterCommit::conversationUpdated($conversation);

        Event::assertNotDispatched(ConversationUpdated::class);

        DB::commit();

        Event::assertDispatched(ConversationUpdated::class);
    }
}
