<script setup>
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';

const props = defineProps({
    account: Object
});

const page = usePage();
const tenant = computed(() => page.props.tenant || {});

const formatCurrency = (value) => {
    return new Intl.NumberFormat('es-CO', {
        style: 'currency',
        currency: 'COP',
        minimumFractionDigits: 0
    }).format(value || 0);
};

const formatDate = (value) => {
    if (!value) {
        return 'No disponible';
    }
    return new Date(value).toLocaleDateString('es-CO', {
        year: 'numeric',
        month: 'long',
        day: '2-digit'
    });
};
</script>

<template>        <!-- CABECERA SUPERIOR -->
    <nav class="bg-white border-b border-slate-200 px-6 py-4 flex justify-between items-center shadow-sm">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg flex items-center justify-center text-white font-bold text-sm"
                    :style="{ backgroundColor: tenant.primary_color || '#0f172a' }">
                {{ tenant.company_name?.substring(0,2).toUpperCase() || 'ERP' }}
            </div>

            <div>
                <span class="font-bold text-slate-800 text-lg">{{ tenant.company_name }} - Historial de Cuenta por Cobrar</span>
                <p class="text-sm text-slate-500">Monitoreo de créditos, fechas de vencimiento y registro de abonos.</p>
            </div>
        </div>

        <div class="flex items-center gap-4">
            <Link href="/accounts-receivable" class="text-sm font-semibold text-slate-500 hover:text-slate-800 transition-colors border-2 p-1 rounded-lg">
                Volver al Inicio
            </Link>
        </div>
    </nav>

    <div class="min-h-screen bg-slate-100 font-sans">
        <div class="max-w-7xl mx-auto p-6 space-y-6">
            <div class="bg-white border border-slate-200 rounded-3xl shadow-sm overflow-hidden">
                <div class="bg-slate-50 px-6 py-2 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div>
                        <p class="text-xs uppercase tracking-[0.16em] text-slate-500 font-semibold"></p>
                        <h1 class="mt-2 text-2xl font-bold text-slate-900">Cuenta #{{ account?.id || account?.sale_id }}</h1>
                        <p class="text-sm text-slate-500 mt-1">Cliente: <span class="font-semibold text-slate-700">{{ account?.customer?.company_name || `${account?.customer?.first_name || ''} ${account?.customer?.last_name || ''}`.trim() || 'Cliente no identificado' }}</span></p>
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-white" :class="account?.status === 'PAGADA' ? 'bg-emerald-600' : account?.status === 'VENCIDA' ? 'bg-rose-600' : 'bg-amber-600'">
                            {{ account?.status || 'SIN ESTADO' }}
                        </span>
                    </div>
                </div>

                <div class="grid gap-2 p-3 md:grid-cols-3">
                    <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p class="text-xs uppercase tracking-[0.16em] text-slate-500 font-semibold">Monto Original</p>
                        <p class="mt-3 text-3xl font-bold text-slate-900">{{ formatCurrency(account?.original_amount) }}</p>
                    </div>
                    <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p class="text-xs uppercase tracking-[0.16em] text-slate-500 font-semibold">Saldo Pendiente</p>
                        <p class="mt-3 text-3xl font-bold text-slate-900">{{ formatCurrency(account?.balance) }}</p>
                    </div>
                    <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p class="text-xs uppercase tracking-[0.16em] text-slate-500 font-semibold">Vencimiento</p>
                        <p class="mt-3 text-xl font-semibold text-slate-900">{{ formatDate(account?.due_date) }}</p>
                    </div>
                </div>

                <div class="grid gap-2 p-2 md:grid-cols-2">
                    <div class="rounded-3xl border border-slate-200 bg-white p-3 shadow-sm">
                        <h2 class="text-sm font-bold text-slate-900 mb-4">Detalles de la Venta</h2>
                        <div class="space-y-3 text-sm text-slate-600">
                            <div class="flex justify-between gap-4">
                                <span class="font-medium text-slate-500">ID Venta</span>
                                <span class="font-semibold text-slate-900">{{ account?.sale?.id || account?.sale_id }}</span>
                            </div>
                            <div class="flex justify-between gap-4">
                                <span class="font-medium text-slate-500">Estado Pago</span>
                                <span class="font-semibold text-slate-900">{{ account?.sale?.payment_status || 'N/A' }}</span>
                            </div>
                            <div class="flex justify-between gap-4">
                                <span class="font-medium text-slate-500">Total Venta</span>
                                <span class="font-semibold text-slate-900">{{ formatCurrency(account?.sale?.total) }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="rounded-3xl border border-slate-200 bg-white p-3 shadow-sm">
                        <h2 class="text-sm font-bold text-slate-900 mb-4">Cliente</h2>
                        <div class="space-y-3 text-sm text-slate-600">
                            <div>
                                <p class="font-medium text-slate-500">Nombre</p>
                                <p class="mt-1 text-slate-900">{{ account?.customer?.company_name || `${account?.customer?.first_name || ''} ${account?.customer?.last_name || ''}`.trim() || 'Sin cliente' }}</p>
                            </div>
                            <div>
                                <p class="font-medium text-slate-500">Documento</p>
                                <p class="mt-1 text-slate-900">{{ account?.customer?.document_type || '' }} {{ account?.customer?.document_number || '' }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-slate-50 border-t border-slate-200 p-6">
                    <h2 class="text-sm font-bold uppercase tracking-[0.2em] text-slate-500 mb-4">Historial de Pagos</h2>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-left text-sm text-slate-600">
                            <thead>
                                <tr class="border-b border-slate-200 bg-white text-slate-500 uppercase tracking-[0.14em] text-xs font-semibold">
                                    <th class="px-4 py-3">Fecha</th>
                                    <th class="px-4 py-3">Método</th>
                                    <th class="px-4 py-3 text-right">Monto</th>
                                    <th class="px-4 py-3 text-right">Recibido</th>
                                    <th class="px-4 py-3 text-right">Cambio</th>
                                    <th class="px-4 py-3">Referencia</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 bg-white">
                                <tr v-for="payment in account?.sale?.payments || []" :key="payment.id" class="hover:bg-slate-50">
                                    <td class="px-4 py-3">{{ formatDate(payment.created_at) }}</td>
                                    <td class="px-4 py-3">{{ payment.payment_method || 'N/A' }}</td>
                                    <td class="px-4 py-3 text-right font-semibold">{{ formatCurrency(payment.amount) }}</td>
                                    <td class="px-4 py-3 text-right">{{ formatCurrency(payment.received_amount) }}</td>
                                    <td class="px-4 py-3 text-right">{{ formatCurrency(payment.change_amount) }}</td>
                                    <td class="px-4 py-3">{{ payment.transaction_reference || '-' }}</td>
                                </tr>
                                <tr v-if="!(account?.sale?.payments?.length > 0)">
                                    <td colspan="6" class="px-4 py-8 text-center text-slate-400">No hay pagos registrados aún para esta cuenta.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
