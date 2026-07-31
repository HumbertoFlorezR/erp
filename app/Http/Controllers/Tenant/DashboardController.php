<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\AccountReceivable;
use App\Models\Tenant\Product;
use App\Models\Tenant\PurchaseInvoice;
use App\Models\Tenant\Sale;
use App\Models\Tenant\SalePayment;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        $today = Carbon::now();
        $periodStart = $today->copy()->startOfMonth();
        $periodEnd = $today->copy()->endOfMonth();

        $totalSales = Sale::whereBetween('created_at', [$periodStart, $periodEnd])
            ->sum('total');

        $cashBalance = SalePayment::where('payment_method', 'EFECTIVO')
            ->whereBetween('created_at', [$periodStart, $periodEnd])
            ->sum('amount');

        $accountsReceivable = AccountReceivable::where('status', '!=', 'ANULADA')
            ->sum('balance');

        $lowStockCount = Product::where('manage_stock', true)
            ->whereColumn('stock', '<=', 'minimum_stock')
            ->count();

        $accountsReceivableList = AccountReceivable::with(['customer', 'sale'])
            ->where('status', '!=', 'ANULADA')
            ->orderBy('due_date')
            ->take(5)
            ->get()
            ->map(function ($account) {
                return [
                    'id' => $account->id,
                    'number' => $account->sale->invoice_number ?? "V-{$account->sale_id}",
                    'client' => $account->customer?->company_name ?? trim(($account->customer?->first_name ?? '') . ' ' . ($account->customer?->last_name ?? '')) ?: 'Cliente Desconocido',
                    'total' => $account->balance,
                ];
            });

        $accountsPayableList = PurchaseInvoice::with('provider')
            ->where('payment_status', 'PENDIENTE')
            ->orderBy('due_date')
            ->take(5)
            ->get()
            ->map(function ($invoice) {
                return [
                    'id' => $invoice->id,
                    'number' => $invoice->invoice_number ?? "F-{$invoice->id}",
                    'provider' => $invoice->provider?->company_name ?? trim(($invoice->provider?->first_name ?? '') . ' ' . ($invoice->provider?->last_name ?? '')) ?: 'Proveedor Desconocido',
                    'total' => $invoice->total,
                ];
            });

        $lowStockProducts = Product::where('manage_stock', true)
            ->whereColumn('stock', '<=', 'minimum_stock')
            ->orderBy('stock')
            ->take(5)
            ->get()
            ->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->code ?? 'Sin SKU',
                    'current_stock' => $product->stock,
                    'min_stock' => $product->minimum_stock,
                ];
            });

        return Inertia::render('Tenant/Dashboard', [
            'stats' => [
                'total_sales' => $totalSales,
                'cash_balance' => $cashBalance,
                'accounts_receivable' => $accountsReceivable,
                'low_stock_count' => $lowStockCount,
            ],
            'accountsReceivableList' => $accountsReceivableList,
            'accountsPayableList' => $accountsPayableList,
            'lowStockProducts' => $lowStockProducts,
        ]);
    }
}
