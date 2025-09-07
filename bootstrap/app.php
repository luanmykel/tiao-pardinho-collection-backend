<?php

use App\Http\Middleware\RefreshSongViewsThrottle;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Validation\ValidationException;
use PHPOpenSourceSaver\JWTAuth\Http\Middleware\Authenticate as JwtAuthenticate;
use PHPOpenSourceSaver\JWTAuth\Http\Middleware\RefreshToken as JwtRefresh;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn () => true);

        $exceptions->renderable(function (AuthenticationException $e) {
            return response()->json(['message' => 'Não autenticado'], 401);
        });

        $exceptions->renderable(function (AuthorizationException $e) {
            return response()->json(['message' => 'Acesso negado'], 403);
        });

        $exceptions->renderable(function (NotFoundHttpException $e) {
            return response()->json(['message' => 'Não encontrado'], 404);
        });

        $exceptions->renderable(function (ModelNotFoundException $e) {
            return response()->json(['message' => 'Não encontrado'], 404);
        });

        $exceptions->renderable(function (MethodNotAllowedHttpException $e) {
            return response()->json(['message' => 'Método não permitido'], 405);
        });

        $exceptions->renderable(function (ValidationException $e) {
            return response()->json([
                'message' => 'Corrija os campos destacados.',
                'errors' => $e->errors(),
            ], 422);
        });

    })->withMiddleware(function ($middleware) {
        $middleware->append(RefreshSongViewsThrottle::class);
        $middleware->alias([
            'jwt.auth' => JwtAuthenticate::class,
            'jwt.refresh' => JwtRefresh::class,
        ]);
    })->create();
