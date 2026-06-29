<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ContactController extends Controller
{
    /**
     * Muestra el listado de contactos con filtros dinámicos.
     */
    public function index(Request $request)
    {
        // Iniciamos la consulta base incorporando el accesor 'full_name' en la respuesta json
        $query = Contact::query();

        // 🔍 Filtro por tipo (client, supplier, employee) enviado desde el frontend
        if ($request->has('type')) {
            $type = $request->get('type');
            if (in_array($type, ['client', 'supplier', 'employee'])) {
                $query->where("is_{$type}", true);
            }
        }

        // 🔍 Buscador general (por nombre o documento)
        if ($request->has('search')) {
            $search = mb_strtoupper($request->get('search'), 'UTF-8');
            $query->where(function ($q) use ($search) {
                $q->where('document_number', 'like', "%{$search}%")
                  ->orWhere('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('company_name', 'like', "%{$search}%");
            });
        }

        // Paginamos los resultados y mantenemos los filtros en la URL
        $contacts = $query->latest()
            ->paginate(10)
            ->withQueryString()
            ->through(fn ($contact) => [
                'id' => $contact->id,
                'document_type' => $contact->document_type,
                'document_number' => $contact->document_number,
                'full_name' => $contact->full_name, // 💡 Accesor mágico de Eloquent
                'email' => $contact->email,
                'phone' => $contact->phone,
                'is_client' => $contact->is_client,
                'is_supplier' => $contact->is_supplier,
                'is_employee' => $contact->is_employee,
                'is_active' => $contact->is_active,
            ]);

        return Inertia::render('Tenant/Contacts/Index', [
            'contacts' => $contacts,
            'filters' => $request->only(['type', 'search']),
        ]);
    }

    /**
     * Almacena un nuevo contacto en la base de datos del Tenant.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'document_type'   => 'required|string|max:5',
            'document_number' => 'required|string|max:20', // La validación única por tenant se maneja suave
            'first_name'      => 'nullable|required_without:company_name|string|max:100',
            'last_name'       => 'nullable|required_without:company_name|string|max:100',
            'company_name'    => 'nullable|required_without:first_name|string|max:200',
            'email'           => 'nullable|email|max:150',
            'phone'           => 'nullable|string|max:20',
            'address'         => 'nullable|string|max:255',
            'city_code'       => 'nullable|string|max:10',
            'regime_type'     => 'required|string',
            'is_client'       => 'boolean',
            'is_supplier'     => 'boolean',
            'is_employee'     => 'boolean',
        ]);

        Contact::create($validated);

        return redirect()->back()->with('success', 'Contacto creado correctamente.');
    }

    /**
     * Actualiza los datos de un contacto (incluyendo sus roles).
     */
    public function update(Request $request, Contact $contact)
    {
        $validated = $request->validate([
            'document_type'   => 'required|string|max:5',
            'document_number' => 'required|string|max:20',
            'first_name'      => 'nullable|required_without:company_name|string|max:100',
            'last_name'       => 'nullable|required_without:company_name|string|max:100',
            'company_name'    => 'nullable|required_without:first_name|string|max:200',
            'email'           => 'nullable|email|max:150',
            'phone'           => 'nullable|string|max:20',
            'address'         => 'nullable|string|max:255',
            'city_code'       => 'nullable|string|max:10',
            'regime_type'     => 'required|string',
            'is_client'       => 'boolean',
            'is_supplier'     => 'boolean',
            'is_employee'     => 'boolean',
        ]);

        $contact->update($validated);

        return redirect()->back()->with('success', 'Contacto actualizado correctamente.');
    }

    /**
     * 🔥 MÉTODO QUIRÚRGICO: Cambia exclusivamente el estado activo/inactivo.
     */
    public function toggleStatus(Contact $contact)
    {
        $contact->update([
            'is_active' => !$contact->is_active
        ]);

        return redirect()->back()->with('success', 'Estado actualizado con éxito.');
    }
}
