<?php

namespace App\Observers;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;

class TenantObserver
{
    /**
     * Se ejecuta automáticamente justo después de insertar la empresa en la BD central
     */
    public function created(Tenant $tenant): void
    {
        // 1. Definimos el nombre de su base de datos propia
        $dbName = 'tenant_' . $tenant->id;

        // 2. Creamos la base de datos físicamente en MySQL si no existe
        DB::statement("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");

        // 3. Cambiamos la conexión de Laravel dinámicamente hacia la nueva BD para migrarla
        config(['database.connections.tenant' => [
            'driver' => 'mysql',
            'host' => config('database.connections.mysql.host'),
            'port' => config('database.connections.mysql.port'),
            'database' => $dbName,
            'username' => config('database.connections.mysql.username'),
            'password' => config('database.connections.mysql.password'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
        ]]);

        // 4. Ejecutamos las migraciones del ERP apuntando exclusivamente a esa conexión temporaria
        Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path' => 'database/migrations/tenant', // Separaremos las tablas del cliente aquí
            '--force' => true,
        ]);
    }
}
