<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'project' => config('app.name', 'Collection Laravel API'),
        'version' => '2.0',
        'created_by' => 'Luan Mykel',
        'email' => 'luanmykel@hotmail.com',
        'backend' => [
            'framework' => 'Laravel',
            'version' => app()->version(),
        ],
        'frontend' => [
            'spa' => 'ReactJS',
        ],
        'stack' => [
            'php' => PHP_VERSION,
            'env' => app()->environment(),
            'timezone' => config('app.timezone'),
        ],
        'links' => [
            'api' => url('/api'),
        ],
        '_meta' => [
            'timestamp' => now()->toIso8601String(),
            'server' => request()->server('SERVER_NAME'),
        ],
    ], 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
});
