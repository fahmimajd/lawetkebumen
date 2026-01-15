<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyPayloadSize
{
    public function handle(Request $request, Closure $next, ?string $maxBytes = null): Response
    {
        $limit = $maxBytes !== null ? (int) $maxBytes : (int) config('services.wa_gateway.webhook_max_bytes', 0);

        if ($limit > 0) {
            $contentLength = $request->headers->get('Content-Length');
            if ($contentLength !== null && (int) $contentLength > $limit) {
                return response()->json(['message' => 'Payload too large.'], 413);
            }

            $raw = $request->getContent();
            if ($raw !== '' && strlen($raw) > $limit) {
                return response()->json(['message' => 'Payload too large.'], 413);
            }
        }

        return $next($request);
    }
}
