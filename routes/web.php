<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\Admin\EmpresaController;

/*
|--------------------------------------------------------------------------
| 1. RUTAS DEL INQUILINO / CLIENTE (Subdominios dinámicos)
|--------------------------------------------------------------------------
*/
Route::group(['domain' => '{tenant}.erp-global.test'], function () {

    Route::get('/', function ($tenant) {
        // Pasamos la variable explícitamente como una prop estándar
        return Inertia::render('Welcome', [
            'tenantName' => strtoupper($tenant)
        ]);
    });

});

/*
|--------------------------------------------------------------------------
| 2. RUTAS DEL PANEL CENTRAL / LANDLORD (Dominio principal)
|--------------------------------------------------------------------------
*/
Route::group(['domain' => 'erp-global.test'], function () {

    Route::get('/', function () {
        return Inertia::render('Welcome', [
            'message' => '¡Hola desde Vue 3 + Inertia! El frontend reactivo de tu ERP está listo.'
        ]);
    });

    // Tu CRUD de control de empresas centrales
    Route::resource('empresas', EmpresaController::class);
});
