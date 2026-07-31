<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\AccountReceivable;
use App\Models\Tenant\SalePayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class AccountReceivableController extends Controller
{
    /**
     * Listado de cartera pendiente, agrupado por cliente.
     */
    public function index(Request $request)
    {
        $accounts = AccountReceivable::with(['customer', 'sale'])
            ->where('status', '!=', 'ANULADA')
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->customer_id, fn ($q) => $q->where('customer_id', $request->customer_id))
            ->orderBy('due_date')
            ->paginate(20);

        return Inertia::render('AccountsReceivable/Index', [
            'accounts' => $accounts,
        ]);
    }

    /**
     * Detalle de una cuenta por cobrar puntual (con historial de abonos de la venta asociada).
     */
    public function show($tenant, $accountId)
    {
        $accountReceivable = AccountReceivable::findOrFail($accountId);
        $accountReceivable->load(['customer', 'sale.payments']);

        return Inertia::render('AccountsReceivable/Show', [
            'account' => $accountReceivable,
        ]);
    }

    /**
     * Registrar un abono sobre una cuenta por cobrar existente.
     */
    public function applyPayment($tenant, Request $request, $accountId)
    {
        $accountReceivable = AccountReceivable::findOrFail($accountId);
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', 'in:EFECTIVO,TRANSFERENCIA,TARJETA_DEBITO,TARJETA_CREDITO'],
            'received_amount' => ['nullable', 'numeric', 'min:0'],
            'reference' => ['nullable', 'string', 'max:100'],
        ]);

        if ($accountReceivable->status === 'ANULADA') {
            return response()->json([
                'message' => 'Esta cuenta por cobrar está anulada, no se pueden registrar abonos.'
            ], 422);
        }

        if ($accountReceivable->status === 'PAGADA') {
            return response()->json([
                'message' => 'Esta cuenta por cobrar ya está saldada.'
            ], 422);
        }

        // Nunca confiar en el cliente para saber cuánto se puede abonar
        if (round($validated['amount'], 2) > round($accountReceivable->balance, 2)) {
            return response()->json([
                'message' => "El abono (\${$validated['amount']}) supera el saldo pendiente (\${$accountReceivable->balance})."
            ], 422);
        }

        // Buscar los abonos realizados para la venta asociada y verificar que no se exceda el total de la venta
        $totalPaid = $accountReceivable->sale->total_amount - $accountReceivable->balance;
        $totalSaleAmount = $accountReceivable->sale->total_amount;
        if (round($totalPaid + $validated['amount'], 2) > round($totalSaleAmount, 2)) {
            return response()->json([
                'message' => "El abono (\${$validated['amount']}) más los abonos previos (\${$totalPaid}) exceden el total de la venta (\${$totalSaleAmount})."
            ], 422);
        }

        return DB::transaction(function () use ($validated, $accountReceivable) {

            // Bloquear la fila para evitar que dos abonos simultáneos dejen el saldo inconsistente
            $accountReceivable = AccountReceivable::where('id', $accountReceivable->id)
                ->lockForUpdate()
                ->first();

            $isCash = $validated['method'] === 'EFECTIVO';
            $received = $isCash ? ($validated['received_amount'] ?? $validated['amount']) : $validated['amount'];
            $change = $isCash ? max(0, $received - $validated['amount']) : 0;

            // El abono se registra como un SalePayment más, asociado a la venta original
            $payment = SalePayment::create([
                'sale_id' => $accountReceivable->sale_id,
                'payment_method' => $validated['method'],
                'amount' => $validated['amount'],
                'received_amount' => $received,
                'change_amount' => $change,
                'transaction_reference' => $validated['reference'] ?? null,
            ]);

            // Actualizar saldo de la cuenta por cobrar
            $accountReceivable->balance -= $received;
            $accountReceivable->status = $accountReceivable->balance <= 0 ? 'PAGADA' : $accountReceivable->status;
            $accountReceivable->save();

            // Mantener sincronizado el estado de la venta original
            $sale = $accountReceivable->sale;
            $sale->payment_status = $accountReceivable->balance <= 0 ? 'PAGADA' : $sale->payment_status;
            $sale->save();

            return response()->json([
                'success' => true,
                'message' => 'Abono registrado correctamente.',
                'payment_id' => $payment->id,
                'new_balance' => $accountReceivable->balance,
                'account_status' => $accountReceivable->status,
                'received_amount' => $received
            ]);
        });
    }
}
