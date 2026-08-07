<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\Tenant\AccountPayable;
use App\Models\Tenant\KardexMovement;
use App\Models\Tenant\Product;
use App\Models\Tenant\PurchaseInvoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class PurchaseInvoiceController extends Controller
{
    public function index(Request $request)
    {
        $query = PurchaseInvoice::with('provider');

        if ($request->has('search')) {
            $search = $request->search;
            $query->where('invoice_number', 'like', '%'.$search.'%')
                ->orWhereHas('provider', function($q) use ($search) {
                    // 🛠️ CORRECCIÓN: Buscamos sobre las columnas reales del tercero
                    $q->where('company_name', 'like', '%'.$search.'%')
                      ->orWhere('first_name', 'like', '%'.$search.'%')
                      ->orWhere('last_name', 'like', '%'.$search.'%')
                      ->orWhere('document_number', 'like', '%'.$search.'%');
                });
        }

        $invoices = $query->orderBy('issue_date', 'desc')->paginate(10);

        // 🌟 CONSTRUCCIÓN MANUAL Y SEGURA DE LAS RUTAS PARA EL FRONTEND
        $custom_routes = [
            'url' => url('/'),
            'routes' => [
                'purchase-invoices.index'  => ['uri' => 'purchase-invoices'],
                'purchase-invoices.create' => ['uri' => 'purchase-invoices/create'],
                'purchase-invoices.show'   => ['uri' => 'purchase-invoices/{id}'],
                'purchase-invoices.cancel' => ['uri' => 'purchase-invoices/{id}/cancel'],
            ]
        ];

        return Inertia::render('Purchases/Index', [
            'invoices'   => $invoices,
            'filters'    => $request->only(['search']),
            'ziggy_data' => $custom_routes
        ]);
    }

    public function create()
    {
        $providers = Contact::where('is_supplier', true)
            ->orWhere('is_client', true)
            ->select('id', 'first_name', 'last_name', 'company_name', 'document_number')
            ->get()
            ->map(function ($contact) {
                return [
                    'id'   => $contact->id,
                    'name' => $contact->company_name ?: trim($contact->first_name . ' ' . $contact->last_name),
                    'nit'  => $contact->document_number
                ];
            });

        $products = Product::get()
            ->map(function ($product) {
                return [
                    'id'                  => $product->id,
                    'name'                => $product->name,
                    'code'                => $product->code ?? '', // 🌟 CAMBIADO: De 'sku' a 'code'
                    'price_cost'          => $product->average_cost ?? 0,
                    'costo_promedio'      => $product->average_cost ?? 0,
                    'stock'               => $product->stock ?? 0,
                    'controlar_inventario'=> $product->manage_stock ?? true
                ];
            });

        // 🌟 AGREGAMOS LAS RUTAS MANUALES PARA EL FORMULARIO DE CREACIÓN
        $custom_routes = [
            'url' => url('/'),
            'routes' => [
                'purchase-invoices.index' => ['uri' => 'purchase-invoices'],
                'purchase-invoices.store' => ['uri' => 'purchase-invoices'],
            ]
        ];

        return Inertia::render('Purchases/Create', [
            'providers'  => $providers,
            'products'   => $products,
            'ziggy_data' => $custom_routes // 👈 Enviamos el mapa seguro para que el componente frontend sepa a dónde apuntar
        ]);
    }

    public function store(Request $request)
    {
        // 1. Validación estricta (revisando que todo venga correcto)
        $validated = $request->validate([
            'contact_id'              => 'required|exists:contacts,id',
            'invoice_number'          => 'required|string',
            'issue_date'              => 'required|date',
            'due_date'                => 'required|date',
            'payment_type'            => 'required|in:CONTADO,CREDITO', // 👈 nuevo
            'notes'                   => 'nullable|string',
            'discount'                => 'nullable|numeric|min:0',
            'items'                   => 'required|array|min:1',
            'items.*.product_id'      => 'required|exists:products,id',
            'items.*.quantity'        => 'required|numeric|min:0.0001',
            'items.*.price_unit'      => 'required|numeric|min:0',
            'items.*.tax_rate'        => 'required|numeric',
            'items.*.batch_number'    => 'nullable|string',
            'items.*.expiration_date' => 'nullable|date',
        ]);

        // Iniciamos la transacción en la base de datos del Tenant
        DB::beginTransaction();

        try {
            $subtotalGeneral = 0;
            $taxGeneral = 0;
            $itemsData = [];

            // 2. Pre-calculamos los totales iterando los items
            foreach ($validated['items'] as $item) {
                $subtotalItem = $item['quantity'] * $item['price_unit'];
                $taxItem      = $subtotalItem * ($item['tax_rate'] / 100);

                $subtotalGeneral += $subtotalItem;
                $taxGeneral      += $taxItem;

                // Guardamos temporalmente el arreglo procesado para los detalles
                $itemsData[] = [
                    'product_id'      => $item['product_id'],
                    'quantity'        => $item['quantity'],
                    'price_unit'      => $item['price_unit'],
                    'tax_rate'        => $item['tax_rate'],
                    'tax_amount'      => $taxItem,
                    'subtotal'        => $subtotalItem,
                    'total'           => $subtotalItem + $taxItem,
                    'batch_number'    => $item['batch_number'] ?? null,
                    'expiration_date' => $item['expiration_date'] ?? null,
                ];
            }

            $discount = $validated['discount'] ?? 0;
            $totalGeneral = ($subtotalGeneral + $taxGeneral) - $discount;

            // 3. Grabamos la Factura de Compra Principal
            $invoice = PurchaseInvoice::create([
                'contact_id'     => $validated['contact_id'],
                'invoice_number' => $validated['invoice_number'],
                'issue_date'     => $validated['issue_date'],
                'due_date'       => $validated['due_date'],
                'subtotal'       => $subtotalGeneral,
                'discount'       => $discount,
                'tax_amount'     => $taxGeneral,
                'total'          => $totalGeneral,
                'payment_type'   => $validated['payment_type'],                                   // 👈 nuevo
                'payment_status' => $validated['payment_type'] === 'CONTADO' ? 'PAGADA' : 'PENDIENTE', // 👈 cambiado
                'notes'          => $validated['notes'] ?? null,
            ]);

            // 👇 NUEVO: solo generamos cuenta por pagar si la compra es a crédito
            if ($validated['payment_type'] === 'CREDITO') {
                AccountPayable::create([
                    'purchase_invoice_id' => $invoice->id,
                    'provider_id'         => $invoice->contact_id,
                    'original_amount'     => $totalGeneral,
                    'balance'             => $totalGeneral,
                    'due_date'            => $validated['due_date'],
                    'status'              => 'PENDIENTE',
                ]);
            }

            // 4. Grabamos los detalles e impactamos el inventario / Kardex
            foreach ($itemsData as $data) {
                // Guardamos el ítem asociado a la factura
                $invoice->items()->create($data);

                // Buscamos el producto para actualizar Kardex y Stock
                $product = Product::find($data['product_id']);
                if ($product && $product->manage_stock) {
                    $stockAnterior = $product->stock ?? 0;
                    $costoAnterior = $product->average_cost ?? 0;

                    $nuevoStock = $stockAnterior + $data['quantity'];

                    // Cálculo de Costo Promedio Ponderado
                    $nuevoCostoPromedio = $costoAnterior;
                    if ($nuevoStock > 0) {
                        $nuevoCostoPromedio = (($stockAnterior * $costoAnterior) + ($data['quantity'] * $data['price_unit'])) / $nuevoStock;
                    }

                    // Registramos el movimiento en el Kardex
                    KardexMovement::create([
                        'product_id'         => $product->id,
                        'movable_type'       => PurchaseInvoice::class,
                        'movable_id'         => $invoice->id,
                        'batch_number'       => $data['batch_number'],
                        'expiration_date'    => $data['expiration_date'],
                        'type'               => 'ENTRADA',
                        'concept'            => 'COMPRA',
                        'quantity'           => $data['quantity'],
                        'price_unit'         => $data['price_unit'],
                        'total'              => $data['subtotal'],
                        'balance_quantity'   => $nuevoStock,
                        'balance_price_unit' => $nuevoCostoPromedio,
                        'balance_total'      => $nuevoStock * $nuevoCostoPromedio,
                    ]);

                    // Actualizamos el maestro de productos con stock y costo actualizados
                    $product->update([
                        'stock'        => $nuevoStock,
                        'average_cost' => $nuevoCostoPromedio
                    ]);
                }
            }

            // 🌟 EL PUNTO CLAVE: Confirmamos y asentamos los datos permanentemente en la BD
            DB::commit();

            return redirect()->route('purchase-invoices.create')
                            ->with('success', 'Factura de compra registrada e inventario actualizado con éxito.');

        } catch (\Exception $e) {
            // Deshacemos cualquier inserción a medias en la base de datos
            DB::rollBack();

            Log::error('Error al registrar compra: ' . $e->getMessage());
            throw \Illuminate\Validation\ValidationException::withMessages([
                'error' => 'No se pudo registrar la compra. Por favor intenta de nuevo.'
            ]);
        }
    }

    public function cancel($tenant, $id)
    {
        DB::beginTransaction();

        try {
            $invoice = PurchaseInvoice::with('items')->findOrFail($id);

            if ($invoice->payment_status === 'ANULADA') {
                return redirect()->back()->withErrors(['error' => 'Esta factura ya se encuentra anulada.']);
            }

            foreach ($invoice->items as $item) {
                $product = Product::find($item->product_id);

                if ($product && $product->manage_stock) {
                    $stockActual = $product->stock ?? 0;
                    $costoPromedioActual = $product->average_cost ?? $product->last_purchase_price ?? 0;

                    $cantidadSalida = $item->quantity;
                    $nuevoStock = $stockActual - $cantidadSalida;
                    $nuevoCostoPromedio = $nuevoStock > 0 ? $costoPromedioActual : 0;

                    KardexMovement::create([
                        'product_id'         => $product->id,
                        'movable_type'       => PurchaseInvoice::class,
                        'movable_id'         => $invoice->id,
                        'batch_number'       => $item->batch_number,
                        'expiration_date'    => $item->expiration_date,
                        'type'               => 'SALIDA',
                        'concept'            => 'ANULACION_COMPRA',
                        'quantity'           => $cantidadSalida,
                        'price_unit'         => $item->price_unit,
                        'total'              => $item->subtotal,
                        'balance_quantity'   => $nuevoStock,
                        'balance_price_unit' => $nuevoCostoPromedio,
                        'balance_total'      => $nuevoStock * $nuevoCostoPromedio,
                    ]);

                    $product->update([
                        'stock' => $nuevoStock
                    ]);
                }
            }

            $invoice->update([
                'payment_status' => 'ANULADA',
                'notes' => $invoice->notes . " | [ANULADA EL " . now()->format('Y-m-d H:i') . "]"
            ]);

            // 👇 NUEVO: si tenía cuenta por pagar asociada, también se anula
            $accountPayable = AccountPayable::where('purchase_invoice_id', $invoice->id)->first();
            if ($accountPayable) {
                $accountPayable->update(['status' => 'ANULADA']);
            }

            DB::commit();

            return redirect()->route('purchase-invoices.index')
                             ->with('success', 'Factura de compra anulada e inventario reversado correctamente.');

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withErrors(['error' => 'Error al anular la factura: ' . $e->getMessage()]);
        }
    }
    /**
     * Visualizar el detalle completo de una factura de compra (Solo Lectura)
     */
    public function show($tenant,$purchase_invoice)
    {
        $id = $purchase_invoice;
        // Cargamos la factura con su proveedor y sus ítems enlazados al producto correspondiente
        $invoice = PurchaseInvoice::with(['provider', 'items.product'])->findOrFail($id);

        // Estructura segura de rutas dinámicas requerida para tus componentes
        $custom_routes = [
            'url' => url('/'),
            'routes' => [
                'purchase-invoices.index'  => ['uri' => 'purchase-invoices'],
                'purchase-invoices.create' => ['uri' => 'purchase-invoices/create'],
                'purchase-invoices.show'   => ['uri' => 'purchase-invoices/{id}'],
            ]
        ];

        return Inertia::render('Purchases/Show', [
            'invoice' => $invoice,
            'ziggy_data' => $custom_routes
        ]);
    }
}
