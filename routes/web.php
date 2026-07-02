<?php

use App\Http\Controllers\Admin\EmpresaController;
use App\Http\Controllers\Tenant\ContactController;
use App\Http\Controllers\Tenant\DashboardController;
use App\Http\Controllers\Tenant\ExportController;
use App\Http\Controllers\Tenant\ProductController;
use App\Http\Controllers\Tenant\TenantAuthController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| 1. RUTAS DEL INQUILINO / CLIENTE (Subdominios dinámicos)
|--------------------------------------------------------------------------
*/

Route::group([
    'domain' => '{tenant}.erp-global.test',
    'middleware' => [\App\Http\Middleware\TenantMiddleware::class]
], function () {

    // Ruta de bienvenida del subdominio
    Route::get('/', function () {
        return Inertia::render('Welcome');
    });

    // Rutas de autenticación sin el middleware 'guest' para evitar conflictos en Laravel
    Route::get('/login', [TenantAuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [TenantAuthController::class, 'login']);

    // Rutas protegidas por autenticación
    // Dentro de Route::group(['domain' => '{tenant}.erp-global.test' ...])
    Route::middleware(['auth'])->group(function () {

        // Cambiamos el index para que cargue normal sin el middleware 'web' redundante aquí adentro
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('tenant.dashboard');

        Route::post('/logout', [TenantAuthController::class, 'logout'])->name('tenant.logout');

        // 🚀 AÑADE ESTAS LÍNEAS AQUÍ ADENTRO:
        Route::get('/contacts', [ContactController::class, 'index'])->name('contacts.index');
        Route::post('/contacts', [ContactController::class, 'store'])->name('contacts.store');
        Route::patch('/contacts/{contact}/toggle', [ContactController::class, 'toggleStatus'])->name('contacts.toggle');
        Route::put('/contacts/{contact}', [ContactController::class, 'update'])->name('contacts.update');

        // 📦 MÓDULO DE PRODUCTOS Y SERVICIOS
        Route::get('/products', [ProductController::class, 'index'])->name('products.index');
        Route::post('/products', [ProductController::class, 'store'])->name('products.store');
        Route::put('/products/{product}', [ProductController::class, 'update'])->name('products.update');
        Route::patch('/products/{product}/toggle', [ProductController::class, 'toggleStatus'])->name('products.toggle');

        // 📊 MOTOR DE EXPORTACIÓN GENÉRICO
        Route::post('/export/preferences', [ExportController::class, 'savePreferences'])->name('export.preferences');
        Route::post('/export/download', [ExportController::class, 'export'])->name('export.download');
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

    // CRUD de control de empresas centrales
    Route::resource('empresas', EmpresaController::class);
});
