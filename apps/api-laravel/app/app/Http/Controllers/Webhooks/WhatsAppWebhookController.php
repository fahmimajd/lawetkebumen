<?php

namespace App\Http\Controllers\Webhooks;

use App\Actions\Webhooks\ProcessWhatsAppWebhook;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhatsAppWebhookController extends Controller
{
    public function __invoke(Request $request, ProcessWhatsAppWebhook $action): JsonResponse
    {
        $result = $action->handle($request);

        return response()->json($result, 200);
    }
}
