<script setup>
import { ref, watch, computed } from 'vue';
import { usePage, router, Head, Link } from '@inertiajs/vue3';

// 1. Recibimos las propiedades desde el controlador
const props = defineProps({
    contacts: Object,
    filters: Object
});

const page = usePage();
const tenant = computed(() => page.props.tenant || {});

// 2. Estados reactivos para búsquedas y filtros
const search = ref(props.filters.search || '');
const activeType = ref(props.filters.type || '');

// Escuchamos cambios en el buscador y el filtro de tipo para actualizar la URL de Inertia
watch([search, activeType], ([newSearch, newType]) => {
    router.get('/contacts', {
        search: newSearch,
        type: newType
    }, {
        preserveState: true,
        replace: true
    });
});

// 3. Método rápido para cambiar el estado activo/inactivo (PATCH)
const toggleStatus = (id) => {
    router.patch(`/contacts/${id}/toggle`, {}, {
        preserveScroll: true
    });
};
</script>

<template>
    <Head title="Administración de Contactos" />

    <div class="min-h-screen bg-slate-50 flex">
        <aside class="w-64 bg-slate-900 text-slate-300 flex flex-col justify-between hidden md:flex">
            <div class="p-6">
                <div class="flex items-center gap-3 mb-8">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center text-white font-bold text-sm shadow-md"
                         :style="{ backgroundColor: tenant.primary_color }">
                        {{ tenant.company_name?.charAt(0) }}
                    </div>
                    <span class="font-bold text-white tracking-tight">{{ tenant.company_name }}</span>
                </div>
                <nav class="space-y-2">
                    <Link href="/dashboard" class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium hover:bg-slate-800 transition-all">
                        Dashboard
                    </Link>
                    <Link href="/contacts" class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium text-white shadow-sm"
                          :style="{ backgroundColor: tenant.primary_color }">
                        Terceros (Contactos)
                    </Link>
                </nav>
            </div>
        </aside>

        <main class="flex-1 flex flex-col min-w-0 overflow-hidden">
            <header class="bg-white border-b border-slate-100 h-16 flex items-center justify-between px-6 z-10 shadow-sm">
                <h1 class="text-xl font-bold text-slate-800 tracking-tight">Gestión de Terceros</h1>
                <div class="flex items-center gap-4">
                    <span class="text-sm font-medium text-slate-600">Hola, {{ page.props.auth?.user?.name }}</span>
                </div>
            </header>

            <div class="p-6 max-w-7xl w-full mx-auto space-y-6 overflow-y-auto flex-1">

                <div class="bg-white p-4 rounded-2xl shadow-sm border border-slate-100 flex flex-col md:flex-row md:items-center md:justify-between gap-4">

                    <div class="flex bg-slate-100 p-1 rounded-xl self-start">
                        <button @click="activeType = ''"
                                :class="['px-4 py-1.5 rounded-lg text-xs font-semibold transition-all', !activeType ? 'bg-white text-slate-800 shadow-sm' : 'text-slate-500 hover:text-slate-800']">
                            Todos
                        </button>
                        <button @click="activeType = 'client'"
                                :class="['px-4 py-1.5 rounded-lg text-xs font-semibold transition-all', activeType === 'client' ? 'bg-white text-slate-800 shadow-sm' : 'text-slate-500 hover:text-slate-800']">
                            Clientes
                        </button>
                        <button @click="activeType = 'supplier'"
                                :class="['px-4 py-1.5 rounded-lg text-xs font-semibold transition-all', activeType === 'supplier' ? 'bg-white text-slate-800 shadow-sm' : 'text-slate-500 hover:text-slate-800']">
                            Proveedores
                        </button>
                        <button @click="activeType = 'employee'"
                                :class="['px-4 py-1.5 rounded-lg text-xs font-semibold transition-all', activeType === 'employee' ? 'bg-white text-slate-800 shadow-sm' : 'text-slate-500 hover:text-slate-800']">
                            Empleados
                        </button>
                    </div>

                    <div class="flex items-center gap-3 w-full md:w-auto">
                        <div class="relative flex-1 md:w-64">
                            <input type="text"
                                   v-model="search"
                                   placeholder="Buscar por nombre o documento..."
                                   class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-4 pr-4 py-2 text-sm focus:outline-none focus:ring-2 focus:border-transparent transition-all"
                                   :style="{ '--tw-ring-color': tenant.primary_color }" />
                        </div>
                        <button class="text-white font-semibold text-sm px-4 py-2 rounded-xl shadow-md transition-all active:scale-95 whitespace-nowrap"
                                :style="{ backgroundColor: tenant.primary_color }">
                            + Nuevo Tercero
                        </button>
                    </div>
                </div>

                <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-slate-50/70 border-b border-slate-100 text-xs font-bold text-slate-500 uppercase tracking-wider">
                                    <th class="px-6 py-4">Identificación</th>
                                    <th class="px-6 py-4">Nombre / Razón Social</th>
                                    <th class="px-6 py-4">Contacto</th>
                                    <th class="px-6 py-4">Clasificación</th>
                                    <th class="px-6 py-4 text-center">Estado</th>
                                    <th class="px-6 py-4 text-right">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-sm text-slate-600">
                                <tr v-for="contact in contacts.data" :key="contact.id" class="hover:bg-slate-50/50 transition-colors">
                                    <td class="px-6 py-4 whitespace-nowrap font-medium text-slate-700">
                                        <span class="text-xs bg-slate-100 text-slate-500 px-1.5 py-0.5 rounded mr-1.5">{{ contact.document_type }}</span>
                                        {{ contact.document_number }}
                                    </td>
                                    <td class="px-6 py-4 font-semibold text-slate-800">
                                        {{ contact.full_name }}
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex flex-col">
                                            <span>{{ contact.phone || 'N/A' }}</span>
                                            <span class="text-xs text-slate-400">{{ contact.email || '-' }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex gap-1.5 flex-wrap">
                                            <span v-if="contact.is_client" class="bg-blue-50 text-blue-700 text-[10px] font-bold px-2 py-0.5 rounded-full border border-blue-100">Cliente</span>
                                            <span v-if="contact.is_supplier" class="bg-purple-50 text-purple-700 text-[10px] font-bold px-2 py-0.5 rounded-full border border-purple-100">Proveedor</span>
                                            <span v-if="contact.is_employee" class="bg-amber-50 text-amber-700 text-[10px] font-bold px-2 py-0.5 rounded-full border border-amber-100">Empleado</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center">
                                        <button @click="toggleStatus(contact.id)"
                                                :class="['relative inline-flex h-5 w-10 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none', contact.is_active ? 'bg-emerald-500' : 'bg-slate-200']">
                                            <span :class="['pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out', contact.is_active ? 'translate-x-5' : 'translate-x-0']"></span>
                                        </button>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-xs font-semibold">
                                        <button class="text-slate-600 hover:text-slate-900 bg-slate-100 hover:bg-slate-200 px-3 py-1.5 rounded-lg transition-all">
                                            Editar
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="contacts.data.length === 0">
                                    <td colspan="6" class="px-6 py-12 text-center text-slate-400">
                                        No se encontraron terceros registrados con los filtros aplicados.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-if="contacts.links && contacts.links.length > 3" class="bg-slate-50/50 px-6 py-4 border-t border-slate-100 flex items-center justify-between">
                        <span class="text-xs text-slate-500">Mostrando registros paginados</span>
                        <div class="flex gap-1">
                            <Component :is="link.url ? Link : 'span'"
                                       v-for="(link, index) in contacts.links"
                                       :key="index"
                                       :href="link.url"
                                       v-html="link.label"
                                       :class="['px-3 py-1 text-xs rounded-lg border font-medium transition-all', link.active ? 'text-white border-transparent' : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-50', !link.url ? 'opacity-40 cursor-not-allowed' : '']"
                                       :style="link.active ? { backgroundColor: tenant.primary_color } : {}" />
                        </div>
                    </div>
                </div>

            </div>
        </main>
    </div>
</template>
