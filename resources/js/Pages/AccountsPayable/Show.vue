<script setup>
import { computed } from 'vue';
import { usePage, Link } from '@inertiajs/vue3';

const props = defineProps({
    account: Object
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

const formatDateTime = (date) => {
    if (!date) return '—';
    return new Date(date).toLocaleString('es-CO', {
        day: '2-digit', month: '2-digit', year: 'numeric',
        hour: '2-digit', minute: '2-digit'
    });
};

const statusBadge = (status) => {
    return {
        'PENDIENTE': 'bg-amber-50 text-amber-600',
        'PAGADA': 'bg-emerald-50 text-emerald-600',
        'ANULADA': 'bg-slate-100 text-slate-500',
    }[status] || 'bg-slate-100 text-slate-500';
};

// El historial de abonos vive en account.invoice.payments (cargado en el controlador con ->load(['provider', 'invoice.payments']))
const payments = computed(() => props.account.invoice?.payments || []);

const totalPaid = computed(() => {
    return payments.value.reduce((sum, p) => sum + Number(p.amount), 0);
});

const progressPercent = computed(() => {
    if (!props.account.original_amount || props.account.original_amount <= 0) return 0;
    return Math.min(100, Math.round((totalPaid.value / props.account.original_amount) * 100));
});
</script>

<template>
    <Head title="Detalle Cuenta por Pagar" />

    <div class="min-h-screen bg-slate-100 font-sans flex flex-col">
        <!-- CABECERA -->
        <nav class="bg-white border-b border-slate-200 px-6 py-4 flex justify-between items-center shadow-sm">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg flex items-center justify-center text-white font-bold text-sm"
                     :style="{ backgroundColor: tenant.primary_color || '#0f172a' }">
                    {{ tenant.company_name?.substring(0,2).toUpperCase() || 'ERP' }}
                </div>
                <div>
                    <span class="font-bold text-slate-800 text-lg">{{ tenant.company_name }} - Detalle de Cuenta</span>
                    <p class="text-sm text-slate-500">Factura {{ account.invoice?.invoice_number || `F-${account.purchase_invoice_id}` }}</p>
                </div>
            </div>

            <Link href="/accounts-payable" class="text-sm font-semibold text-slate-500 hover:text-slate-800 transition-colors border-2 p-1 rounded-lg">
                &larr; Volver a Cartera
            </Link>
        </nav>

        <div class="p-6 max-w-5xl w-full mx-auto space-y-6 flex-1">

            <!-- RESUMEN -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-6">
                    <div>
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Proveedor</p>
                        <p class="text-lg font-bold text-slate-800 mt-1">{{ account.provider?.full_name }}</p>
                        <p class="text-sm text-slate-500">{{ account.provider?.document_number }}</p>
                    </div>

                    <div class="text-right">
                        <span :class="statusBadge(account.status)" class="text-xs font-semibold px-3 py-1 rounded-full">
                            {{ account.status }}
                        </span>
                        <p class="text-xs text-slate-400 mt-2">Vence: {{ formatDate(account.due_date) }}</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-6 pt-6 border-t border-slate-100">
                    <div>
                        <p class="text-xs font-bold text-slate-400 uppercase">Monto Original</p>
                        <p class="text-xl font-bold text-slate-800">{{ formatCurrency(account.original_amount) }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-bold text-slate-400 uppercase">Total Abonado</p>
                        <p class="text-xl font-bold text-emerald-600">{{ formatCurrency(totalPaid) }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-bold text-slate-400 uppercase">Saldo Pendiente</p>
                        <p class="text-xl font-bold text-rose-600">{{ formatCurrency(account.balance) }}</p>
                    </div>
                </div>

                <!-- Barra de progreso -->
                <div class="mt-4">
                    <div class="w-full bg-slate-100 rounded-full h-2">
                        <div class="bg-emerald-500 h-2 rounded-full transition-all" :style="{ width: progressPercent + '%' }"></div>
                    </div>
                    <p class="text-xs text-slate-400 mt-1">{{ progressPercent }}% pagado</p>
                </div>
            </div>

            <!-- HISTORIAL DE ABONOS -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100">
                    <h3 class="font-bold text-slate-800">Historial de Abonos</h3>
                </div>

                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-slate-500 text-xs uppercase">
                        <tr>
                            <th class="px-6 py-3 text-left">Fecha</th>
                            <th class="px-6 py-3 text-left">Método</th>
                            <th class="px-6 py-3 text-left">Referencia</th>
                            <th class="px-6 py-3 text-right">Monto</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="payment in payments" :key="payment.id" class="hover:bg-slate-50">
                            <td class="px-6 py-3">{{ formatDateTime(payment.created_at) }}</td>
                            <td class="px-6 py-3">{{ payment.payment_method }}</td>
                            <td class="px-6 py-3 text-slate-500">{{ payment.transaction_reference || '—' }}</td>
                            <td class="px-6 py-3 text-right font-semibold text-emerald-600">
                                {{ formatCurrency(payment.amount) }}
                            </td>
                        </tr>

                        <tr v-if="payments.length === 0">
                            <td colspan="4" class="px-6 py-12 text-center text-slate-400">
                                Aún no se han registrado abonos para esta cuenta.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
