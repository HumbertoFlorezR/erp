<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Tenant;
use Symfony\Component\HttpFoundation\Response;

class TenantMiddleware
{
    /**
     * Intercepta la petición para conectar la base de datos del cliente según el subdominio
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Extraemos el host actual (ej: sajjuna.erp-global.test)
        $host = $request->getHost();

        // 2. Separamos las partes por el punto
        $parts = explode('.', $host);

        // Si hay subdominio (ej: sajjuna), el conteo de partes en un dominio local suele ser mayor a 2
        // Cambia el '3' según tu estructura (ej: si es miempresa.localhost, tiene 2 partes)
        if (count($parts) >= 3 && $parts[0] !== 'www' && $parts[0] !== 'erp-global') {

            $subdominio = $parts[0];

            // 3. Buscamos si ese subdominio existe registrado en la tabla central
            $tenant = Tenant::find($subdominio);

            if ($tenant) {
                // 4. Definimos el nombre de su base de datos
                $dbName = 'tenant_' . str_replace('-', '_', $subdominio);

                // 5. Reconfiguramos la conexión predeterminada (mysql) apuntando al cliente
                config(['database.connections.mysql.database' => $dbName]);

                // 6. Purgamos la conexión vieja para obligar a Laravel a reconectarse a la BD del cliente
                DB::purge('mysql');
                DB::reconnect('mysql');
            } else {
                // Si el subdominio no existe en el sistema, abortamos con un 404
                abort(404, "La empresa solicitada no existe.");
            }
        }

        return $next($request);
    }
}
