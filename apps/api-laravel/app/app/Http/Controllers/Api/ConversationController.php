<?php

namespace App\Http\Controllers\Api;

use App\Actions\Conversations\AssignConversation;
use App\Actions\Conversations\AcquireConversationLock;
use App\Actions\Conversations\DeleteConversation;
use App\Actions\Conversations\ListConversations;
use App\Actions\Conversations\MarkConversationRead;
use App\Actions\Conversations\ReleaseConversationLock;
use App\Actions\Conversations\UpdateConversationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ConversationAssignRequest;
use App\Http\Requests\Api\ConversationListRequest;
use App\Http\Requests\Api\ConversationStatusRequest;
use App\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConversationController extends Controller
{
    public function index(ConversationListRequest $request, ListConversations $action): JsonResponse
    {
        return response()->json($action->handle($request->user(), $request));
    }

    public function assign(
        ConversationAssignRequest $request,
        Conversation $conversation,
        AssignConversation $action
    ): JsonResponse {
        return response()->json($action->handle($request->user(), $conversation, $request->validated()));
    }

    public function status(
        ConversationStatusRequest $request,
        Conversation $conversation,
        UpdateConversationStatus $action
    ): JsonResponse {
        return response()->json($action->handle($request->user(), $conversation, $request->validated()));
    }

    public function read(Request $request, Conversation $conversation, MarkConversationRead $action): JsonResponse
    {
        return response()->json($action->handle($request->user(), $conversation));
    }

    public function lock(Request $request, Conversation $conversation, AcquireConversationLock $action): JsonResponse
    {
        $result = $action->handle($request->user(), $conversation);
        $status = $result['http_status'] ?? 200;
        unset($result['http_status']);

        return response()->json($result, $status);
    }

    public function unlock(Request $request, Conversation $conversation, ReleaseConversationLock $action): JsonResponse
    {
        return response()->json($action->handle($request->user(), $conversation));
    }

    public function destroy(
        Request $request,
        Conversation $conversation,
        DeleteConversation $action
    ): JsonResponse {
        return response()->json($action->handle($request->user(), $conversation));
    }
}
