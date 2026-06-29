<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;

class MigrateTenants extends Command
{
    protected $signature = 'tenants:migrate';
    protected $description = 'Ejecuta las migraciones en todas las bases de datos de los inquilinos';

    public function handle()
    {
        // 1. Consultamos todos los inquilinos registrados en la base central
        $tenants = DB::table('erp-global.tenants')->get();

        foreach ($tenants ?? [] as $tenant) {
            $dbName = 'tenant_' . str_replace('-', '_', $tenant->id);
            $this->info("Migrando el inquilino: {$tenant->id} ({$dbName})");

            // Cambiamos la configuración de la conexión en caliente
            config(['database.connections.mysql.database' => $dbName]);
            DB::purge('mysql');

            // Ejecutamos el comando nativo apuntando solo a la carpeta tenant
            Artisan::call('migrate', [
                '--path' => '/database/migrations/tenant',
                '--force' => true
            ]);
        }

        $this->info('¡Proceso de migración de tenants finalizado con éxito!');
    }
}
