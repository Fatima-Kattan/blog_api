<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',   
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
$app->configure('cors');

$app->middleware([
    Illuminate\Http\Middleware\HandleCors::class, // ⬅️ هذا موجود أصلاً
]);

$app->middlewareGroup('api', [
    Illuminate\Http\Middleware\HandleCors::class, // ⬅️ تأكد أنه موجود هنا
]);
$app->middleware(function (Request $request, Closure $next) {
    $response = $next($request);
    
    $response->headers->set('Access-Control-Allow-Origin', '*');
    $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
    $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
    
    return $response;
});