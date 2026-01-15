<?php

use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\ConversationWorkflowController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\QuickAnswerController;
use App\Http\Controllers\Webhooks\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/wa', WhatsAppWebhookController::class)
    ->middleware(['throttle:wa-webhooks', 'verify.payload']);

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/conversations', [ConversationController::class, 'index']);
    Route::get('/quick-answers', [QuickAnswerController::class, 'index']);
    Route::post('/conversations/{conversation}/assign', [ConversationController::class, 'assign']);
    Route::post('/conversations/{conversation}/status', [ConversationController::class, 'status']);
    Route::post('/conversations/{conversation}/read', [ConversationController::class, 'read']);
    Route::post('/conversations/{conversation}/lock', [ConversationController::class, 'lock']);
    Route::delete('/conversations/{conversation}/lock', [ConversationController::class, 'unlock']);
    Route::delete('/conversations/{conversation}', [ConversationController::class, 'destroy']);

    Route::post('/conversations/{conversation}/accept', [ConversationWorkflowController::class, 'accept']);
    Route::post('/conversations/{conversation}/transfer', [ConversationWorkflowController::class, 'transfer']);
    Route::post('/conversations/{conversation}/close', [ConversationWorkflowController::class, 'close']);
    Route::post('/conversations/{conversation}/reopen', [ConversationWorkflowController::class, 'reopen']);

    Route::get('/conversations/{conversation}/messages', [MessageController::class, 'index']);
    Route::post('/conversations/{conversation}/messages', [MessageController::class, 'store']);
    Route::delete('/conversations/{conversation}/messages/{message}', [MessageController::class, 'destroy']);
});
