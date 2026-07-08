<script setup>
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
//import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

// Definimos la propiedad de manera estándar para el compilador de Vue
defineProps({
    invoice: Object
});

// Captura dinámica del Tenant de la Base de Datos para mantener la consistencia de colores de la empresa
const page = usePage();
const tenant = computed(() => page.props.tenant || {});
</script>

<template>
    <Head :title="`Factura de Compra #${invoice?.invoice_number || ''}`" />

    <AuthenticatedLayout>
        <!-- CABECERA SUPERIOR INDENTADA (Igual a Contactos) -->
        <nav class="bg-white border-b border-slate-200 px-6 py-4 flex justify-between items-center shadow-sm">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg flex items-center justify-center text-white font-bold text-sm"
                     :style="{ backgroundColor: tenant.primary_color }">
                    {{ tenant.company_name?.substring(0,2).toUpperCase() }}
                </div>
                <div>
                    <span class="font-bold text-slate-800 text-lg">{{ tenant.company_name }} -
                        <Link href="/purchase-invoices" class="text-xs font-semibold uppercase tracking-wider text-slate-500 hover:text-slate-800 transition-colors">
                            Facturas de Compra
                        </Link>
                        <span class="text-slate-300">/</span>
                        <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Detalle</span>
                    </span>
                    <h1 class="text-2xl font-bold text-slate-900 mt-1 flex items-center gap-3">
                        Factura de Compra #{{ invoice?.invoice_number || 'N/A' }}
                    </h1>
                </div>
            </div>
            <div class="flex items-center gap-4">
                <span
                    class="px-3 py-1.5 rounded-lg text-xs font-bold uppercase tracking-wider border shadow-sm"
                    :class="invoice?.payment_status === 'ANULADA'
                        ? 'bg-rose-50 text-rose-700 border-rose-200'
                        : 'bg-green-50 text-green-700 border-green-200'"
                >
                    {{ invoice?.payment_status || 'REGISTRADA' }}
                </span>

                <Link
                    href="/purchase-invoices"
                    class="px-4 py-2 text-xs font-semibold text-slate-600 bg-white border border-slate-200 rounded-lg shadow-sm hover:bg-slate-50 transition-all flex items-center gap-1"
                >
                    ← Volver al Listado
                </Link>
            </div>
        </nav>

        <div class="py-6 px-4 sm:px-6 lg:px-8 bg-slate-50 min-h-[calc(100vh-115px)]">
            <div class="max-w-7xl mx-auto">

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">

                    <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm">
                        <h2 class="text-xs font-bold uppercase text-slate-400 tracking-wider mb-4 pb-2 border-b border-slate-100 flex items-center gap-1.5">
                            <span class="w-1.5 h-3 rounded-sm" :style="{ backgroundColor: tenant.primary_color }"></span>
                            Información General
                        </h2>
                        <div class="space-y-3 text-sm">
                            <div>
                                <span class="text-xs font-medium text-slate-400 block uppercase">Número de Factura</span>
                                <span class="text-slate-900 font-semibold text-base">{{ invoice?.invoice_number || 'N/A' }}</span>
                            </div>
                            <div>
                                <span class="text-xs font-medium text-slate-400 block uppercase">Fecha de Emisión</span>
                                <span class="text-slate-700 font-medium">{{ invoice?.issue_date || 'N/A' }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm lg:col-span-2">
                        <h2 class="text-xs font-bold uppercase text-slate-400 tracking-wider mb-4 pb-2 border-b border-slate-100 flex items-center gap-1.5">
                            <span class="w-1.5 h-3 rounded-sm" :style="{ backgroundColor: tenant.primary_color }"></span>
                            Datos del Proveedor
                        </h2>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                            <div>
                                <span class="text-xs font-medium text-slate-400 block uppercase">Razón Social / Nombre Tercero</span>
                                <p class="text-slate-900 font-bold text-base mt-0.5">
                                    {{ invoice?.provider ? (invoice.provider.company_name || `${invoice.provider.first_name || ''} ${invoice.provider.last_name || ''}`) : 'Sin Proveedor Registrado' }}
                                </p>
                            </div>
                            <div>
                                <span class="text-xs font-medium text-slate-400 block uppercase">Identificación (NIT / CC)</span>
                                <p class="text-slate-700 font-semibold mt-1 flex items-center gap-1.5">
                                    {{ invoice?.provider?.document_number || 'N/A' }}
                                    <span v-if="invoice?.provider?.document_type" class="px-1.5 py-0.5 bg-slate-100 text-slate-500 rounded text-xs font-mono">
                                        {{ invoice.provider.document_type }}
                                    </span>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden mb-6">
                    <div class="p-4 border-b border-slate-200 bg-slate-50/50">
                        <h2 class="text-xs font-bold uppercase text-slate-500 tracking-wider flex items-center gap-1.5">
                            Artículos e Ítems Registrados en la Compra
                        </h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-slate-100/75 border-b border-slate-200 text-slate-500 text-xs font-bold uppercase tracking-wider">
                                    <th class="py-3 px-4">Producto / Detalle</th>
                                    <th class="py-3 px-4 text-center w-24">Cantidad</th>
                                    <th class="py-3 px-4 text-right w-40">Precio Unitario</th>
                                    <th class="py-3 px-4 text-right w-44">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 text-sm">
                                <tr v-for="item in invoice?.items" :key="item.id" class="hover:bg-slate-200/80 transition-colors">
                                    <td class="py-2 px-4">
                                        <div class="font-semibold text-slate-800">
                                            {{ item.product?.name || 'Producto no identificado' }}
                                        </div>
                                        <div v-if="item.product?.code" class="text-xs text-slate-400 font-mono mt-0.5">
                                            SKU: {{ item.product.code }}
                                        </div>
                                    </td>
                                    <td class="py-2 px-4 text-center font-bold text-slate-700">
                                        {{ Number(item.quantity || 0) }}
                                    </td>
                                    <td class="py-2 px-4 text-right font-medium text-slate-600">
                                        ${{ Number(item.price_unit || 0).toLocaleString('co-CO', { minimumFractionDigits: 2 }) }}
                                    </td>
                                    <td class="py-2 px-4 text-right font-bold text-slate-900">
                                        ${{ Number(item.subtotal || 0).toLocaleString('co-CO', { minimumFractionDigits: 2 }) }}
                                    </td>
                                </tr>
                                <tr v-if="!invoice?.items || invoice.items.length === 0">
                                    <td colspan="4" class="py-8 text-center text-slate-400 text-xs uppercase tracking-wider">
                                        Esta factura de compra no contiene artículos registrados.
                                    </td>
                                end</tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="bg-slate-50/70 p-5 border-t border-slate-200 flex justify-end">
                        <div class="w-full sm:w-80 space-y-2.5 text-sm">
                            <div class="flex justify-between text-slate-500 font-medium">
                                <span>Subtotal Neto:</span>
                                <span class="text-slate-800">${{ Number(invoice?.subtotal || invoice?.total || 0).toLocaleString('co-CO', { minimumFractionDigits: 2 }) }}</span>
                            </div>
                            <div class="flex justify-between text-slate-500 font-medium border-b border-slate-200 pb-2.5">
                                <span>Impuestos / IVA:</span>
                                <span class="text-slate-800">$0.00</span>
                            </div>
                            <div class="flex justify-between items-center pt-1.5">
                                <span class="text-slate-900 font-bold text-base">Total Factura:</span>
                                <span class="font-extrabold text-xl tracking-tight" :style="{ color: tenant.primary_color }">
                                    ${{ Number(invoice?.total || 0).toLocaleString('co-CO', { minimumFractionDigits: 2 }) }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm" v-if="invoice?.notes">
                    <h2 class="text-xs font-bold uppercase text-slate-400 tracking-wider mb-2 pb-1 border-b border-slate-50 flex items-center gap-1.5">
                        Notas u Observaciones del Documento
                    </h2>
                    <p class="text-sm text-slate-600 whitespace-pre-wrap bg-slate-50 p-3 rounded-lg border border-slate-100 font-medium leading-relaxed">{{ invoice.notes }}</p>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
