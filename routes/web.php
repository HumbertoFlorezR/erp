<?php

use App\Http\Controllers\Tenant\ExpenseController;
use App\Http\Controllers\Admin\EmpresaController;
use App\Http\Controllers\AiQueryController;
use App\Http\Controllers\Tenant\AccountPayableController;
use App\Http\Controllers\Tenant\AccountReceivableController;
use App\Http\Controllers\Tenant\ContactController;
use App\Http\Controllers\Tenant\DashboardController;
use App\Http\Controllers\Tenant\DianResolutionController;
use App\Http\Controllers\Tenant\ExportController;
use App\Http\Controllers\Tenant\ProductController;
use App\Http\Controllers\Tenant\PurchaseInvoiceController;
use App\Http\Controllers\Tenant\SalesPosController;
use App\Http\Controllers\Tenant\TenantAuthController;
use Illuminate\Support\Facades\Log;
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

        Route::get('/contacts', [ContactController::class, 'index'])->name('contacts.index');
        Route::post('/contacts', [ContactController::class, 'store'])->name('contacts.store');
        Route::patch('/contacts/{contact}/toggle', [ContactController::class, 'toggleStatus'])->name('contacts.toggle');
        Route::put('/contacts/{contact}', [ContactController::class, 'update'])->name('contacts.update');
        Route::post('contacts/quick-provider', [ContactController::class, 'storeQuickProvider'])->name('contacts.quick-provider');

        Route::get('/products', [ProductController::class, 'index'])->name('products.index');
        Route::post('/products', [ProductController::class, 'store'])->name('products.store');
        Route::put('/products/{product}', [ProductController::class, 'update'])->name('products.update');
        Route::patch('/products/{product}/toggle', [ProductController::class, 'toggleStatus'])->name('products.toggle');
        Route::post('products/quick-store', [ProductController::class, 'storeQuick'])->name('products.quick-store');

        /*
        |--------------------------------------------------------------------------
        | MÓDULO DE COMPRAS (PURCHASE INVOICES) - 100% MANUAL PARA EVITAR CHOQUES
        |--------------------------------------------------------------------------
        */
        Route::get('purchase-invoices', [PurchaseInvoiceController::class, 'index'])->name('purchase-invoices.index');
        Route::post('purchase-invoices', [PurchaseInvoiceController::class, 'store'])->name('purchase-invoices.store');
        Route::get('purchase-invoices/create', [PurchaseInvoiceController::class, 'create'])->name('purchase-invoices.create');
        Route::get('purchase-invoices/{purchase_invoice}', [PurchaseInvoiceController::class, 'show'])->name('purchase-invoices.show');
        Route::post('purchase-invoices/{id}/cancel', [PurchaseInvoiceController::class, 'cancel'])->name('purchase-invoices.cancel');

        // --- MÓDULO ADMINISTRATIVO DE CONFIGURACIÓN ---
        Route::get('/settings/resolutions', [DianResolutionController::class, 'index'])->name('settings.resolutions.index');
        Route::get('/settings/resolutions/create', [DianResolutionController::class, 'create'])->name('settings.resolutions.create');
        Route::post('/settings/resolutions', [DianResolutionController::class, 'store'])->name('settings.resolutions.store');
        Route::put('/settings/resolutions/{id}/toggle', [DianResolutionController::class, 'toggleActive'])->name('settings.resolutions.toggle');

        // Rutas del Punto de Venta (POS)
        Route::get('/sales/pos', [SalesPosController::class, 'index'])->name('sales.pos.index');
        Route::post('/sales/pos', [SalesPosController::class, 'store'])->name('sales.pos.store');
        Route::post('/sales/pos/customer', [SalesPosController::class, 'storeCustomer'])->name('sales.pos.customer');
        Route::get('/sales/pos/search-customers', [SalesPosController::class, 'searchCustomers'])->name('sales.pos.search-customers');
        Route::get('/sales/pos/search-products', [SalesPosController::class, 'searchProducts'])->name('sales.pos.search-products');

        // Rutas para la gestión de cuentas por cobrar
        Route::get('/accounts-receivable', [AccountReceivableController::class, 'index'])->name('accounts-receivable.index');
        Route::get('/accounts-receivable/{accountId}', [AccountReceivableController::class, 'show'])->name('accounts-receivable.show');
        Route::post('/accounts-receivable/{accountId}/payment', [AccountReceivableController::class, 'applyPayment'])->name('accounts-receivable.payment');
        Route::post('/accounts-receivable/{accountId}/payments', [AccountReceivableController::class, 'applyPayment'])->name('accounts-receivable.apply-payment');

        // Rutas para la gestión de cuentas por pagar
        Route::get('/accounts-payable', [AccountPayableController::class, 'index'])->name('accounts-payable.index');
        Route::get('/accounts-payable/{accountId}', [AccountPayableController::class, 'show'])->name('accounts-payable.show');
        Route::post('/accounts-payable/{accountId}/payments', [AccountPayableController::class, 'applyPayment'])->name('accounts-payable.apply-payment');

        // Módulo de Gastos
        Route::middleware(['permission:gastos.ver'])->group(function () {
            Route::get('/expenses', [ExpenseController::class, 'index'])->name('expenses.index');
            Route::middleware(['permission:gastos.crear'])->group(function () {
                Route::post('/expenses', [ExpenseController::class, 'store'])->name('expenses.store');
                Route::post('/expenses/categories', [ExpenseController::class, 'storeQuickCategory'])->name('expenses.categories.store');
            });
            Route::middleware(['permission:gastos.editar'])->group(function () {
                Route::put('/expenses/{id}', [ExpenseController::class, 'update'])->name('expenses.update');
            });
            Route::middleware(['permission:gastos.anular'])->group(function () {
                Route::post('/expenses/{id}/cancel', [ExpenseController::class, 'cancel'])->name('expenses.cancel');
            });
        });
        /*
        Route::get('/expenses', [ExpenseController::class, 'index'])->name('expenses.index');
        Route::post('/expenses', [ExpenseController::class, 'store'])->name('expenses.store');
        Route::put('/expenses/{id}', [ExpenseController::class, 'update'])->name('expenses.update');
        Route::post('/expenses/{id}/cancel', [ExpenseController::class, 'cancel'])->name('expenses.cancel');
        Route::post('/expenses/categories', [ExpenseController::class, 'storeQuickCategory'])->name('expenses.categories.store');

        /*
        // Módulo de Gastos — todo requiere al menos poder verlo


// Ventas POS
Route::middleware(['permission:pos.vender'])->group(function () {
    Route::get('/sales/pos', [SalesPosController::class, 'index'])->name('sales.pos.index');
    Route::post('/sales/pos', [SalesPosController::class, 'store'])->name('sales.pos.store');
    Route::post('/sales/pos/customer', [SalesPosController::class, 'storeCustomer'])->name('sales.pos.customer');
    Route::get('/sales/pos/search-customers', [SalesPosController::class, 'searchCustomers'])->name('sales.pos.search-customers');
    Route::get('/sales/pos/search-products', [SalesPosController::class, 'searchProducts'])->name('sales.pos.search-products');
});
        */
        // Ruta de prueba para verificar que el POST funciona
        Route::post('/test-payment/{id}', function ($id) {
            return response()->json(['success' => true, 'id' => $id]);
        });

        /* Exportación de datos */
        Route::post('/export/preferences', [ExportController::class, 'savePreferences'])->name('export.preferences');
        Route::post('/export/download', [ExportController::class, 'export'])->name('export.download');

        // 🤖 RUTA PARA EL ASISTENTE IA (Solo Admin)
        Route::middleware(['can:admin'])->group(function () {
            Route::post('/admin/ai-query', [AiQueryController::class, 'processQuery'])->name('admin.ai-query');
        });

    // Fallback temporal para depuración de 404 en el subdominio del tenant.
    // Registra en storage/logs/laravel.log información útil sobre la petición.
    Route::fallback(function () {
        $req = request();
        Log::warning('Fallback (tenant) - unmatched request', [
            'method' => $req->method(),
            'url' => $req->fullUrl(),
            'path' => $req->path(),
            'host' => $req->getHost(),
            'headers' => $req->headers->all(),
            'is_ajax' => $req->ajax(),
            'expects_json' => $req->expectsJson(),
        ]);

        if ($req->expectsJson() || $req->ajax()) {
            return response()->json(['message' => 'Not Found (logged)'], 404);
        }

        abort(404);
    });

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
