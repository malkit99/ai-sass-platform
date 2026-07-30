<?php

use App\Http\Middleware\EnsureTrialNotExpired;
use App\Http\Middleware\ResolveResellerDomain;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->api(append: [ResolveResellerDomain::class]);
        $middleware->alias(['trial.active' => EnsureTrialNotExpired::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Every /api/* route (including the Public REST API, screenshots
        // 38-50) has no HTML representation — without this, a caller that
        // doesn't send an explicit Accept: application/json header (plain
        // curl, some low-code tools) gets an HTML redirect for a
        // ValidationException or an HTML error page for a 401/403/404
        // instead of a parseable JSON body.
        $exceptions->shouldRenderJsonWhen(fn ($request, $e) => $request->is('api/*') || $request->expectsJson());
    })->create();
