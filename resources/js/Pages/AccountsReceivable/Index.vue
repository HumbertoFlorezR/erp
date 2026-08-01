<script setup>
import { ref, watch, computed } from 'vue';
import axios from 'axios';
import { usePage, useForm, router, Head, Link } from '@inertiajs/vue3';
import ExportModal from '@/Components/ExportModal.vue';

const showExportModal = ref(false);

// 1. Props enviadas desde AccountsReceivableController.php
const props = defineProps({
    accounts: Object,
    filters: Object
});

// Columnas configurables para la exportación
const receivableColumns = [
    { key: 'customer.full_name', label: 'Cliente' },
    { key: 'customer.document_number', label: 'Identificación' },
    { key: 'sale_id', label: 'ID Venta / Factura' },
    { key: 'due_date', label: 'Fecha Vencimiento' },
    { key: 'original_amount', label: 'Monto Original' },
    { key: 'balance', label: 'Saldo Pendiente' },
    { key: 'status', label: 'Estado' },
];

const page = usePage();
const tenant = computed(() => page.props.tenant || {});

// 2. Filtros y Búsqueda reactiva
const search = ref(props.filters?.customer_id || '');
const activeStatus = ref(props.filters?.status || '');

watch([search, activeStatus], ([newCustomer, newStatus]) => {
    router.get('/accounts-receivable', {
        customer_id: newCustomer,
        status: newStatus
    }, {
        preserveState: true,
        replace: true
    });
});

// Helper para dar formato de moneda en pesos/USD
const formatCurrency = (amount) => {
    return new Intl.NumberFormat('es-CO', {
        style: 'currency',
        currency: 'COP',
        minimumFractionDigits: 0
    }).format(amount || 0);
};

// 3. Estado y Formulario para la Modal de Registro de Abonos
const showPaymentModal = ref(false);
const selectedAccount = ref(null);
const successMessage = ref('');
const receiptData = ref(null);
const isSubmitting = ref(false);

const paymentForm = useForm({
    amount: '',
    method: 'EFECTIVO',
    received_amount: '',
    reference: ''
});

// Abrir la modal asignando la cuenta seleccionada
const openPaymentModal = (account) => {
    selectedAccount.value = account;
    successMessage.value = '';
    paymentForm.reset();
    paymentForm.amount = account.balance; // Por defecto sugiere el pago total
    showPaymentModal.value = true;
};

const printReceipt = (account, amount, method, receivedAmount, reference) => {
    const parsedAmount = parseFloat(amount) || 0;
    const parsedReceived = parseFloat(receivedAmount) || 0;
    const previousBalance = parseFloat(account?.balance || 0);
    const changeAmount = method === 'EFECTIVO' ? Math.max(0, parsedReceived - parsedAmount) : 0;

    receiptData.value = {
        companyName: tenant.value.company_name || 'ERP GLOBAL',
        customerName: account?.customer?.company_name || `${account?.customer?.first_name || ''} ${account?.customer?.last_name || ''}`.trim() || 'Cliente General',
        saleId: account?.sale_id,
        amount: parsedAmount,
        method,
        reference: reference || 'SIN REFERENCIA',
        receivedAmount: parsedReceived,
        changeAmount,
        previousBalance,
        newBalance: Math.max(0, previousBalance - parsedAmount),
        date: new Date().toLocaleString('es-CO')
    };

    setTimeout(() => {
        window.print();
        receiptData.value = null;
    }, 400);
};

// Cálculo en tiempo real del cambio si el pago es en EFECTIVO
const changeAmount = computed(() => {
    if (paymentForm.method !== 'EFECTIVO') return 0;
    const received = parseFloat(paymentForm.received_amount) || 0;
    const amount = parseFloat(paymentForm.amount) || 0;
    return Math.max(0, received - amount);
});

// Enviar Abono mediante la ruta estipulada en el Controlador
const submitPayment = async () => {
    const accountToProcess = selectedAccount.value;
    isSubmitting.value = true;
    successMessage.value = '';

    try {
        const response = await axios.post(`/accounts-receivable/${accountToProcess.id}/payments`, paymentForm.data());
        const data = response.data || {};

        successMessage.value = data.message || 'Abono registrado correctamente.';
        console.log('Abono registrado:', data);

        printReceipt(
            accountToProcess,
            data.amount || paymentForm.amount,
            data.method || paymentForm.method,
            data.received_amount || paymentForm.received_amount,
            data.reference || paymentForm.reference
        );

        showPaymentModal.value = false;
        paymentForm.reset();
        selectedAccount.value = null;

        await router.reload({ preserveState: true, only: ['accounts'] });
    } catch (error) {
        if (error.response && error.response.data && error.response.data.errors) {
            paymentForm.setErrors(error.response.data.errors);
        }

        successMessage.value = '';
    } finally {
        isSubmitting.value = false;
    }
};
</script>

<template>
    <Head title="Cartera por Cobrar" />

    <div class="min-h-screen bg-slate-100 font-sans flex flex-col no-print">
        <!-- CABECERA SUPERIOR -->
        <nav class="bg-white border-b border-slate-200 px-6 py-4 flex justify-between items-center shadow-sm">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg flex items-center justify-center text-white font-bold text-sm"
                     :style="{ backgroundColor: tenant.primary_color || '#0f172a' }">
                    {{ tenant.company_name?.substring(0,2).toUpperCase() || 'ERP' }}
                </div>

                <div>
                    <span class="font-bold text-slate-800 text-lg">{{ tenant.company_name }} - Cartera por Cobrar</span>
                    <p class="text-sm text-slate-500">Monitoreo de créditos, fechas de vencimiento y registro de abonos.</p>
                </div>
            </div>

            <div class="flex items-center gap-4">
                <Link href="/dashboard" class="text-sm font-semibold text-slate-500 hover:text-slate-800 transition-colors border-2 p-1 rounded-lg">
                    Volver al Inicio
                </Link>
            </div>
        </nav>

        <div v-if="successMessage" class="mx-6 mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 shadow-sm">
            {{ successMessage }}
        </div>

        <!-- CUERPO DEL MÓDULO -->
        <div class="p-6 max-w-7xl w-full mx-auto space-y-6 flex-1">
            <!-- BARRA DE ACCIONES Y FILTROS -->
            <div class="bg-white p-4 rounded-2xl shadow-sm border border-slate-200 flex flex-col md:flex-row md:items-center md:justify-between gap-4">

                <!-- Tabs de Filtro por Estado -->
                <div class="flex bg-slate-100 p-1 rounded-xl self-start overflow-x-auto max-w-full">
                    <button @click="activeStatus = ''" :class="['px-4 py-1.5 rounded-lg text-xs font-semibold transition-all whitespace-nowrap', !activeStatus ? 'bg-white text-slate-800 shadow-sm' : 'text-slate-500 hover:text-slate-800']">Todas</button>
                    <button @click="activeStatus = 'PENDIENTE'" :class="['px-4 py-1.5 rounded-lg text-xs font-semibold transition-all whitespace-nowrap', activeStatus === 'PENDIENTE' ? 'bg-white text-slate-800 shadow-sm' : 'text-slate-500 hover:text-slate-800']">Pendientes</button>
                    <button @click="activeStatus = 'VENCIDA'" :class="['px-4 py-1.5 rounded-lg text-xs font-semibold transition-all whitespace-nowrap', activeStatus === 'VENCIDA' ? 'bg-white text-slate-800 shadow-sm' : 'text-slate-500 hover:text-slate-800']">Vencidas</button>
                    <button @click="activeStatus = 'PAGADA'" :class="['px-4 py-1.5 rounded-lg text-xs font-semibold transition-all whitespace-nowrap', activeStatus === 'PAGADA' ? 'bg-white text-slate-800 shadow-sm' : 'text-slate-500 hover:text-slate-800']">Pagadas</button>
                </div>

                <div class="flex items-center gap-3 w-full md:w-auto">
                    <button @click="showExportModal = true" class="bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 font-semibold text-sm px-4 py-2 rounded-xl shadow-sm transition-all flex items-center gap-2">
                        <span>📥 Exportar</span>
                    </button>
                </div>
            </div>

            <!-- TABLA DE CARTERA POR COBRAR -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-xs font-bold text-slate-500 uppercase tracking-wider">
                                <th class="px-6 py-4">Cliente / Contacto</th>
                                <th class="px-6 py-4">Doc. Venta</th>
                                <th class="px-6 py-4">Fecha Vencimiento</th>
                                <th class="px-6 py-4 text-right">Monto Original</th>
                                <th class="px-6 py-4 text-right">Saldo Pendiente</th>
                                <th class="px-6 py-4 text-center">Estado</th>
                                <th class="px-6 py-4 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 text-sm text-slate-600">
                            <tr v-for="account in accounts.data" :key="account.id" class="hover:bg-slate-100/60 transition-colors">
                                <!-- Nombre del Cliente -->
                                <td class="px-6 py-3 font-semibold text-slate-800">
                                    <div class="flex flex-col">
                                        <span>{{ account.customer?.company_name || `${account.customer?.first_name || ''} ${account.customer?.last_name || ''}`.trim() || 'Cliente General' }}</span>
                                        <span class="text-xs text-slate-400 font-normal">
                                            {{ account.customer?.document_type }}: {{ account.customer?.document_number }}
                                        </span>
                                    </div>
                                </td>

                                <!-- Venta Asociada -->
                                <td class="px-6 py-3 whitespace-nowrap font-medium text-slate-700">
                                    <span class="bg-slate-100 text-slate-600 px-2 py-1 rounded-md text-xs font-mono font-bold">
                                        Venta #{{ account.sale_id }}
                                    </span>
                                </td>

                                <!-- Fecha de Vencimiento -->
                                <td class="px-6 py-3 whitespace-nowrap font-medium">
                                    <span :class="{'text-rose-600 font-bold': account.status === 'VENCIDA', 'text-slate-600': account.status !== 'VENCIDA'}">
                                        {{ account.due_date || 'Sin fecha' }}
                                    </span>
                                </td>

                                <!-- Monto Original -->
                                <td class="px-6 py-3 text-right font-medium text-slate-500 whitespace-nowrap">
                                    {{ formatCurrency(account.original_amount) }}
                                </td>

                                <!-- Saldo Actual -->
                                <td class="px-6 py-3 text-right font-bold text-slate-800 whitespace-nowrap">
                                    {{ formatCurrency(account.balance) }}
                                </td>

                                <!-- Badge de Estado -->
                                <td class="px-6 py-3 text-center whitespace-nowrap">
                                    <span v-if="account.status === 'PENDIENTE'" class="bg-amber-50 text-amber-700 border border-amber-200 text-[11px] font-bold px-2.5 py-1 rounded-full">
                                        PENDIENTE
                                    </span>
                                    <span v-else-if="account.status === 'VENCIDA'" class="bg-rose-50 text-rose-700 border border-rose-200 text-[11px] font-bold px-2.5 py-1 rounded-full">
                                        VENCIDA
                                    </span>
                                    <span v-else-if="account.status === 'PAGADA'" class="bg-emerald-50 text-emerald-700 border border-emerald-200 text-[11px] font-bold px-2.5 py-1 rounded-full">
                                        PAGADA
                                    </span>
                                    <span v-else class="bg-slate-100 text-slate-500 text-[11px] font-bold px-2.5 py-1 rounded-full">
                                        {{ account.status }}
                                    </span>
                                </td>

                                <!-- Acciones -->
                                <td class="px-6 py-3 text-right whitespace-nowrap space-x-2">
                                    <Link
                                        :href="`/accounts-receivable/${account.id}`"
                                        class="text-slate-600 hover:text-slate-900 bg-slate-100 hover:bg-slate-200 px-3 py-1.5 rounded-lg text-xs font-semibold transition-all inline-block">
                                        Ver Historial
                                    </Link>

                                    <button
                                        v-if="account.status !== 'PAGADA' && account.status !== 'ANULADA'"
                                        @click="openPaymentModal(account)"
                                        class="text-white font-semibold px-3 py-1.5 rounded-lg text-xs shadow-sm transition-all active:scale-95"
                                        :style="{ backgroundColor: tenant.primary_color || '#0f172a' }">
                                        + Registrar Abono
                                    </button>
                                </td>
                            </tr>

                            <tr v-if="accounts.data.length === 0">
                                <td colspan="7" class="px-6 py-12 text-center text-slate-400">
                                    No se encontraron registros de cuentas por cobrar con los filtros aplicados.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- 💵 MODAL REGISTRAR ABONO -->
        <div v-if="showPaymentModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm transition-opacity" @click="showPaymentModal = false"></div>

            <div class="bg-white rounded-2xl shadow-xl border border-slate-200 max-w-md w-full relative z-10 p-6 flex flex-col">
                <div class="flex justify-between items-center pb-3 border-b border-slate-100 mb-4">
                    <h3 class="text-lg font-bold text-slate-800">Registrar Abono a Cartera</h3>
                    <button @click="showPaymentModal = false" class="text-slate-400 hover:text-slate-600 text-xl font-bold">&times;</button>
                </div>

                <div v-if="selectedAccount" class="bg-slate-50 p-3 rounded-xl border border-slate-200 mb-4 text-xs space-y-1">
                    <p class="text-slate-500"><strong>Cliente:</strong> {{ selectedAccount.customer?.company_name || selectedAccount.customer?.first_name }}</p>
                    <p class="text-slate-500"><strong>Saldo Actual:</strong> <span class="text-rose-600 font-bold">{{ formatCurrency(selectedAccount.balance) }}</span></p>
                </div>

                <form @submit.prevent="submitPayment" class="space-y-4">
                    <!-- Método de Pago -->
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Método de Pago</label>
                        <select v-model="paymentForm.method" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm focus:outline-none">
                            <option value="EFECTIVO">Efectivo</option>
                            <option value="TRANSFERENCIA">Transferencia Bancaria</option>
                            <option value="TARJETA_DEBITO">Tarjeta Débito</option>
                            <option value="TARJETA_CREDITO">Tarjeta Crédito</option>
                        </select>
                    </div>

                    <!-- Valor del Abono -->
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Monto a Abonar</label>
                        <input
                            v-model="paymentForm.amount"
                            type="number"
                            step="0.01"
                            :max="selectedAccount?.balance"
                            required
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm focus:outline-none"
                        />
                        <span v-if="paymentForm.errors.amount" class="text-xs text-rose-500 mt-1 block">{{ paymentForm.errors.amount }}</span>
                    </div>

                    <!-- Campos Dinámicos para Efectivo -->
                    <div v-if="paymentForm.method === 'EFECTIVO'" class="grid grid-cols-2 gap-3 bg-amber-50/50 p-3 rounded-xl border border-amber-100">
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Recibido</label>
                            <input v-model="paymentForm.received_amount" type="number" step="0.01" class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-1.5 text-sm focus:outline-none" />
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Cambio / Devueltas</label>
                            <div class="px-2.5 py-1.5 font-bold text-sm text-slate-700 bg-slate-100 rounded-lg border border-slate-200">
                                {{ formatCurrency(changeAmount) }}
                            </div>
                        </div>
                    </div>

                    <!-- Referencia de Transacción -->
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Referencia / Comprobante (Opcional)</label>
                        <input v-model="paymentForm.reference" type="text" placeholder="Ej: TR-982341" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm focus:outline-none" />
                    </div>

                    <!-- Botones de Acción -->
                    <div class="flex gap-3 pt-3 border-t border-slate-100 justify-end">
                        <button type="button" @click="showPaymentModal = false" class="bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold py-2 px-4 rounded-xl text-sm transition-all">
                            Cancelar
                        </button>
                        <button type="submit" :disabled="isSubmitting" class="text-white font-semibold py-2 px-5 rounded-xl text-sm shadow-md transition-all flex items-center gap-2" :style="{ backgroundColor: tenant.primary_color || '#0f172a' }">
                            <span v-if="isSubmitting" class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                            {{ isSubmitting ? 'Guardando...' : 'Confirmar Abono' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>

    <!-- MODAL DE EXPORTACIÓN -->
    <ExportModal
        :is-open="showExportModal"
        module-name="accounts_receivable"
        :available-columns="receivableColumns"
        :current-data="accounts.data"
        @close="showExportModal = false"
    />

    <div v-if="receiptData" class="hidden print:block font-mono text-[10px] leading-tight text-black p-3">
        <div class="w-[58mm] mx-auto">
            <div class="text-center font-bold text-[12px]">{{ receiptData.companyName }}</div>
            <div class="text-center text-[10px] mb-2">TIRILLA DE ABONO</div>
            <div class="border-b border-dashed border-black pb-2 mb-2">
                <div>{{ receiptData.date }}</div>
                <div><strong>Cliente:</strong> {{ receiptData.customerName }}</div>
                <div><strong>Venta:</strong> #{{ receiptData.saleId }}</div>
            </div>
            <div class="space-y-1">
                <div class="flex justify-between"><span>Método</span><span>{{ receiptData.method }}</span></div>
                <div class="flex justify-between"><span>Monto anterior</span><span>{{ formatCurrency(receiptData.amount) }}</span></div>
                <div class="flex justify-between"><span>Recibido</span><span>{{ formatCurrency(receiptData.receivedAmount) }}</span></div>
                <div v-if="receiptData.changeAmount > 0" class="flex justify-between"><span>Cambio</span><span>{{ formatCurrency(receiptData.changeAmount) }}</span></div>
                <div class="flex justify-between"><span>Saldo nuevo</span><span>{{ formatCurrency(receiptData.amount - receiptData.receivedAmount) }}</span></div>
                <div class="mt-2"><strong>Ref:</strong> {{ receiptData.reference }}</div>
            </div>
            <div class="border-t border-dashed border-black mt-2 pt-2 text-center">
                Gracias por su pago
            </div>
        </div>
    </div>
</template>

<style scoped>
.no-print {
    display: flex;
}

@media print {
    .no-print {
        display: none !important;
    }
}
</style>
