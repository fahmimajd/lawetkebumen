<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::routes([
    'middleware' => ['web', 'auth'],
]);

Broadcast::channel('conversations', function () {
    return true;
});

Broadcast::channel('conversation.{conversationId}', function () {
    return true;
});

Broadcast::channel('chat.{conversationId}', function () {
    return true;
});
