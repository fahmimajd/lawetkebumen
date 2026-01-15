<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\WaGatewayClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use RuntimeException;

class WaConnectionController extends Controller
{
    public function index(): View
    {
        return view('settings.wa.index');
    }

    public function status(WaGatewayClient $client): JsonResponse
    {
        try {
            $payload = $client->getStatus();
        } catch (RuntimeException $exception) {
            return response()->json([
                'ok' => false,
                'message' => $exception->getMessage(),
            ], 503);
        }

        return response()->json($payload);
    }

    public function qr(WaGatewayClient $client): JsonResponse
    {
        try {
            $payload = $client->getQr();
        } catch (RuntimeException $exception) {
            return response()->json([
                'ok' => false,
                'message' => $exception->getMessage(),
            ], 503);
        }

        return response()->json($payload);
    }

    public function reconnect(WaGatewayClient $client): RedirectResponse
    {
        try {
            $client->reconnect();
        } catch (RuntimeException $exception) {
            return back()->with('status', $exception->getMessage());
        }

        return back()->with('status', 'Reconnect requested.');
    }

    public function logout(WaGatewayClient $client): RedirectResponse
    {
        try {
            $client->logout();
        } catch (RuntimeException $exception) {
            return back()->with('status', $exception->getMessage());
        }

        return back()->with('status', 'Logout requested.');
    }

    public function reset(WaGatewayClient $client): RedirectResponse
    {
        try {
            $client->reset();
        } catch (RuntimeException $exception) {
            return back()->with('status', $exception->getMessage());
        }

        return back()->with('status', 'Connection data deleted. Please scan QR again.');
    }
}
