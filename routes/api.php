<?php

use App\Http\Controllers\Admin\SongAdminController;
use App\Http\Controllers\Admin\SuggestionModerationController;
use App\Http\Controllers\Admin\UserAdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\SongController;
use App\Http\Controllers\SuggestionController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'project' => config('app.name', 'Laravel API'),
        'version' => '2.0',
    ], 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
});

Route::get('/health', function () {
    try {
        DB::select('select 1');
    } catch (\Throwable $e) {
        return response()->json(['ok' => false, 'service' => 'db'], 500);
    }

    return response()->json([
        'ok' => true,
        'timestamp' => now()->toISOString(),
    ]);
});

Route::get('/songs/top', [SongController::class, 'top']);
Route::get('/songs/rest', [SongController::class, 'rest']);
Route::post('/suggestions', [SuggestionController::class, 'store']);

Route::post('/login', [AuthController::class, 'login']);
Route::post('/refresh', [AuthController::class, 'refresh'])->middleware('jwt.auth');

Route::middleware('jwt.auth')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::put('/me', [AuthController::class, 'updateProfile']);
    Route::put('/me/password', [AuthController::class, 'changePassword']);
    Route::post('/me/avatar', [AuthController::class, 'uploadAvatar']);
    Route::delete('/me/avatar', [AuthController::class, 'clearAvatar']);

    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/users/active', [AuthController::class, 'activeUsers']); // compat
});

Route::middleware(['jwt.auth', 'can:admin'])->group(function () {

    Route::get('/suggestions', [SuggestionModerationController::class, 'index']);
    Route::post('/suggestions/{suggestion}/approve', [SuggestionModerationController::class, 'approve']);
    Route::post('/suggestions/{suggestion}/reject', [SuggestionModerationController::class, 'reject']);

    Route::prefix('/admin')->group(function () {
        Route::get('/users', [UserAdminController::class, 'index']);
        Route::post('/users', [UserAdminController::class, 'store']);
        Route::put('/users/{id}', [UserAdminController::class, 'update']);
        Route::put('/users/{id}/password', [UserAdminController::class, 'updatePassword']);
        Route::delete('/users/{id}', [UserAdminController::class, 'destroy']);
    });
    Route::apiResource('/songs', SongAdminController::class)->only(['index', 'destroy']);
});
