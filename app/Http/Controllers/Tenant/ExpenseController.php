<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Expense;
use App\Models\Tenant\ExpenseCategory;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ExpenseController extends Controller
{
    /**
     * Listado de gastos con filtros por categoría y rango de fechas.
     */
    public function index(Request $request)
    {
        $expenses = Expense::with(['category', 'provider'])
            ->where('status', '!=', 'ANULADO')
            ->when($request->category_id, fn ($q) => $q->where('expense_category_id', $request->category_id))
            ->when($request->date_from, fn ($q) => $q->whereDate('expense_date', '>=', $request->date_from))
            ->when($request->date_to, fn ($q) => $q->whereDate('expense_date', '<=', $request->date_to))
            ->orderByDesc('expense_date')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Expenses/Index', [
            'expenses'   => $expenses,
            'categories' => ExpenseCategory::orderBy('name')->get(),
            'filters'    => $request->only(['category_id', 'date_from', 'date_to']),
        ]);
    }

    /**
     * Registrar un nuevo gasto.
     */
    public function store($tenant,Request $request)
    {
        $validated = $request->validate([
            'expense_category_id' => ['required', 'exists:expense_categories,id'],
            'provider_id'          => ['nullable', 'exists:contacts,id'],
            'description'          => ['required', 'string', 'max:255'],
            'amount'               => ['required', 'numeric', 'min:0.01'],
            'expense_date'         => ['required', 'date'],
            'payment_method'       => ['required', 'in:EFECTIVO,TRANSFERENCIA,TARJETA_DEBITO,TARJETA_CREDITO'],
            'reference'            => ['nullable', 'string', 'max:100'],
        ]);

        $validated['status'] = 'ACTIVO';

        Expense::create($validated);

        return back()->with('success', 'Gasto registrado correctamente.');
    }

    /**
     * Anular un gasto (no se elimina físicamente, se marca ANULADO).
     */
    public function cancel($tenant, $id)
    {
        $expense = Expense::findOrFail($id);
        $expense->status = 'ANULADO';
        $expense->save();

        return back()->with('success', 'Gasto anulado correctamente.');
    }

    /**
     * Crear una categoría nueva al vuelo, desde el mismo formulario de registro de gasto.
     */
    public function storeQuickCategory($tenant, Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:expense_categories,name'],
        ]);

        $category = ExpenseCategory::create([
            'name'       => $validated['name'],
            'is_default' => false,
        ]);

        return response()->json($category);
    }

    /**
     * Actualizar un gasto existente.
     */
    public function update($tenant, Request $request, $id)
    {
        $expense = Expense::findOrFail($id);

        $validated = $request->validate([
            'expense_category_id' => ['required', 'exists:expense_categories,id'],
            'provider_id'          => ['nullable', 'exists:contacts,id'],
            'description'          => ['required', 'string', 'max:255'],
            'amount'               => ['required', 'numeric', 'min:0.01'],
            'expense_date'         => ['required', 'date'],
            'payment_method'       => ['required', 'in:EFECTIVO,TRANSFERENCIA,TARJETA_DEBITO,TARJETA_CREDITO'],
            'reference'            => ['nullable', 'string', 'max:100'],
        ]);

        $expense->update($validated);

        return back()->with('success', 'Gasto actualizado correctamente.');
    }
}
