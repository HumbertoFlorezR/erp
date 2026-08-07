<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Limpiamos la caché de permisos antes de sembrar, para evitar datos viejos en memoria
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // 1. CATÁLOGO COMPLETO DE PERMISOS, POR MÓDULO
        $permissions = [
            // Dashboard
            'dashboard.ver',

            // Contactos
            'contactos.ver', 'contactos.crear', 'contactos.editar', 'contactos.toggle',

            // Productos
            'productos.ver', 'productos.crear', 'productos.editar', 'productos.toggle',

            // Compras
            'compras.ver', 'compras.crear', 'compras.anular',

            // Ventas POS
            'pos.vender', 'pos.ver',

            // Cartera por Cobrar
            'cartera-cobrar.ver', 'cartera-cobrar.abonar',

            // Cartera por Pagar
            'cartera-pagar.ver', 'cartera-pagar.abonar',

            // Gastos
            'gastos.ver', 'gastos.crear', 'gastos.editar', 'gastos.anular',

            // Exportación
            'exportacion.usar',

            // Configuración
            'configuracion.dian', 'configuracion.usuarios',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // 2. ROLES COMO "COMBOS" PREDEFINIDOS DE ESOS PERMISOS

        // ADMIN: tiene todo, sin excepción
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->syncPermissions(Permission::all());

        // CAJERO: enfocado en el punto de venta del día a día
        $cajero = Role::firstOrCreate(['name' => 'cajero', 'guard_name' => 'web']);
        $cajero->syncPermissions([
            'dashboard.ver',
            'pos.vender', 'pos.ver',
            'productos.ver',
            'contactos.ver', 'contactos.crear', // para poder crear cliente rápido en el POS
            'cartera-cobrar.ver',
        ]);

        // CONTADOR: enfocado en la parte financiera, sin tocar operación diaria
        $contador = Role::firstOrCreate(['name' => 'contador', 'guard_name' => 'web']);
        $contador->syncPermissions([
            'dashboard.ver',
            'contactos.ver',
            'productos.ver',
            'compras.ver',
            'cartera-cobrar.ver', 'cartera-cobrar.abonar',
            'cartera-pagar.ver', 'cartera-pagar.abonar',
            'gastos.ver', 'gastos.crear', 'gastos.editar', 'gastos.anular',
            'exportacion.usar',
        ]);

        // SOLO LECTURA: puede ver todo, no puede crear/editar/anular nada
        $lectura = Role::firstOrCreate(['name' => 'solo-lectura', 'guard_name' => 'web']);
        $lectura->syncPermissions(
            Permission::all()->filter(fn ($p) => str_ends_with($p->name, '.ver'))
        );
    }
}
