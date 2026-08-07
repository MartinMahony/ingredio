<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Sentry\Laravel\Integration;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $proxies = env('TRUSTED_PROXIES', '*');

        if ($proxies !== '*') {
            $proxies = array_filter(array_map('trim', explode(',', $proxies)));

            if ($proxies === []) {
                $proxies = '*';
            }
        }

        // Trust the reverse proxy (Traefik, via Coolify) in production so
        // HTTPS/scheme detection and secure cookies are correct. We deliberately
        // do NOT trust X-Forwarded-Host to prevent host-header spoofing that
        // can poison password-reset and other generated URLs.
        // In production set TRUSTED_PROXIES to the actual proxy CIDRs instead of *.
        $middleware->trustProxies(
            at: $proxies,
            headers: Request::HEADER_X_FORWARDED_FOR |
                Request::HEADER_X_FORWARDED_PORT |
                Request::HEADER_X_FORWARDED_PROTO |
                Request::HEADER_X_FORWARDED_AWS_ELB,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        Integration::handles($exceptions);
    })->create();
