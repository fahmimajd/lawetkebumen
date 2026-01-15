<?php

namespace App\Http\Controllers\Api;

use App\Actions\Conversations\AcceptConversation;
use App\Actions\Conversations\CloseConversation;
use App\Actions\Conversations\ReopenConversation;
use App\Actions\Conversations\TransferConversation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ConversationTransferRequest;
use App\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConversationWorkflowController extends Controller
{
    public function accept(Request $request, Conversation $conversation, AcceptConversation $action): JsonResponse
    {
        return response()->json($action->handle($request->user(), $conversation));
    }

    public function transfer(
        ConversationTransferRequest $request,
        Conversation $conversation,
        TransferConversation $action
    ): JsonResponse {
        return response()->json($action->handle($request->user(), $conversation, $request->validated()));
    }

    public function close(Request $request, Conversation $conversation, CloseConversation $action): JsonResponse
    {
        return response()->json($action->handle($request->user(), $conversation));
    }

    public function reopen(Request $request, Conversation $conversation, ReopenConversation $action): JsonResponse
    {
        return response()->json($action->handle($request->user(), $conversation));
    }
}
