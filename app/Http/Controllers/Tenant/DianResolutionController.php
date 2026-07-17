<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\DianResolution;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DianResolutionController extends Controller
{
    public function index()
    {
        $resolutions = DianResolution::orderBy('created_at', 'desc')->get();
        return Inertia::render('Settings/ResolutionsIndex', [
            'resolutions' => $resolutions
        ]);
    }

    public function store(Request $request)
    {
        // 1. Validación corregida
        $validated = $request->validate([
            'prefix'             => 'nullable|string|max:10',
            'resolution_number'  => 'required|string|max:50',
            'from_number'        => 'required|integer|min:1',
            'to_number'          => 'required|integer|gt:from_number',
            'current_number'     => 'required|integer|min:0', // 🟢 Permite iniciar desde 0
            'date_from'          => 'required|date',
            'date_to'            => 'required|date|after:date_from',
        ]);

        // 2. Convertir prefix a mayúsculas
        if (!empty($validated['prefix'])) {
            $validated['prefix'] = strtoupper($validated['prefix']);
        }

        // 3. Desactivar anteriores resoluciones
        DianResolution::where('is_active', true)->update(['is_active' => false]);

        // 4. Crear la nueva resolución
        DianResolution::create(array_merge($validated, ['is_active' => true]));

        // 4. 🌟 SOLUCIÓN: Redirección relativa limpia sin requerir el parámetro tenant
        return redirect('/settings/resolutions')->with('success', 'Resolución registrada con éxito.');
    }

    public function toggleActive($id)
    {
        $resolution = DianResolution::findOrFail($id);

        if (!$resolution->is_active) {
            DianResolution::where('is_active', true)->update(['is_active' => false]);
            $resolution->is_active = true;
        } else {
            $resolution->is_active = false;
        }

        $resolution->save();
        return redirect()->back();
    }
}
