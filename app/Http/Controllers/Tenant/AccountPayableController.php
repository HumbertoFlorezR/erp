<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\AccountPayable;
use App\Models\Tenant\PurchasePayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class AccountPayableController extends Controller
{
    public function index(Request $request)
    {
        $accounts = AccountPayable::with(['provider', 'invoice'])
            ->where('status', '!=', 'ANULADA')
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->provider_id, fn ($q) => $q->where('provider_id', $request->provider_id))
            ->orderBy('due_date')
            ->paginate(20);

        return Inertia::render('AccountsPayable/Index', [
            'accounts' => $accounts,
        ]);
    }

    public function show($tenant, $accountId)
    {
        $account = AccountPayable::findOrFail($accountId);
        $account->load(['provider', 'invoice.payments']);

        return Inertia::render('AccountsPayable/Show', [
            'account' => $account,
        ]);
    }

    public function applyPayment($tenant, Request $request, $accountId)
    {
        $account = AccountPayable::findOrFail($accountId); // o AccountReceivable, según el controlador

        $validated = $request->validate([
            'amount'          => ['required', 'numeric', 'min:0.01'],
            'method'          => ['required', 'in:EFECTIVO,TRANSFERENCIA,TARJETA_DEBITO,TARJETA_CREDITO'],
            'received_amount' => ['nullable', 'numeric', 'min:0'],
            'reference'       => ['nullable', 'string', 'max:100'],
        ]);

        if ($account->status === 'ANULADA') {
            throw ValidationException::withMessages([
                'amount' => 'Esta cuenta está anulada, no se pueden registrar abonos.'
            ]);
        }

        if ($account->status === 'PAGADA') {
            throw ValidationException::withMessages([
                'amount' => 'Esta cuenta ya está saldada.'
            ]);
        }

        if (round($validated['amount'], 2) > round($account->balance, 2)) {
            throw ValidationException::withMessages([
                'amount' => "El abono (\${$validated['amount']}) supera el saldo pendiente (\${$account->balance})."
            ]);
        }

        DB::transaction(function () use ($validated, $account) {
            $account = $account::where('id', $account->id)->lockForUpdate()->first();

            $isCash = $validated['method'] === 'EFECTIVO';
            $received = $isCash ? ($validated['received_amount'] ?? $validated['amount']) : $validated['amount'];
            $change = $isCash ? max(0, $received - $validated['amount']) : 0;

            PurchasePayment::create([ // o SalePayment, según el controlador
                'purchase_invoice_id'   => $account->purchase_invoice_id,
                'payment_method'        => $validated['method'],
                'amount'                => $validated['amount'],
                'received_amount'       => $received,
                'change_amount'         => $change,
                'transaction_reference' => $validated['reference'] ?? null,
            ]);

            $account->balance -= $validated['amount'];
            $account->status = $account->balance <= 0 ? 'PAGADA' : $account->status;
            $account->save();

            $invoice = $account->invoice; // o $account->sale, según el controlador
            $invoice->payment_status = $account->balance <= 0 ? 'PAGADA' : $invoice->payment_status;
            $invoice->save();
        });

        return redirect()->back()->with('success', 'Abono registrado correctamente.');
    }
}
