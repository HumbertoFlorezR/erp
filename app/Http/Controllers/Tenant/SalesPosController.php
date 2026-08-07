<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\Tenant\AccountReceivable;
use App\Models\Tenant\DianResolution;
use App\Models\Tenant\KardexMovement;
use App\Models\Tenant\Product;
use App\Models\Tenant\Sale;
use App\Models\Tenant\SaleDetail;
use App\Models\Tenant\SalePayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class SalesPosController extends Controller
{
    /**
     * 1. Renderiza el punto de venta con la información inicial necesaria.
     */
    public function index()
    {
        // Obtener resolución activa DIAN
        $resolution = DianResolution::where('is_active', true)
            ->where('current_number', '<', DB::raw('to_number'))
            ->where('date_to', '>=', now()->toDateString())
            ->first();

        // Calcular el próximo consecutivo sugerido para visualización
        $nextInvoice = $resolution
            ? ($resolution->prefix ? $resolution->prefix . '-' : '') . ($resolution->current_number + 1)
            : 'SIN RESOLUCIÓN ACTIVA';

        return Inertia::render('Sales/SalesPos', [
            'nextInvoice' => $nextInvoice,
            'activeResolution' => $resolution,
            // Cargar un cliente genérico por defecto (ej: Cuantías Menores / Consumidor Final) si existe
            'defaultCustomer' => Contact::where('is_client', true)->where('document_number', '222222222222')->first()
        ]);
    }

    /**
     * 2. API interna para buscar productos por código de barras/SKU (code) o nombre.
     */
    public function searchProducts(Request $request)
    {
        try {
            $search = $request->get('q');

            if (empty($search)) {
                return response()->json([]);
            }

            $products = Product::where('is_active', true)
                ->where(function($query) use ($search) {
                    $query->where('code', 'LIKE', "%{$search}%") // 👈 Corregido a 'code'
                          ->orWhere('barcode', 'LIKE', "%{$search}%") // 👈 Corregido a 'barcode'
                          ->orWhere('name', 'LIKE', "%{$search}%");
                })
                ->take(10)
                ->get();

            return response()->json($products);

        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 3. API interna para crear clientes rápido desde la modal del POS.
     */
    public function storeCustomer(Request $request)
    {
        $validated = $request->validate([
            'document_type'   => 'required|string|max:5',
            'document_number' => 'required|string|max:20|unique:contacts,document_number',
            'first_name'      => 'nullable|string|max:100',
            'last_name'       => 'nullable|string|max:100',
            'company_name'    => 'nullable|string|max:200',
            'phone'           => 'nullable|string|max:20',
            'email'           => 'nullable|email|max:150',
        ]);

        $validated['is_client'] = true;
        // Si viene razón social es jurídica, de lo contrario natural
        $validated['regime_type'] = $request->filled('company_name') ? 'RESPONSABLE_IVA' : 'NO_RESPONSABLE';

        $customer = Contact::create($validated);

        return response()->json([
            'success' => true,
            'customer' => $customer
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => ['required', 'exists:contacts,id'],
            'sale_type' => ['required', 'in:CONTADO,SEPARE,CREDITO'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'exists:products,id'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.discount_p' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'payments' => ['nullable', 'array'],
            'payments.*.method' => ['required_with:payments', 'in:EFECTIVO,TRANSFERENCIA,TARJETA_DEBITO,TARJETA_CREDITO'],
            'payments.*.amount' => ['required_with:payments', 'numeric', 'min:0'],
            'payments.*.received_amount' => ['nullable', 'numeric', 'min:0'],
            'payments.*.reference' => ['nullable', 'string', 'max:100'],
        ]);

        // 👇 NUEVO: las ventas a Crédito/Separe no pueden quedar a nombre del cliente genérico
        if (in_array($validated['sale_type'], ['CREDITO', 'SEPARE'])) {
            $customer = Contact::find($validated['customer_id']);
            if (!$customer || $customer->document_number === '222222222222') {
                return response()->json([
                    'message' => 'Las ventas a Crédito o Plan Separe requieren un cliente registrado (no el consumidor final genérico).'
                ], 422);
            }
        }

        // 👇 CAMBIADO: de DB::transaction(closure) a transacción manual, para poder
        //    hacer rollBack() explícito antes de cada salida por error y no "quemar"
        //    el consecutivo DIAN cuando la venta no se completa.
        DB::beginTransaction();

        try {
            // A. Validar y bloquear la resolución DIAN para evitar colisiones de numeración
            $resolution = DianResolution::where('is_active', true)
                ->where('current_number', '<', DB::raw('to_number'))
                ->where('date_to', '>=', now()->toDateString())
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            if (!$resolution) {
                DB::rollBack();
                return response()->json([
                    'message' => 'No hay una resolución DIAN activa o se han agotado los consecutivos.'
                ], 422);
            }

            // Incrementar consecutivo
            $resolution->current_number += 1;
            $resolution->save();

            $invoiceNumber = ($resolution->prefix ? $resolution->prefix . '-' : '') . $resolution->current_number;

            // Totales de la cabecera
            $subtotalGlobal = 0;
            $discountGlobal = 0;
            $taxGlobal = 0;

            // B. Procesar temporalmente los items para calcular totales puros del servidor
            $processedItems = [];
            foreach ($request->items as $item) {
                $product = Product::findOrFail($item['id']);
                $qty = $item['qty'];
                $discPercent = $item['discount_p'] ?? 0;

                // 👇 CAMBIADO: rollBack() antes de responder, para no dejar el
                //    consecutivo DIAN incrementado sin una venta real detrás.
                if ($product->manage_stock && $product->stock < $qty) {
                    DB::rollBack();
                    return response()->json([
                        'message' => "Stock insuficiente para el producto '{$product->name}'. Disponible: {$product->stock}."
                    ], 422);
                }

                // Cálculos Financieros bajo esquema de la migración
                $price = $product->price_excluding_tax;
                $subtotalItemRaw = $price * $qty;

                $discAmount = $subtotalItemRaw * ($discPercent / 100);
                $subtotalConDescuento = $subtotalItemRaw - $discAmount;

                $taxAmount = $subtotalConDescuento * ($product->tax_rate / 100);
                $subtotalFinalItem = $subtotalConDescuento + $taxAmount;

                $subtotalGlobal += $subtotalItemRaw;
                $discountGlobal += $discAmount;
                $taxGlobal += $taxAmount;

                $processedItems[] = [
                    'product_id' => $product->id,
                    'product_model' => $product,
                    'quantity' => $qty,
                    'price' => $price,
                    'discount_percentage' => $discPercent,
                    'discount_amount' => $discAmount,
                    'tax_percentage' => $product->tax_rate,
                    'tax_amount' => $taxAmount,
                    'subtotal' => $subtotalFinalItem
                ];
            }

            $totalGlobal = ($subtotalGlobal - $discountGlobal) + $taxGlobal;

            // C. Reglas de negocio por tipo de venta — SIEMPRE calculadas en servidor
            $paidAmount = collect($request->payments ?? [])->sum('amount');

            // 👇 CAMBIADO: rollBack() antes de responder
            if ($request->sale_type === 'CONTADO' && round($paidAmount, 2) < round($totalGlobal, 2)) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Una venta de Contado requiere el pago completo del total.'
                ], 422);
            }

            // 👇 CAMBIADO: rollBack() antes de responder
            if ($request->sale_type === 'SEPARE' && $paidAmount <= 0) {
                DB::rollBack();
                return response()->json([
                    'message' => 'El Plan Separe requiere un abono inicial mayor a $0.'
                ], 422);
            }

            // 👇 QUITADO: el primer $paymentStatus = match(true) {...} era código muerto
            //    (se sobreescribía de inmediato con el siguiente match). Se dejó un solo bloque.
            $paymentStatus = match ($request->sale_type) {
                'CONTADO' => 'PAGADA',
                'SEPARE'  => 'SEPARE',
                'CREDITO' => $paidAmount >= $totalGlobal ? 'PAGADA' : 'PENDIENTE',
            };

            // D. Crear la Cabecera de la Venta (Sales)
            $sale = Sale::create([
                'dian_resolution_id' => $resolution->id,
                'invoice_number'     => $invoiceNumber,
                'customer_id'        => $request->customer_id,
                'user_id'            => $request->user()->id,
                'subtotal'           => $subtotalGlobal,
                'discount_total'     => $discountGlobal,
                'tax_total'          => $taxGlobal,
                'total'              => $totalGlobal,
                'sale_type'          => $request->sale_type,
                'payment_status'     => $paymentStatus,
            ]);

            // E. Guardar detalles de venta y afectar inventario/Kardex
            foreach ($processedItems as $pItem) {
                SaleDetail::create([
                    'sale_id'             => $sale->id,
                    'product_id'          => $pItem['product_id'],
                    'quantity'            => $pItem['quantity'],
                    'price'               => $pItem['price'],
                    'discount_percentage' => $pItem['discount_percentage'],
                    'discount_amount'     => $pItem['discount_amount'],
                    'tax_percentage'      => $pItem['tax_percentage'],
                    'tax_amount'          => $pItem['tax_amount'],
                    'subtotal'            => $pItem['subtotal'],
                ]);

                $product = $pItem['product_model'];

                if ($product->manage_stock) {
                    $product->decrement('stock', $pItem['quantity']);
                    $newStock = $product->stock;

                    KardexMovement::create([
                        'product_id'         => $product->id,
                        'movable_type'       => Sale::class,
                        'movable_id'         => $sale->id,
                        'type'               => 'SALIDA',
                        'concept'            => 'VENTA',
                        'quantity'           => $pItem['quantity'],
                        'price_unit'         => $pItem['price'],
                        'total'              => $pItem['subtotal'],
                        'balance_quantity'   => $newStock,
                        'balance_price_unit' => $product->average_cost,
                        'balance_total'      => $newStock * $product->average_cost,
                    ]);
                }
            }

            // F. Guardar Métodos de Pago Mixtos (SalePayments) — solo si vienen
            foreach ($request->payments ?? [] as $payment) {
                $isCash = $payment['method'] === 'EFECTIVO';
                $received = $isCash ? ($payment['received_amount'] ?? $payment['amount']) : $payment['amount'];
                $change = $isCash ? max(0, $received - $payment['amount']) : 0;

                SalePayment::create([
                    'sale_id'               => $sale->id,
                    'payment_method'        => $payment['method'],
                    'amount'                => $payment['amount'],
                    'received_amount'       => $received,
                    'change_amount'         => $change,
                    'transaction_reference' => $payment['reference'] ?? null,
                ]);
            }

            // G. Generar Cuenta por Cobrar si la venta queda con saldo pendiente
            $pendingBalance = $totalGlobal - $paidAmount;

            if (in_array($request->sale_type, ['CREDITO', 'SEPARE']) && $pendingBalance > 0) {
                AccountReceivable::create([
                    'sale_id'         => $sale->id,
                    'customer_id'     => $sale->customer_id,
                    'original_amount' => $totalGlobal,
                    'balance'         => $pendingBalance,
                    'due_date'        => now()->addDays($request->sale_type === 'SEPARE' ? 15 : 30),
                    'status'          => 'PENDIENTE',
                ]);
            }

            // 👇 NUEVO: commit explícito antes de responder con éxito
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Factura {$invoiceNumber} procesada correctamente.",
                'invoice_number' => $invoiceNumber,
                'sale_id' => $sale->id
            ]);

        } catch (\Exception $e) {
            // 👇 NUEVO: red de seguridad para cualquier error inesperado no contemplado arriba
            //    (ej. fallo de base de datos a mitad de camino)
            DB::rollBack();
            Log::error('Error al procesar venta POS: ' . $e->getMessage());
            return response()->json([
                'message' => 'No se pudo procesar la venta. Intenta de nuevo.'
            ], 500);
        }
    }

    public function searchCustomers($tenant, Request $request)
    {
        try {
            $search = $request->get('q');

            if (empty($search)) {
                return response()->json([]);
            }

            // Realizamos la consulta buscando en los campos reales de la tabla contacts
            $customers = Contact::where('is_client', true)
                ->where(function($query) use ($search) {
                    $query->where('first_name', 'LIKE', "%{$search}%")
                        ->orWhere('last_name', 'LIKE', "%{$search}%")
                        ->orWhere('company_name', 'LIKE', "%{$search}%")
                        ->orWhere('document_number', 'LIKE', "%{$search}%");
                })
                ->limit(10)
                ->get(['id', 'first_name', 'last_name', 'company_name', 'document_number']);

            // Mapeamos los resultados para adjuntar un "name" dinámico que use Vue sin romper nada
            $formattedCustomers = $customers->map(function($customer) {
                $fullName = $customer->company_name
                    ? $customer->company_name
                    : trim($customer->first_name . ' ' . $customer->last_name);

                return [
                    'id'              => $customer->id,
                    'name'            => $fullName ?: 'Cliente sin nombre',
                    'first_name'      => $customer->first_name,
                    'last_name'       => $customer->last_name,
                    'company_name'    => $customer->company_name,
                    'document_number' => $customer->document_number,
                ];
            });

            return response()->json($formattedCustomers);

        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }
}
