<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Product;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProductController extends Controller
{
    /**
     * Listar los productos y servicios con filtros reactivos.
     */
    public function index(Request $request)
    {
        $query = Product::query();

        // 🔍 Filtro 1: Búsqueda global por Nombre o Código (SKU)
        if ($request->has('search') && $request->input('search') != '') {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        // 🏷️ Filtro 2: Por Tipo (PRODUCTO o SERVICIO)
        if ($request->has('type') && in_array($request->input('type'), ['PRODUCTO', 'SERVICIO'])) {
            $query->where('type', $request->input('type'));
        }

        // ⚠️ Filtro 3: Alertas de Stock Bajo (Criticos)
        if ($request->has('low_stock') && $request->boolean('low_stock')) {
            $query->where('type', 'PRODUCTO')
                  ->where('manage_stock', true)
                  ->whereRaw('stock <= minimum_stock');
        }

        // Paginamos los datos y mapeamos la respuesta para Inertia
        $products = $query->latest()
            ->paginate(10)
            ->withQueryString()
            ->through(fn ($product) => [
                'id'                  => $product->id,
                'type'                => $product->type,
                'code'                => $product->code,
                'barcode'             => $product->barcode,       // 👈 Mapeado
                'name'                => $product->name,
                'description'         => $product->description,
                'average_cost'        => $product->average_cost,
                'last_purchase_price' => $product->last_purchase_price,
                'price_excluding_tax' => $product->price_excluding_tax,
                'tax_rate'            => $product->tax_rate,
                'tax_type'            => $product->tax_type,
                'discount_rate'       => $product->discount_rate,  // 👈 Mapeado
                'stock'               => $product->stock,
                'minimum_stock'       => $product->minimum_stock,
                'manage_stock'        => $product->manage_stock,
                'is_perishable'       => $product->is_perishable,
                'unit_measure_code'   => $product->unit_measure_code,
                'dian_code'           => $product->dian_code,
                'is_active'           => $product->is_active,
                'is_low_stock'        => $product->is_low_stock,
                'profit_margin'       => $product->profit_margin,
                'price_including_tax' => $product->price_including_tax,
            ]);

        return Inertia::render('Tenant/Products/Index', [
            'products' => $products,
            'filters'  => $request->only(['search', 'type', 'low_stock']),
        ]);
    }

    /**
     * Almacenar un nuevo producto o servicio.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'type'                => 'required|in:PRODUCTO,SERVICIO',
            'code'                => 'nullable|string|max:50',
            'barcode'             => 'nullable|string|max:50|unique:products,barcode', // 👈 Validado
            'name'                => 'required|string|max:150',
            'description'         => 'nullable|string',                 // 👈 agregar
            'price_excluding_tax' => 'required|numeric|min:0',
            'tax_rate'            => 'required|numeric|min:0|max:100',
            'tax_type'            => 'required|in:GRAVADO,EXENTO,EXCLUIDO',
            'discount_rate'       => 'nullable|numeric|min:0|max:100',                 // 👈 Validado
            'minimum_stock'       => 'nullable|numeric|min:0',           // 👈 agregar
            'manage_stock'        => 'boolean',
            'is_perishable'       => 'boolean',
            'unit_measure_code'   => 'required|string|max:5',
        ]);

        if ($validated['type'] === 'SERVICIO') {
            $validated['manage_stock'] = false;
            $validated['minimum_stock'] = 0;
            $validated['is_perishable'] = false;
            $validated['unit_measure_code'] = 'WSD';
        }

        $product = Product::create([
            'type'                 => $validated['type'] ?? 'PRODUCTO',
            'name'                 => $validated['name'],
            'code'                 => $validated['code'] ?? 'GEN-' . time(),
            'barcode'              => $validated['barcode'] ?? null,                  // 👈 Guardado
            'price_excluding_tax'  => $validated['price_excluding_tax'] ?? 0,
            'average_cost'         => $validated['price_excluding_tax'] ?? 0,
            'tax_rate'             => $validated['tax_rate'] ?? 19,
            'tax_type'             => $validated['tax_type'] ?? 'GRAVADO',
            'discount_rate'        => $validated['discount_rate'] ?? 0.00,            // 👈 Guardado
            'stock'                => 0,
            'manage_stock'         => $validated['manage_stock'] ?? true,
            'unit_measure_code'    => $validated['unit_measure_code'] ?? '94',
            'description'          => $validated['description'] ?? null,
            'minimum_stock'        => $validated['minimum_stock'] ?? 0,
        ]);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'product' => [
                    'id'                  => $product->id,
                    'name'                => $product->name,
                    'code'                => $product->code,
                    'price_excluding_tax' => $product->price_excluding_tax,
                    'manage_stock'        => $product->manage_stock,
                    'stock'               => $product->stock,
                ]
            ]);
        }

        return redirect()->back()->with('success', 'Ítem creado correctamente.');
    }

    /**
     * Alternar de forma rápida el estado Activo/Inactivo (PATCH)
     */
    public function toggleStatus($tenant, $id)
    {
        // Recuerda: El primer parámetro siempre recibe el subdominio del inquilino de forma transparente
        $product = Product::findOrFail($id);
        $product->update([
            'is_active' => !$product->is_active
        ]);

        return redirect()->back()->with('success', 'Estado del ítem modificado.');
    }
    /**
     * Actualizar un producto o servicio existente.
     */
    public function update(Request $request, $tenant, $id)
    {
        $product = Product::findOrFail($id);

        $validated = $request->validate([
            'type'                => 'required|in:PRODUCTO,SERVICIO',
            'code'                => 'nullable|string|max:50',
            'barcode'             => 'nullable|string|max:50|unique:products,barcode,' . $product->id, // 👈 Evita error de duplicado al editar
            'name'                => 'required|string|max:150',
            'description'         => 'nullable|string',
            'price_excluding_tax' => 'required|numeric|min:0',
            'tax_rate'            => 'required|numeric|min:0|max:100',
            'tax_type'            => 'required|in:GRAVADO,EXENTO,EXCLUIDO',
            'discount_rate'       => 'nullable|numeric|min:0|max:100',                                // 👈 Validado
            'minimum_stock'       => 'nullable|numeric|min:0',
            'manage_stock'        => 'boolean',
            'is_perishable'       => 'boolean',
            'unit_measure_code'   => 'required|string|max:5',
        ]);

        if ($validated['type'] === 'SERVICIO') {
            $validated['manage_stock'] = false;
            $validated['minimum_stock'] = 0;
            $validated['is_perishable'] = false;
            $validated['unit_measure_code'] = 'WSD';
        }

        $product->update($validated);

        return redirect()->back()->with('success', 'Ítem actualizado correctamente.');
    }

    /**
     * Crear un producto rápido desde el formulario de compras (Soporta Axios y Formularios Tradicionales)
     */
    public function storeQuick(Request $request)
    {
        $validated = $request->validate([
            'name'           => 'required|string|max:255',
            'code'           => 'nullable|string|unique:products,code',
            'manage_stock'   => 'required|boolean',
            'price_excluding_tax' => 'nullable|numeric|min:0',
        ]);

        $product = Product::create([
            'name'                => $validated['name'],
            'code'                => $validated['code'] ?? 'GEN-' . time() . rand(10, 99),
            'price_excluding_tax' => $validated['price_excluding_tax'] ?? 0,
            'average_cost'        => $validated['price_excluding_tax'] ?? 0,
            'stock'               => 0,
            'manage_stock'        => $validated['manage_stock'],
            'tax_rate'            => 19,
            'tax_type'            => 'GRAVADO',
            'unit_measure_code'   => '94',
        ]);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'product' => [
                    'id'                  => $product->id,
                    'name'                => $product->name,
                    'code'                => $product->code,
                    'price_excluding_tax' => $product->price_excluding_tax,
                    'stock'               => 0,
                    'manage_stock'        => $product->manage_stock,
                ]
            ]);
        }

        return redirect()->back()->with('success', 'Producto express creado correctamente.');
    }
}
