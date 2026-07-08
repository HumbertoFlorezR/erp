<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ContactController extends Controller
{
    /**
     * Listado con filtros y buscador dinámico
     */
    public function index(Request $request)
    {
        $query = Contact::query();

        // Filtro por rol (?type=client, ?type=supplier, ?type=employee)
        if ($request->has('type') && in_array($request->type, ['client', 'supplier', 'employee'])) {
            $query->where("is_{$request->type}", true);
        }

        // Buscador general por cédula/NIT o nombre
        if ($request->has('search') && $request->search != '') {
            $search = mb_strtoupper($request->search, 'UTF-8');
            $query->where(function ($q) use ($search) {
                $q->where('document_number', 'like', "%{$search}%")
                  ->orWhere('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('company_name', 'like', "%{$search}%");
            });
        }

        // Paginamos manteniendo los parámetros de búsqueda en la URL
        $contacts = $query->latest()
            ->paginate(10)
            ->withQueryString()
            ->through(fn ($contact) => [
                'id' => $contact->id,
                'document_type' => $contact->document_type,
                'document_number' => $contact->document_number,
                'verification_digit' => $contact->verification_digit,
                'full_name' => $contact->full_name,

                // 🚀 AGREGAR ESTOS CAMPOS PARA QUE SE PUEDAN EDITAR:
                'first_name' => $contact->first_name,
                'last_name' => $contact->last_name,
                'company_name' => $contact->company_name,
                'address' => $contact->address,
                'regime_type' => $contact->regime_type,

                'email' => $contact->email,
                'phone' => $contact->phone,
                'is_client' => (bool)$contact->is_client,
                'is_supplier' => (bool)$contact->is_supplier,
                'is_employee' => (bool)$contact->is_employee,
                'is_active' => $contact->is_active,
            ]);

        return Inertia::render('Tenant/Contacts/Index', [
            'contacts' => $contacts,
            'filters' => $request->only(['type', 'search']),
        ]);
    }

    /**
     * Guardar nuevo tercero
     */
    public function store(Request $request)
    {
        // 1. Validamos todos los campos que pueden venir del formulario completo o express
        $validated = $request->validate([
            'document_type'   => 'required|string|max:20',
            'document_number' => 'required|string|max:50|unique:contacts,document_number',
            'first_name'      => 'nullable|required_without:company_name|string|max:100',
            'last_name'       => 'nullable|required_without:company_name|string|max:100',
            'company_name'    => 'nullable|required_without:first_name|string|max:200',
            'email'           => 'nullable|email|max:150',
            'phone'           => 'nullable|string|max:20',
            'address'         => 'nullable|string|max:255',
            'regime_type'     => 'required|string',
            'is_client'       => 'boolean',
            'is_supplier'     => 'boolean',
            'is_employee'     => 'boolean',
        ]);

        // 2. Creación del registro en la base de datos usando el array $validated
        $contact = Contact::create([
            'document_type'   => $validated['document_type'],
            'document_number' => $validated['document_number'],
            'first_name'      => $validated['first_name'] ?? null,
            'last_name'       => $validated['last_name'] ?? null,
            'company_name'    => $validated['company_name'] ?? null,
            'email'           => $validated['email'] ?? null,
            'phone'           => $validated['phone'] ?? null,
            'address'         => $validated['address'] ?? null,
            'regime_type'     => $validated['regime_type'],
            'is_client'       => $request->boolean('is_client', false),
            'is_supplier'     => $request->boolean('is_supplier', false),
            'is_employee'     => $request->boolean('is_employee', false),
            'status'          => 'ACTIVO',
        ]);

        return redirect()->route('contacts.index')->with('success', 'Tercero creado con éxito.');
    }

    /**
     * Alternar estado Activo/Inactivo rápido (PATCH)
     */
    public function toggleStatus($tenant, $id)
    {
        // $tenant recibe "sajjuna" de forma automática por el dominio
        // $id recibe el ID correcto del contacto (ej. 1)

        $contact = Contact::findOrFail($id);

        $contact->update([
            'is_active' => !$contact->is_active
        ]);

        return redirect()->back()->with('success', 'Estado actualizado con éxito.');
    }

    /**
     * Actualizar los datos de un tercero (PUT)
     */
    public function update($tenant, $id, Request $request)
    {
        $contact = Contact::findOrFail($id);

        $validated = $request->validate([
            'document_type'   => 'required|string|max:5',
            'document_number' => 'required|string|max:20',
            'verification_digit' => 'nullable|string|max:1',
            'first_name'      => 'nullable|required_without:company_name|string|max:100',
            'last_name'       => 'nullable|required_without:company_name|string|max:100',
            'company_name'    => 'nullable|required_without:first_name|string|max:200',
            'email'           => 'nullable|email|max:150',
            'phone'           => 'nullable|string|max:20',
            'address'         => 'nullable|string|max:255',
            'regime_type'     => 'required|string',
            'is_client'       => 'boolean',
            'is_supplier'     => 'boolean',
            'is_employee'     => 'boolean',
        ]);

        $contact->update($validated);

        return redirect()->back()->with('success', 'Tercero actualizado con éxito.');
    }

    /**
     * Crear un proveedor rápido desde el formulario de compras (Petición AJAX)
     */
    public function storeQuickProvider(Request $request)
    {
        // 1. Validamos todos los campos que nos está enviando el formulario express
        $validated = $request->validate([
            'company_name'    => 'required|string|max:255',
            'document_number' => 'required|string|unique:contacts,document_number',
            'document_type'   => 'nullable|string|max:20',
            'regime_type'     => 'nullable|string|max:50',
            'first_name'      => 'nullable|string|max:100',
            'last_name'       => 'nullable|string|max:100',
        ]);

        // 2. CREACIÓN: Asignamos usando los datos validados o valores por defecto seguros
        $provider = Contact::create([
            'company_name'    => $validated['company_name'],
            'document_number' => $validated['document_number'],
            'document_type'   => $validated['document_type'] ?? 'NIT', // 🛠️ Respaldo seguro si viene vacío
            'regime_type'     => $validated['regime_type'] ?? 'RESPONSABLE_IVA', // 🛠️ Respaldo seguro si viene vacío
            'first_name'      => $validated['first_name'] ?? null,
            'last_name'       => $validated['last_name'] ?? null,
            'is_supplier'     => true,  // Forzamos que sea un proveedor para las compras
            'is_client'       => false,
            'is_employee'     => false,
        ]);

        // 3. Retornamos la respuesta JSON limpia para que Vue la procese
        return response()->json([
            'success' => true,
            'contact' => [
                'id'              => $provider->id,
                'company_name'    => $provider->company_name,
                'document_number' => $provider->document_number
            ]
        ]);
    }
}
