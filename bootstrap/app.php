<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {

        // 1. Mantenemos el CSRF activo de forma normal para el login
        $middleware->validateCsrfTokens(except: [
            'export/download',
        ]);

        // 2. Inyectamos de forma limpia el TenantMiddleware en el grupo Web de Laravel 13
        $middleware->web(append: [
            \App\Http\Middleware\TenantMiddleware::class,
        ]);

        // 3. 🔥 EL ORDEN CRUCIAL DE PRIORIDAD (Esto arregla el fallo de la sesión fantasma)
        $middleware->priority([
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class, // <-- Primero se crea la sesión
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
            \App\Http\Middleware\TenantMiddleware::class,       // <-- Luego conmutamos la base de datos
            \Illuminate\Routing\Middleware\SubstituteBindings::class, // <-- IMPORTANTE: Después del Tenant
            \Illuminate\Auth\Middleware\Authenticate::class,    // <-- Por último actúa la protección 'auth'
        ]);

        // 4. Configuración del redireccionador si el usuario es rechazado por el 'auth'
        $middleware->redirectTo(function ($request) {
            if ($request->is('login') || $request->is('*/login')) {
                return null;
            }

            $host = $request->getHost();
            $parts = explode('.', $host);

            if (count($parts) > 2 && $parts[0] !== 'www' && $parts[0] !== 'erp-global') {
                return route('login', ['tenant' => $parts[0]]);
            }

            return route('login');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
