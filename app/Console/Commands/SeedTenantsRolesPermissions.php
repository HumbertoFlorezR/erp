<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;

class SeedTenantsRolesPermissions extends Command
{
    protected $signature = 'tenants:seed-roles';
    protected $description = 'Siembra los roles y permisos base en todas las bases de datos de los inquilinos';

    public function handle()
    {
        $tenants = DB::table('erp-global.tenants')->get();
        $fallidos = [];

        foreach ($tenants ?? [] as $tenant) {
            $dbName = 'tenant_' . str_replace('-', '_', $tenant->id);
            $this->info("Sembrando roles en el inquilino: {$tenant->id} ({$dbName})");

            config(['database.connections.mysql.database' => $dbName]);
            DB::purge('mysql');

            try {
                Artisan::call('db:seed', [
                    '--class' => \Database\Seeders\RolesAndPermissionsSeeder::class,
                    '--force' => true,
                ]);
            } catch (\Exception $e) {
                $this->error("❌ Falló el tenant {$tenant->id}: " . $e->getMessage());
                $fallidos[] = $tenant->id;
                continue;
            }
        }

        if (count($fallidos) > 0) {
            $this->warn('Tenants con errores: ' . implode(', ', $fallidos));
        } else {
            $this->info('¡Roles y permisos sembrados correctamente en todos los tenants!');
        }
    }
}
