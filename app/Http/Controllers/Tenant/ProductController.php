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
                'name'                => $product->name,
                'description'         => $product->description,
                'average_cost'        => $product->average_cost,
                'last_purchase_price' => $product->last_purchase_price,
                'price_excluding_tax' => $product->price_excluding_tax,
                'tax_rate'            => $product->tax_rate,
                'tax_type'            => $product->tax_type,
                'stock'               => $product->stock,
                'minimum_stock'       => $product->minimum_stock,
                'manage_stock'        => $product->manage_stock,
                'is_perishable'       => $product->is_perishable,
                'unit_measure_code'   => $product->unit_measure_code,
                'dian_code'           => $product->dian_code,
                'is_active'           => $product->is_active,
                // Atributos dinámicos añadidos desde el modelo
                'is_low_stock'         => $product->is_low_stock,
                'profit_margin'        => $product->profit_margin,
                'price_including_tax'  => $product->price_including_tax,
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
            'name'                => 'required|string|max:150',
            'description'         => 'nullable|string',
            'price_excluding_tax' => 'required|numeric|min:0',
            'tax_rate'            => 'required|numeric|min:0|max:100',
            'tax_type'            => 'required|in:GRAVADO,EXENTO,EXCLUIDO',
            'minimum_stock'       => 'nullable|numeric|min:0',
            'manage_stock'        => 'boolean',
            'is_perishable'       => 'boolean',
            'unit_measure_code'   => 'required|string|max:5',
        ]);

        // Ajustes automáticos si es un Servicio
        if ($validated['type'] === 'SERVICIO') {
            $validated['manage_stock'] = false;
            $validated['minimum_stock'] = 0;
            $validated['is_perishable'] = false;
            $validated['unit_measure_code'] = 'WSD'; // Estándar de servicio DIAN
        }

        Product::create($validated);

        return redirect()->back()->with('success', 'Ítem registrado con éxito en el catálogo.');
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
            'name'                => 'required|string|max:150',
            'description'         => 'nullable|string',
            'price_excluding_tax' => 'required|numeric|min:0',
            'tax_rate'            => 'required|numeric|min:0|max:100',
            'tax_type'            => 'required|in:GRAVADO,EXENTO,EXCLUIDO',
            'minimum_stock'       => 'nullable|numeric|min:0',
            'manage_stock'        => 'boolean',
            'is_perishable'       => 'boolean',
            'unit_measure_code'   => 'required|string|max:5',
        ]);

        // Forzar limpieza si se transformó o editó como Servicio
        if ($validated['type'] === 'SERVICIO') {
            $validated['manage_stock'] = false;
            $validated['minimum_stock'] = 0;
            $validated['is_perishable'] = false;
            $validated['unit_measure_code'] = 'WSD';
        }

        $product->update($validated);

        return redirect()->back()->with('success', 'Ítem actualizado correctamente.');
    }
}
