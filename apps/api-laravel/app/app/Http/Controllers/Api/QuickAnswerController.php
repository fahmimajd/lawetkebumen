<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\QuickAnswer;
use Illuminate\Http\JsonResponse;

class QuickAnswerController extends Controller
{
    public function index(): JsonResponse
    {
        $quickAnswers = QuickAnswer::query()
            ->where('is_active', true)
            ->orderBy('shortcut')
            ->get(['id', 'shortcut', 'body']);

        return response()->json([
            'data' => $quickAnswers->map(fn (QuickAnswer $answer) => [
                'id' => $answer->id,
                'shortcut' => $answer->shortcut,
                'body' => $answer->body,
            ]),
        ]);
    }
}
