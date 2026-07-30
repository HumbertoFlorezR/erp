<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\Tenant\DianResolution;
use App\Models\Tenant\KardexMovement;
use App\Models\Tenant\Product;
use App\Models\Tenant\Sale;
use App\Models\Tenant\SaleDetail;
use App\Models\Tenant\SalePayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
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

    /**
     * 4. PROCESAR LA VENTA (La operación del negocio).
     *
    */
    /* public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => ['required', 'exists:contacts,id'],
            'sale_type' => ['required', 'in:CONTADO,SEPARE,CREDITO'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'exists:products,id'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.discount_p' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'payments' => ['required', 'array', 'min:1'],
            'payments.*.method' => ['required', 'in:EFECTIVO,TRANSFERENCIA,TARJETA_DEBITO,TARJETA_CREDITO'],
            'payments.*.amount' => ['required', 'numeric', 'min:0'],
            'payments.*.received_amount' => ['nullable', 'numeric', 'min:0'],
            'payments.*.reference' => ['nullable', 'string', 'max:100'],
        ]);

        return DB::transaction(function () use ($request, $validated) {

            // A. Validar y bloquear la resolución DIAN para evitar colisiones de numeración
            $resolution = DianResolution::where('is_active', true)
                ->lockForUpdate()
                ->first();

            if (!$resolution || $resolution->current_number >= $resolution->to_number) {
                return redirect()->back()->withErrors(['error' => 'No hay una resolución DIAN activa o se han agotado los consecutivos.']);
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
                $discPercent = $item['discount_p'];

                // Cálculos Financieros bajo esquema de la migración
                $price = $product->price_excluding_tax; // Base gravable
                $subtotalItemRaw = $price * $qty;

                $discAmount = $subtotalItemRaw * ($discPercent / 100);
                $subtotalConDescuento = $subtotalItemRaw - $discAmount;

                $taxAmount = $subtotalConDescuento * ($product->tax_rate / 100);
                $subtotalFinalItem = $subtotalConDescuento + $taxAmount;

                // Acumuladores globales
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

            // C. Crear la Cabecera de la Venta (Sales)
            $sale = Sale::create([
                'dian_resolution_id' => $resolution->id,
                'invoice_number'     => $invoiceNumber,
                'customer_id'        => $request->customer_id,
                'user_id'            => $request->user()->id, // Captura el usuario que vende
                'subtotal'           => $subtotalGlobal,
                'discount_total'     => $discountGlobal,
                'tax_total'          => $taxGlobal,
                'total'              => $totalGlobal,
                'payment_status'     => 'PAGADA'
            ]);

            // D. Guardar detalles de venta y afectar inventario/Kardex
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

                // Descontar Stock si el producto lo requiere
                if ($product->manage_stock) {
                    $oldStock = $product->stock;
                    $product->decrement('stock', $pItem['quantity']);
                    $newStock = $product->stock;

                    // 📊 Registrar movimiento en el Kardex (Polimórfico con sales)
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

            // E. Guardar Métodos de Pago Mixtos (SalePayments)
            foreach ($request->payments as $payment) {
                // Cálculo de vuelto para efectivo
                $received = $payment['received_amount'] ?? $payment['amount'];
                $change = $received - $payment['amount'];

                SalePayment::create([
                    'sale_id'               => $sale->id,
                    'payment_method'        => $payment['method'],
                    'amount'                => $payment['amount'],
                    'received_amount'       => $received,
                    'change_amount'         => $change > 0 ? $change : 0,
                    'transaction_reference' => $payment['reference'] ?? null, // Voucher del datáfono o Nequi
                ]);
            }

            // MODIFICACIÓN AQUÍ: Responder directamente en formato JSON
            return response()->json([
                'success' => true,
                'message' => "Factura {$invoiceNumber} procesada correctamente.",
                'invoice_number' => $invoiceNumber,
                'sale_id' => $sale->id
            ]);
        });
    } */

/*
    public function store(Request $request)
    {
        // 1. Validar los datos de la venta
        $validated = $request->validate([
            'customer_id'    => 'required|exists:contacts,id',
            'payment_status' => 'required|in:PAGADA,PENDIENTE,SEPARE',
            'cart'           => 'required|array|min:1',
            'cart.*.id'      => 'required|exists:products,id',
            'cart.*.qty'     => 'required|numeric|min:0.01',
            'payments'       => 'required|array|min:1',
        ]);

        // 2. Obtener resolución activa de la DIAN
        $resolution = DianResolution::where('is_active', true)
            ->where('current_number', '<', DB::raw('to_number'))
            ->where('date_to', '>=', now()->toDateString())
            ->first();

        if (!$resolution) {
            return back()->withErrors(['resolution' => 'No hay una resolución DIAN activa o se han agotado los números.']);
        }

        // 3. Ejecutar la transacción
        DB::transaction(function () use ($request, $resolution, &$sale) {
            // Incrementar el consecutivo
            $resolution->increment('current_number');
            $invoiceNumber = ($resolution->prefix ? $resolution->prefix . '-' : '') . $resolution->current_number;

            // Crear la cabecera de la venta
            $sale = Sale::create([
                'dian_resolution_id' => $resolution->id,
                'invoice_number'     => $invoiceNumber,
                'customer_id'        => $request->customer_id,
                'user_id'            => Auth::id() ?? 1,
                'subtotal'           => $request->subtotal,
                'discount_total'     => $request->discount_total ?? 0,
                'tax_total'          => $request->tax_total ?? 0,
                'total'              => $request->total,
                'payment_status'     => $request->payment_status,
            ]);

            // Crear detalles de productos y movimientos de Kardex
            foreach ($request->cart as $item) {
                SaleDetail::create([
                    'sale_id'             => $sale->id,
                    'product_id'          => $item['id'],
                    'quantity'            => $item['qty'],
                    'price'               => $item['price_excluding_tax'],
                    'discount_percentage' => $item['discount_p'] ?? 0,
                    'discount_amount'     => $item['discount_amount'] ?? 0,
                    'tax_percentage'      => $item['tax_rate'] ?? 0,
                    'tax_amount'          => $item['tax_amount'] ?? 0,
                    'subtotal'            => $item['subtotal'] ?? ($item['price_excluding_tax'] * $item['qty']),
                ]);

                // Actualizar stock e inventario Kardex
                $product = Product::findOrFail($item['id']);
                $newStock = $product->stock - $item['qty'];

                KardexMovement::create([
                    'product_id'         => $product->id,
                    'movable_type'       => Sale::class,
                    'movable_id'         => $sale->id,
                    'type'               => 'SALIDA',
                    'concept'            => 'VENTA POS',
                    'quantity'           => $item['qty'],
                    'price_unit'         => $product->cost_price ?? $item['price_excluding_tax'],
                    'total'              => $item['qty'] * ($product->cost_price ?? $item['price_excluding_tax']),
                    'balance_quantity'   => $newStock,
                    'balance_price_unit' => $product->cost_price ?? 0,
                    'balance_total'      => $newStock * ($product->cost_price ?? 0),
                ]);

                $product->update(['stock' => $newStock]);
            }

            // Registrar pagos
            foreach ($request->payments as $payment) {
                SalePayment::create([
                    'sale_id'                => $sale->id,
                    'payment_method'         => $payment['method'],
                    'amount'                 => $payment['amount'],
                    'received_amount'        => $payment['received'] ?? $payment['amount'],
                    'change_amount'          => $payment['change'] ?? 0,
                    'transaction_reference' => $payment['reference'] ?? null,
                ]);
            }
        });

        return redirect()->back()->with('success', 'Venta realizada con éxito');
    } */
    /**
     * 4. PROCESAR LA VENTA (La operación del negocio).
     * Soporta CONTADO, CREDITO y SEPARE.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => ['required', 'exists:contacts,id'],
            'sale_type' => ['required', 'in:CONTADO,SEPARE,CREDITO'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'exists:products,id'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.discount_p' => ['nullable', 'numeric', 'min:0', 'max:100'],
            // 👇 Ya no exigimos min:1 aquí: CREDITO puede llegar sin abono (payments = [])
            'payments' => ['nullable', 'array'],
            'payments.*.method' => ['required_with:payments', 'in:EFECTIVO,TRANSFERENCIA,TARJETA_DEBITO,TARJETA_CREDITO'],
            'payments.*.amount' => ['required_with:payments', 'numeric', 'min:0'],
            'payments.*.received_amount' => ['nullable', 'numeric', 'min:0'],
            'payments.*.reference' => ['nullable', 'string', 'max:100'],
        ]);

        return DB::transaction(function () use ($request, $validated) {

            // A. Validar y bloquear la resolución DIAN para evitar colisiones de numeración
            // 👇 Se replican los MISMOS filtros que index() (antes solo filtraba is_active)
            $resolution = DianResolution::where('is_active', true)
                ->where('current_number', '<', DB::raw('to_number'))
                ->where('date_to', '>=', now()->toDateString())
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            // 👇 Antes usaba redirect()->back(), rompía el axios.post() del frontend (esperaba JSON)
            if (!$resolution) {
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
                // 👇 null-safe: antes explotaba si discount_p venía null
                $discPercent = $item['discount_p'] ?? 0;

                // 👇 Chequeo de stock antes de comprometer la venta (antes no existía)
                if ($product->manage_stock && $product->stock < $qty) {
                    return response()->json([
                        'message' => "Stock insuficiente para el producto '{$product->name}'. Disponible: {$product->stock}."
                    ], 422);
                }

                // Cálculos Financieros bajo esquema de la migración
                $price = $product->price_excluding_tax; // Base gravable
                $subtotalItemRaw = $price * $qty;

                $discAmount = $subtotalItemRaw * ($discPercent / 100);
                $subtotalConDescuento = $subtotalItemRaw - $discAmount;

                $taxAmount = $subtotalConDescuento * ($product->tax_rate / 100);
                $subtotalFinalItem = $subtotalConDescuento + $taxAmount;

                // Acumuladores globales
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

            // C. Reglas de negocio por tipo de venta — SIEMPRE calculadas en servidor,
            //    nunca confiando en lo que el cliente diga que pagó.
            $paidAmount = collect($request->payments ?? [])->sum('amount');

            if ($request->sale_type === 'CONTADO' && round($paidAmount, 2) < round($totalGlobal, 2)) {
                return response()->json([
                    'message' => 'Una venta de Contado requiere el pago completo del total.'
                ], 422);
            }

            if ($request->sale_type === 'SEPARE' && $paidAmount <= 0) {
                return response()->json([
                    'message' => 'El Plan Separe requiere un abono inicial mayor a $0.'
                ], 422);
            }

            // CREDITO no exige mínimo: puede llegar con $0 de abono.

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
                'sale_type'          => $request->sale_type,   // 👈 nuevo campo
                'payment_status'     => $paymentStatus,        // 👈 ya no está hardcodeado
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

                // Descontar Stock si el producto lo requiere
                if ($product->manage_stock) {
                    $product->decrement('stock', $pItem['quantity']);
                    $newStock = $product->stock;

                    // 📊 Registrar movimiento en el Kardex (Polimórfico con sales)
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
                // 👇 El "vuelto" solo tiene sentido en EFECTIVO; en tarjeta/transferencia
                //    el campo "abono/recibido" no representa efectivo entregado físicamente.
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

            return response()->json([
                'success' => true,
                'message' => "Factura {$invoiceNumber} procesada correctamente.",
                'invoice_number' => $invoiceNumber,
                'sale_id' => $sale->id
            ]);
        });
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
