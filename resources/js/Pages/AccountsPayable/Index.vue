<script setup>
import { ref, computed } from 'vue';
import { usePage, useForm, router, Head, Link } from '@inertiajs/vue3';
import ExportModal from '@/Components/ExportModal.vue';

const props = defineProps({
    accounts: Object
});

const page = usePage();
const tenant = computed(() => page.props.tenant || {});

const formatCurrency = (amount) => {
    return new Intl.NumberFormat('es-CO', {
        style: 'currency',
        currency: 'COP',
        minimumFractionDigits: 0
    }).format(amount || 0);
};

const formatDate = (date) => {
    if (!date) return '—';
    return new Date(date).toLocaleDateString('es-CO');
};

const statusBadge = (status) => {
    return {
        'PENDIENTE': 'bg-amber-50 text-amber-600',
        'PAGADA': 'bg-emerald-50 text-emerald-600',
        'ANULADA': 'bg-slate-100 text-slate-500',
    }[status] || 'bg-slate-100 text-slate-500';
};

// --- Filtros ---
const statusFilter = ref('');

const applyFilters = () => {
    router.get('/accounts-payable', { status: statusFilter.value }, {
        preserveState: true,
        replace: true
    });
};

// --- Exportar ---
const showExportModal = ref(false);

const accountColumns = [
    { key: 'invoice.invoice_number', label: 'N° Factura' },
    { key: 'provider.full_name', label: 'Proveedor' },
    { key: 'provider.document_number', label: 'Identificación' },
    { key: 'original_amount', label: 'Monto Original' },
    { key: 'balance', label: 'Saldo Pendiente' },
    { key: 'due_date', label: 'Fecha Vencimiento' },
    { key: 'status', label: 'Estado' }
];

// --- Modal de abono ---
const showPaymentModal = ref(false);
const selectedAccount = ref(null);
const successMessage = ref('');

const paymentForm = useForm({
    amount: '',
    method: 'EFECTIVO',
    received_amount: '',
    reference: ''
});

const openPaymentModal = (account) => {
    selectedAccount.value = account;
    successMessage.value = '';
    paymentForm.reset();
    paymentForm.clearErrors();
    showPaymentModal.value = true;
};

const submitPayment = () => {
    paymentForm.post(`/accounts-payable/${selectedAccount.value.id}/payments`, {
        preserveScroll: true,
        onSuccess: () => {
            successMessage.value = 'Abono registrado correctamente.';
            showPaymentModal.value = false;
            paymentForm.reset();
        }
    });
};
</script>

<template>
    <Head title="Cartera por Pagar" />

    <div class="min-h-screen bg-slate-100 font-sans flex flex-col">
        <!-- CABECERA -->
        <nav class="bg-white border-b border-slate-200 px-6 py-4 flex justify-between items-center shadow-sm">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg flex items-center justify-center text-white font-bold text-sm"
                     :style="{ backgroundColor: tenant.primary_color || '#0f172a' }">
                    {{ tenant.company_name?.substring(0,2).toUpperCase() || 'ERP' }}
                </div>
                <div>
                    <span class="font-bold text-slate-800 text-lg">{{ tenant.company_name }} - Cartera por Pagar</span>
                    <p class="text-sm text-slate-500">Cuentas pendientes con proveedores.</p>
                </div>
            </div>

            <Link href="/dashboard" class="text-sm font-semibold text-slate-500 hover:text-slate-800 transition-colors border-2 p-1 rounded-lg">
                Volver al Inicio
            </Link>
        </nav>

        <div v-if="successMessage" class="mx-6 mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 shadow-sm">
            {{ successMessage }}
        </div>

        <!-- CUERPO -->
        <div class="p-6 max-w-7xl w-full mx-auto space-y-6 flex-1">

            <!-- FILTROS + EXPORTAR -->
            <div class="bg-white p-4 rounded-2xl shadow-sm border border-slate-200 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <select v-model="statusFilter" @change="applyFilters" class="bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm">
                    <option value="">Todos los estados</option>
                    <option value="PENDIENTE">Pendiente</option>
                    <option value="PAGADA">Pagada</option>
                </select>

                <button @click="showExportModal = true" class="bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 font-semibold text-sm px-4 py-2.5 rounded-xl shadow-sm transition-all flex items-center gap-2">
                    <span>📥 Exportar</span>
                </button>
            </div>

            <!-- TABLA -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-slate-500 text-xs uppercase">
                        <tr>
                            <th class="px-6 py-3 text-left">Factura</th>
                            <th class="px-6 py-3 text-left">Proveedor</th>
                            <th class="px-6 py-3 text-right">Monto Original</th>
                            <th class="px-6 py-3 text-right">Saldo</th>
                            <th class="px-6 py-3 text-left">Vence</th>
                            <th class="px-6 py-3 text-left">Estado</th>
                            <th class="px-6 py-3 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="account in accounts.data" :key="account.id" class="hover:bg-slate-50">
                            <td class="px-6 py-3">
                                <Link :href="`/accounts-payable/${account.id}`" class="text-blue-600 hover:underline font-medium">
                                    {{ account.invoice?.invoice_number || `F-${account.purchase_invoice_id}` }}
                                </Link>
                            </td>
                            <td class="px-6 py-3">{{ account.provider?.full_name }}</td>
                            <td class="px-6 py-3 text-right">{{ formatCurrency(account.original_amount) }}</td>
                            <td class="px-6 py-3 text-right font-semibold">{{ formatCurrency(account.balance) }}</td>
                            <td class="px-6 py-3">{{ formatDate(account.due_date) }}</td>
                            <td class="px-6 py-3">
                                <span :class="statusBadge(account.status)" class="text-xs font-semibold px-2.5 py-1 rounded-full">
                                    {{ account.status }}
                                </span>
                            </td>
                            <td class="px-6 py-3 text-right space-x-2">
                                <Link :href="`/accounts-payable/${account.id}`" class="text-slate-600 hover:text-slate-800 bg-slate-100 hover:bg-slate-200 px-3 py-1.5 rounded-lg text-xs font-semibold transition-all">
                                    Ver Historial
                                </Link>
                                <button
                                    v-if="account.status === 'PENDIENTE'"
                                    @click="openPaymentModal(account)"
                                    class="text-white px-3 py-1.5 rounded-lg text-xs font-semibold transition-all"
                                    :style="{ backgroundColor: tenant.primary_color || '#0f172a' }">
                                    Registrar Abono
                                </button>
                            </td>
                        </tr>

                        <tr v-if="accounts.data.length === 0">
                            <td colspan="7" class="px-6 py-12 text-center text-slate-400">
                                No hay cuentas por pagar pendientes.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- MODAL DE ABONO -->
        <div v-if="showPaymentModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm" @click="showPaymentModal = false"></div>

            <div class="bg-white rounded-2xl shadow-xl border border-slate-200 max-w-md w-full relative z-10 p-6">
                <div class="flex justify-between items-center pb-3 border-b border-slate-100 mb-4">
                    <h3 class="text-lg font-bold text-slate-800">Registrar Abono</h3>
                    <button @click="showPaymentModal = false" class="text-slate-400 hover:text-slate-600 text-xl font-bold">&times;</button>
                </div>

                <p class="text-sm text-slate-500 mb-4">
                    Saldo pendiente: <span class="font-semibold text-slate-800">{{ formatCurrency(selectedAccount?.balance) }}</span>
                </p>

                <form @submit.prevent="submitPayment" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Monto a Abonar</label>
                        <input v-model="paymentForm.amount" type="number" step="0.01" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm" />
                        <span v-if="paymentForm.errors.amount" class="text-xs text-rose-500 mt-1 block">{{ paymentForm.errors.amount }}</span>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Método de Pago</label>
                        <select v-model="paymentForm.method" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm">
                            <option value="EFECTIVO">Efectivo</option>
                            <option value="TRANSFERENCIA">Transferencia Bancaria</option>
                            <option value="TARJETA_DEBITO">Tarjeta Débito</option>
                            <option value="TARJETA_CREDITO">Tarjeta Crédito</option>
                        </select>
                    </div>

                    <div v-if="paymentForm.method === 'EFECTIVO'">
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Monto Recibido (Opcional)</label>
                        <input v-model="paymentForm.received_amount" type="number" step="0.01" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm" />
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Referencia (Opcional)</label>
                        <input v-model="paymentForm.reference" type="text" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm" />
                    </div>

                    <div class="flex gap-3 pt-3 border-t border-slate-100 justify-end">
                        <button type="button" @click="showPaymentModal = false" class="bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold py-2 px-4 rounded-xl text-sm transition-all">
                            Cancelar
                        </button>
                        <button type="submit" :disabled="paymentForm.processing" class="text-white font-semibold py-2 px-5 rounded-xl text-sm shadow-md transition-all" :style="{ backgroundColor: tenant.primary_color || '#0f172a' }">
                            {{ paymentForm.processing ? 'Guardando...' : 'Confirmar Abono' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <ExportModal
            :is-open="showExportModal"
            module-name="accounts-payable"
            :available-columns="accountColumns"
            :current-data="accounts.data"
            @close="showExportModal = false"
        />
    </div>
</template>
