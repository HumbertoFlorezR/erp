<script setup>
import { ref, watch, computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import debounce from 'lodash/debounce';
import { route } from 'ziggy-js';
import ExportModal from '@/Components/ExportModal.vue';

const showExportModal = ref(false);

// Propiedades del controlador
const props = defineProps({
    invoices: Object,
    filters: Object,
    ziggy_data: Object
});

// Estructura de columnas homologada para las preferencias de exportación
const invoiceColumns = [
    { key: 'invoice_number', label: 'Número Factura' },
    { key: 'provider_name', label: 'Proveedor' },
    { key: 'provider_document', label: 'NIT / CC Proveedor' },
    { key: 'issue_date', label: 'Fecha Emisión' },
    { key: 'due_date', label: 'Fecha Vencimiento' },
    { key: 'payment_method', label: 'Método de Pago' },
    { key: 'payment_status', label: 'Estado' },
    { key: 'total', label: 'Total Factura' },
    { key: 'notes', label: 'Notas / Observaciones' }
];

// Mapeo dinámico de datos a formato plano compatible con el motor de exportación
const processedInvoicesData = computed(() => {
    if (!props.invoices?.data) return [];
    return props.invoices.data.map(invoice => {
        const providerName = invoice.provider
            ? (invoice.provider.company_name && invoice.provider.company_name.trim() ? invoice.provider.company_name : `${invoice.provider.first_name || ''} ${invoice.provider.last_name || ''}`.trim())
            : 'N/A';

        return {
            invoice_number: invoice.invoice_number,
            provider_name: providerName,
            provider_document: invoice.provider?.document_number || 'N/A',
            issue_date: invoice.issue_date || '',
            due_date: invoice.due_date ? new Date(invoice.due_date).toLocaleDateString('es-CO', { timeZone: 'UTC' }) : '',
            payment_method: invoice.payment_method || '',
            payment_status: invoice.payment_status || '',
            total: invoice.total ? parseFloat(invoice.total) : 0,
            notes: invoice.notes || '-'
        };
    });
});

// Captura dinámica del Tenant de la Base de Datos para los colores y marca
const page = usePage();
const tenant = computed(() => page.props.tenant || {});

// Buscador reactivo acoplado a los filtros existentes
const search = ref(props.filters.search || '');

// Monitorear el buscador con debounce usando las rutas locales compartidas
watch(search, debounce((value) => {
    router.get(route('purchase-invoices.index', undefined, undefined, props.ziggy_data), { search: value }, {
        preserveState: true,
        replace: true
    });
}, 300));

// Función para ejecutar la Anulación Estratégica y clonación a Borrador
const handleCancelAndEdit = (invoice) => {
    if (confirm(`¿Está seguro de que desea anular la factura ${invoice.invoice_number}? Esto retirará la mercancía del inventario y cargará un borrador corregible.`)) {
        router.post(route('purchase-invoices.cancel', invoice.id, undefined, props.ziggy_data), {}, {
            onSuccess: () => {
                router.get(route('purchase-invoices.create', undefined, undefined, props.ziggy_data), {
                    initialData: {
                        contact_id: invoice.contact_id,
                        invoice_number: invoice.invoice_number + '-REV',
                        notes: `Corrección de la factura anulada ${invoice.invoice_number}`,
                        items: invoice.items || []
                    }
                });
            }
        });
    }
};

// Auxiliar estético adaptado al TEMA CLARO para las etiquetas de estado
const statusBadgeClass = (status) => {
    switch (status) {
        case 'PAGADA': return 'bg-emerald-50 text-emerald-700 border-emerald-200';
        case 'PENDIENTE': return 'bg-amber-50 text-amber-700 border-amber-200';
        case 'ANULADA': return 'bg-rose-50 text-rose-700 border-rose-200';
        default: return 'bg-slate-50 text-slate-600 border-slate-200';
    }
};
</script>

<template>
    <Head title="Historial de Compras" />

    <div class="min-h-screen bg-slate-100 font-sans flex flex-col">
        <!-- CABECERA SUPERIOR INDENTADA -->
        <nav class="bg-white border-b border-slate-200 px-6 py-4 flex justify-between items-center shadow-sm">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg flex items-center justify-center text-white font-bold text-sm"
                     :style="{ backgroundColor: tenant.primary_color }">
                    {{ tenant.company_name?.substring(0,2).toUpperCase() }}
                </div>
                <div>
                    <span class="font-bold text-slate-800 text-lg">{{ tenant.company_name }} - Facturas de Compra</span>
                    <p class="text-sm text-slate-500">Historial de adquisiciones e ingresos al inventario / Kardex.</p>
                </div>
            </div>
            <div class="flex items-center gap-4">
                <Link href="/dashboard" class="text-sm font-semibold text-slate-500 hover:text-slate-800 transition-colors border-2 p-1">
                    Volver al Inicio
                </Link>
            </div>
        </nav>

        <!-- CUERPO DEL MÓDULO -->
        <div class="p-6 max-w-7xl w-full mx-auto space-y-6 flex-1">

            <!-- BARRA DE ACCIONES -->
            <div class="bg-white p-4 rounded-2xl shadow-sm border border-slate-200 flex flex-col md:flex-row md:items-center md:justify-between gap-4">

                <!-- Buscador Central alineado a la izquierda -->
                <div class="relative flex-1 md:w-80 w-full">
                    <input
                        type="text"
                        v-model="search"
                        placeholder="Buscar por número de factura o proveedor..."
                        class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:border-transparent transition-all"
                        :style="{ '--tw-ring-color': tenant.primary_color }"
                    />
                </div>

                <!-- Botones de Acción integrados -->
                <div class="flex items-center gap-2 self-end md:self-auto">
                    <!-- 🌟 BOTÓN DE EXPORTACIÓN CONECTADO A LA COMPONENTE MODAL -->
                    <button @click="showExportModal = true" class="bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 font-semibold text-sm px-4 py-2.5 rounded-xl shadow-sm transition-all flex items-center gap-2">
                        <span>📥 Exportar Reporte</span>
                    </button>

                    <Link
                        :href="route('purchase-invoices.create', undefined, undefined, props.ziggy_data)"
                        class="text-white font-semibold text-sm px-4 py-2.5 rounded-xl shadow-md transition-all active:scale-95 whitespace-nowrap"
                        :style="{ backgroundColor: tenant.primary_color }"
                    >
                        + Nueva Compra
                    </Link>
                </div>
            </div>

            <!-- CONTENEDOR DE LA TABLA ESTILIZADA -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-xs font-bold text-slate-500 uppercase tracking-wider">
                                <th class="px-6 py-4">Fecha Emisión</th>
                                <th class="px-6 py-4">Número Factura</th>
                                <th class="px-6 py-4">Proveedor</th>
                                <th class="px-6 py-4">Vencimiento</th>
                                <th class="px-6 py-4 text-center">Estado</th>
                                <th class="px-6 py-4 text-right">Total Neto</th>
                                <th class="px-6 py-4 text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 text-sm text-slate-600">
                            <tr
                                v-for="invoice in invoices.data"
                                :key="invoice.id"
                                class="hover:bg-slate-200/80 transition-colors"
                            >
                                <td class="px-6 py-4 text-sm font-semibold">
                                    <Link
                                        :href="`/purchase-invoices/${invoice.id}`"
                                        class="text-blue-600 hover:text-blue-800 hover:underline transition-all block"
                                        :style="{ color: tenant.primary_color }"
                                    >
                                        {{ invoice.invoice_number }}
                                    </Link>
                                </td>
                                <td class="px-6 py-2 whitespace-nowrap font-mono font-bold" :style="{ color: tenant.primary_color }">
                                    {{ invoice.invoice_number }}
                                </td>
                                <td class="px-6 py-2 font-semibold text-slate-800">
                                    {{ invoice.provider ? (invoice.provider.company_name && invoice.provider.company_name.trim() ? invoice.provider.company_name : `${invoice.provider.first_name || ''} ${invoice.provider.last_name || ''}`.trim()) : 'N/A' }}
                                    <span class="block text-xs text-slate-400 font-mono font-normal" v-if="invoice.provider?.document_number">NIT: {{ invoice.provider.document_number }}</span>
                                </td>
                                <td class="px-6 py-2 whitespace-nowrap text-slate-500">
                                    {{ new Date(invoice.due_date).toLocaleDateString('es-CO', { timeZone: 'UTC' }) }}
                                </td>
                                <td class="px-6 py-2 whitespace-nowrap text-center">
                                    <span :class="['px-2.5 py-0.5 rounded-full text-[11px] font-bold border', statusBadgeClass(invoice.payment_status)]">
                                        {{ invoice.payment_status }}
                                    </span>
                                </td>
                                <td class="px-6 py-2 whitespace-nowrap text-right font-bold text-slate-800">
                                    $ {{ parseFloat(invoice.total).toLocaleString('es-CO', { minimumFractionDigits: 2 }) }}
                                </td>
                                <td class="px-6 py-2 whitespace-nowrap text-center">
                                    <div class="flex items-center justify-center">
                                        <button
                                            v-if="invoice.payment_status !== 'ANULADA'"
                                            @click="handleCancelAndEdit(invoice)"
                                            class="bg-rose-50 hover:bg-rose-100 text-rose-600 px-3 py-1.5 rounded-xl text-xs font-bold transition-all active:scale-95"
                                        >
                                            Anular y Corregir
                                        </button>
                                        <span v-else class="text-xs text-slate-400 italic">Sin acciones</span>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="invoices.data.length === 0">
                                <td colspan="7" class="px-6 py-12 text-center text-slate-400">
                                    No se encontraron facturas de compra registradas.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- PAGINACIÓN UNIFICADA -->
                <div v-if="invoices.links.length > 3" class="bg-slate-50 p-4 border-t border-slate-200 flex justify-center space-x-1">
                    <template v-for="(link, k) in invoices.links" :key="k">
                        <div v-if="link.url === null" class="px-3 py-1.5 text-xs text-slate-400 border border-slate-200 rounded-lg bg-white" v-html="link.label" />
                        <Link
                            v-else
                            :href="link.url"
                            class="px-3 py-1.5 text-xs rounded-lg border transition-all"
                            :class="link.active ? 'text-white border-transparent font-bold' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50'"
                            :style="link.active ? { backgroundColor: tenant.primary_color } : {}"
                            v-html="link.label"
                        />
                    </template>
                </div>
            </div>

        </div>
    </div>

    <!-- 🏢 MODAL INTEGRADA Y CONFIGURADA PARA FACTURAS DE COMPRA -->
    <ExportModal
        :is-open="showExportModal"
        module-name="purchase_invoices"
        :available-columns="invoiceColumns"
        :current-data="processedInvoicesData"
        @close="showExportModal = false"
    />
</template>
