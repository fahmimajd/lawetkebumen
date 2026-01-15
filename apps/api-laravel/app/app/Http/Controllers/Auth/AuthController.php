<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\AttemptLogin;
use App\Actions\Auth\Logout;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function login(LoginRequest $request, AttemptLogin $action): JsonResponse
    {
        $user = $action->handle($request->validated(), $request);

        if (! $user) {
            return response()->json([
                'message' => 'Invalid credentials.',
            ], 422);
        }

        return response()->json([
            'user' => $user,
        ]);
    }

    public function logout(Request $request, Logout $action): JsonResponse
    {
        $action->handle($request);

        return response()->json(['ok' => true]);
    }
}
