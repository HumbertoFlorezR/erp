<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

class TenantMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost();
        $parts = explode('.', $host);

        if (count($parts) > 2 && $parts[0] !== 'www' && $parts[0] !== 'erp-global') {
            $subdominio = $parts[0];
            $dbName = 'tenant_' . str_replace('-', '_', $subdominio);

            try {
                // Consultamos a la base de datos central para los parámetros estéticos
                $tenantData = DB::table('erp-global.tenants')
                    ->where('id', $subdominio)
                    ->first();

                \Inertia\Inertia::share('tenant', [
                    'id'              => $subdominio,
                    'company_name'    => $tenantData->company_name ?? strtoupper($subdominio),
                    'primary_color'   => $tenantData->primary_color ?? '#3b82f6',
                    'secondary_color' => $tenantData->secondary_color ?? '#1e293b',
                    'logo_url'        => $tenantData->logo_url ?? null,
                ]);
            } catch (\Exception $e) {
                \Inertia\Inertia::share('tenant', [
                    'id'              => $subdominio,
                    'company_name'    => strtoupper($subdominio),
                    'primary_color'   => '#3b82f6',
                    'secondary_color' => '#1e293b',
                ]);
            }

            // 1. Conmutamos la conexión a la base de datos del cliente de forma dura
            config(['database.connections.mysql.database' => $dbName]);
            DB::purge('mysql');
            DB::reconnect('mysql'); // Forzamos reconexión limpia

            // 2. 🔥 EL TRUCO DE ORO: Compartimos al usuario AQUÍ,
            // justo ahora que ya estamos parados en la base de datos del cliente.
            \Inertia\Inertia::share('auth', [
                'user' => Auth::user() ? [
                    'id'    => Auth::user()->id,
                    'name'  => Auth::user()->name,
                    'email' => Auth::user()->email,
                    'role'  => Auth::user()->role,
                ] : null,
            ]);
        }

        return $next($request);
    }
}
