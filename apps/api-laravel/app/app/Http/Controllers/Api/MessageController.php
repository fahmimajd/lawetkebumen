<?php

namespace App\Http\Controllers\Api;

use App\Actions\Messages\ListConversationMessages;
use App\Actions\Messages\DeleteConversationMessage;
use App\Actions\Messages\SendConversationMessage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ConversationMessagesRequest;
use App\Http\Requests\Api\ConversationSendMessageRequest;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function index(
        ConversationMessagesRequest $request,
        Conversation $conversation,
        ListConversationMessages $action
    ): JsonResponse {
        return response()->json($action->handle($request->user(), $conversation, $request));
    }

    public function store(
        ConversationSendMessageRequest $request,
        Conversation $conversation,
        SendConversationMessage $action
    ): JsonResponse {
        return response()->json($action->handle($request->user(), $conversation, $request->validated()), 202);
    }

    public function destroy(
        Request $request,
        Conversation $conversation,
        Message $message,
        DeleteConversationMessage $action
    ): JsonResponse {
        return response()->json($action->handle($request->user(), $conversation, $message));
    }
}
