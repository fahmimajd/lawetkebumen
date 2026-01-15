<?php

use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\Admin\WaConnectionController;
use App\Http\Controllers\Admin\QuickAnswerController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\Reports\DailyAgentReportController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::redirect('/', '/inbox');
    Route::view('/inbox', 'inbox.index')->name('inbox');
    Route::get('/contacts', [ContactController::class, 'index'])->name('contacts.index');
    Route::post('/contacts', [ContactController::class, 'store'])->name('contacts.store');
    Route::put('/contacts/{contact}', [ContactController::class, 'update'])->name('contacts.update');
    Route::delete('/contacts/{contact}', [ContactController::class, 'destroy'])->name('contacts.destroy');
    Route::get('/wa/status', [WaConnectionController::class, 'status'])->name('wa.status');

    Route::middleware('role:admin')->group(function () {
        Route::get('/settings/users', [UserManagementController::class, 'index'])
            ->name('settings.users.index');
        Route::post('/settings/users', [UserManagementController::class, 'store'])
            ->name('settings.users.store');
        Route::put('/settings/users/{user}', [UserManagementController::class, 'update'])
            ->name('settings.users.update');
        Route::patch('/settings/users/{user}/toggle', [UserManagementController::class, 'toggleActive'])
            ->name('settings.users.toggle');
        Route::delete('/settings/users/{user}', [UserManagementController::class, 'destroy'])
            ->name('settings.users.destroy');

        Route::get('/settings/quick-answers', [QuickAnswerController::class, 'index'])
            ->name('settings.quick-answers.index');
        Route::post('/settings/quick-answers', [QuickAnswerController::class, 'store'])
            ->name('settings.quick-answers.store');
        Route::put('/settings/quick-answers/{quickAnswer}', [QuickAnswerController::class, 'update'])
            ->name('settings.quick-answers.update');
        Route::delete('/settings/quick-answers/{quickAnswer}', [QuickAnswerController::class, 'destroy'])
            ->name('settings.quick-answers.destroy');

        Route::get('/settings/wa', [WaConnectionController::class, 'index'])
            ->name('settings.wa.index');
        Route::get('/settings/wa/status', [WaConnectionController::class, 'status'])
            ->name('settings.wa.status');
        Route::get('/settings/wa/qr', [WaConnectionController::class, 'qr'])
            ->name('settings.wa.qr');
        Route::post('/settings/wa/reconnect', [WaConnectionController::class, 'reconnect'])
            ->name('settings.wa.reconnect');
        Route::post('/settings/wa/logout', [WaConnectionController::class, 'logout'])
            ->name('settings.wa.logout');
        Route::post('/settings/wa/reset', [WaConnectionController::class, 'reset'])
            ->name('settings.wa.reset');

        Route::get('/reports/daily-agents', [DailyAgentReportController::class, 'index'])
            ->name('reports.daily-agents');
    });
});

require __DIR__.'/auth.php';
