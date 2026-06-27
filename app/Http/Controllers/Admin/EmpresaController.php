<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Tenant;
use Inertia\Inertia;

class EmpresaController extends Controller
{
    // Listar las empresas registradas
    public function index()
    {
        $empresas = Tenant::all();
        return Inertia::render('Admin/Empresas/Index', [
            'empresas' => $empresas
        ]);
    }

    // Mostrar el formulario de creación
    public function create()
    {
        return Inertia::render('Admin/Empresas/Create');
    }

    // Guardar la nueva empresa en la BD central y disparar su BD propia
    public function store(Request $request)
    {
        $validated = $request->validate([
            'id' => 'required|alpha_dash|unique:tenants,id',
            'nombre_empresa' => 'required|string|max:255',
            'nit_rut' => 'required|string|unique:tenants,nit_rut',
            'telefono' => 'nullable|string',
            'email_contacto' => 'nullable|email',
        ]);

        try {
            // Esto disparará el TenantObserver::created()
            Tenant::create($validated);
        } catch (\Exception $e) {
            // Si la base de datos falla al crearse, Laravel se detendrá aquí y nos mostrará el porqué
            dd($e->getMessage());
        }

        return redirect()->route('empresas.index')->with('success', 'Empresa creada con éxito.');
    }
}
