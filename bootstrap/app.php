<?php

use App\Http\Middleware\EnsureAdminAccess;
use App\Http\Middleware\EnsureCustomerAccess;
use App\Http\Middleware\EnsureWarehouseAccess;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Trust the tunnel/reverse proxy so Laravel builds correct https:// URLs
        // (needed when sharing the local server through Cloudflare/ngrok).
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'admin' => EnsureAdminAccess::class,
            'customer' => EnsureCustomerAccess::class,
            'warehouse' => EnsureWarehouseAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
