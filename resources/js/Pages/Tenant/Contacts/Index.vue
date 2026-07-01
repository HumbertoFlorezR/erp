<script setup>
import { ref, watch, computed } from 'vue';
import { usePage, useForm, router, Head, Link } from '@inertiajs/vue3';

// 1. Propiedades del controlador
const props = defineProps({
    contacts: Object,
    filters: Object
});

const page = usePage();
const tenant = computed(() => page.props.tenant || {});
const user = computed(() => page.props.auth?.user || null);

// 2. Filtros y Buscador
const search = ref(props.filters.search || '');
const activeType = ref(props.filters.type || '');

watch([search, activeType], ([newSearch, newType]) => {
    router.get('/contacts', {
        search: newSearch,
        type: newType
    }, {
        preserveState: true,
        replace: true
    });
});

// 3. Método reactivo para cambiar el estado Activo/Inactivo (PATCH)
const toggleStatus = (id) => {
    // Construimos la URL limpia para que Inertia la mande directo a Laravel
    router.patch(`/contacts/${id}/toggle`, {}, {
        preserveScroll: true
    });
};

// 3. ESTADOS Y FORMULARIO PARA LA MODAL
const showModal = ref(false);
const isEditing = ref(false);
const editingId = ref(null);

const form = useForm({
    document_type: 'CC',
    document_number: '',
    verification_digit: '',
    first_name: '',
    last_name: '',
    company_name: '',
    email: '',
    phone: '',
    address: '',
    regime_type: 'NO_RESPONSABLE_IVA',
    is_client: true,
    is_supplier: false,
    is_employee: false,
});

// Función para abrir la modal en modo CREACIÓN
const openCreateModal = () => {
    isEditing.value = false;
    editingId.value = null;
    form.reset();
    showModal.value = true;
};

// Función para abrir la modal en modo EDICIÓN precargando los datos
const openEditModal = (contact) => {
    isEditing.value = true;
    editingId.value = contact.id;

    // Asignamos los valores al formulario (buscamos el registro real de la lista)
    form.document_type = contact.document_type;
    form.document_number = contact.document_number;
    form.verification_digit = contact.verification_digit || '';
    form.first_name = contact.first_name || '';
    form.last_name = contact.last_name || '';
    form.company_name = contact.company_name || '';
    form.email = contact.email || '';
    form.phone = contact.phone || '';
    form.address = contact.address || '';
    form.regime_type = contact.regime_type || 'NO_RESPONSABLE_IVA';
    // Convertimos explícitamente a booleano para el binding de Vue
    form.is_client = !!contact.is_client;
    form.is_supplier = !!contact.is_supplier;
    form.is_employee = !!contact.is_employee;

    showModal.value = true;
};

// Enviar formulario (Decide de forma dinámica si es POST o PUT)
const submit = () => {
    if (isEditing.value) {
        form.put(`/contacts/${editingId.value}`, {
            onSuccess: () => {
                showModal.value = false;
                form.reset();
            }
        });
    } else {
        form.post('/contacts', {
            onSuccess: () => {
                showModal.value = false;
                form.reset();
            }
        });
    }
};

// Computado rápido para saber si es NIT y mostrar el dígito de verificación
const isNit = computed(() => form.document_type === 'NIT');
</script>

<template>
    <Head title="Terceros y Contactos" />

    <div class="min-h-screen bg-slate-100 font-sans flex flex-col">
        <!-- CABECERA SUPERIOR -->
        <nav class="bg-white border-b border-slate-200 px-6 py-4 flex justify-between items-center shadow-sm">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg flex items-center justify-center text-white font-bold text-sm"
                     :style="{ backgroundColor: tenant.primary_color }">
                    {{ tenant.company_name?.substring(0,2).toUpperCase() }}
                </div>
                <span class="font-bold text-slate-800 text-lg">{{ tenant.company_name }} - Panel de Control</span>
            </div>

            <div class="flex items-center gap-4">
                <span class="text-sm text-slate-600 font-medium">Hola, {{ user?.name }}</span>
                <Link href="/dashboard" class="text-sm font-semibold text-slate-500 hover:text-slate-800 transition-colors">
                    Volver al Inicio
                </Link>
            </div>
        </nav>

        <!-- CUERPO DEL MÓDULO -->
        <div class="p-6 max-w-7xl w-full mx-auto space-y-6 flex-1">

            <div>
                <h2 class="text-2xl font-bold text-slate-800 tracking-tight">Administración de Terceros</h2>
                <p class="text-sm text-slate-500">Gestión integrada de clientes, proveedores y empleados para nómina.</p>
            </div>

            <!-- BARRA DE ACCIONES -->
            <div class="bg-white p-4 rounded-2xl shadow-sm border border-slate-200 flex flex-col md:flex-row md:items-center md:justify-between gap-4">

                <div class="flex bg-slate-100 p-1 rounded-xl self-start">
                    <button @click="activeType = ''" :class="['px-4 py-1.5 rounded-lg text-xs font-semibold transition-all', !activeType ? 'bg-white text-slate-800 shadow-sm' : 'text-slate-500 hover:text-slate-800']">Todos</button>
                    <button @click="activeType = 'client'" :class="['px-4 py-1.5 rounded-lg text-xs font-semibold transition-all', activeType === 'client' ? 'bg-white text-slate-800 shadow-sm' : 'text-slate-500 hover:text-slate-800']">Clientes</button>
                    <button @click="activeType = 'supplier'" :class="['px-4 py-1.5 rounded-lg text-xs font-semibold transition-all', activeType === 'supplier' ? 'bg-white text-slate-800 shadow-sm' : 'text-slate-500 hover:text-slate-800']">Proveedores</button>
                    <button @click="activeType = 'employee'" :class="['px-4 py-1.5 rounded-lg text-xs font-semibold transition-all', activeType === 'employee' ? 'bg-white text-slate-800 shadow-sm' : 'text-slate-500 hover:text-slate-800']">Empleados</button>
                </div>

                <div class="flex items-center gap-3 w-full md:w-auto">
                    <div class="relative flex-1 md:w-80">
                        <input type="text" v-model="search" placeholder="Buscar por documento o nombre..." class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:border-transparent transition-all" :style="{ '--tw-ring-color': tenant.primary_color }" />
                    </div>
                    <!-- 💡 CAMBIO: Abrir modal al dar clic -->
                    <button @click="openCreateModal" class="text-white font-semibold text-sm px-4 py-2 rounded-xl shadow-md transition-all active:scale-95 whitespace-nowrap animate-fade-in" :style="{ backgroundColor: tenant.primary_color }">
                        + Nuevo Tercero
                    </button>
                </div>
            </div>

            <!-- TABLA DE DATOS -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-xs font-bold text-slate-500 uppercase tracking-wider">
                                <th class="px-6 py-4">Identificación</th>
                                <th class="px-6 py-4">Nombre / Razón Social</th>
                                <th class="px-6 py-4">Contacto</th>
                                <th class="px-6 py-4">Roles</th>
                                <th class="px-6 py-4 text-center">Estado</th>
                                <th class="px-6 py-4 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 text-sm text-slate-600">
                            <tr v-for="contact in contacts.data" :key="contact.id" class="hover:bg-slate-50/50 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap font-medium text-slate-700">
                                    <span class="text-xs bg-slate-100 text-slate-500 px-1.5 py-0.5 rounded mr-1.5 font-bold">{{ contact.document_type }}</span>
                                    {{ contact.document_number }}{{ contact.verification_digit ? '-' + contact.verification_digit : '' }}
                                </td>
                                <td class="px-6 py-4 font-semibold text-slate-800">{{ contact.full_name }}</td>
                                <td class="px-6 py-4">
                                    <div class="flex flex-col">
                                        <span class="font-medium text-slate-700">{{ contact.phone || 'Sin Teléfono' }}</span>
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
                                    <button @click="toggleStatus(contact.id)" :class="['relative inline-flex h-5 w-10 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none', contact.is_active ? 'bg-emerald-500' : 'bg-slate-200']">
                                        <span :class="['pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out', contact.is_active ? 'translate-x-5' : 'translate-x-0']"></span>
                                    </button>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right">
                                    <button
                                        class="text-slate-600 hover:text-slate-900 bg-slate-100 hover:bg-slate-200 px-3 py-1.5 rounded-lg text-xs font-semibold transition-all"
                                        @click="openEditModal(contact)">
                                            Editar
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="contacts.data.length === 0">
                                <td colspan="6" class="px-6 py-12 text-center text-slate-400">No hay ningún tercero registrado con los filtros aplicados.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- 🏢 MODAL FLOTANTE DE CREACIÓN -->
        <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <!-- Capa oscura de fondo -->
            <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm transition-opacity" @click="showModal = false"></div>

            <!-- Contenedor del Formulario -->
            <div class="bg-white rounded-2xl shadow-xl border border-slate-200 max-w-2xl w-full max-h-[90vh] overflow-y-auto relative z-10 p-6 flex flex-col">
                <div class="flex justify-between items-center pb-4 border-b border-slate-100 mb-4">
                    <h3 class="text-lg font-bold text-slate-800">
                        {{ isEditing ? 'Modificar Datos del Tercero' : 'Registrar Nuevo Tercero' }}
                    </h3>
                    <button @click="showModal = false" class="text-slate-400 hover:text-slate-600 text-xl font-bold">&times;</button>
                </div>

                <form @submit.prevent="submit" class="space-y-4 flex-1">
                    <!-- Fila 1: Tipo y Número de Documento -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Tipo Identificación</label>
                            <select v-model="form.document_type" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm focus:outline-none">
                                <option value="CC">Cédula de Ciudadanía</option>
                                <option value="NIT">NIT (Empresas)</option>
                                <option value="CE">Cédula de Extranjería</option>
                                <option value="PP">Pasaporte</option>
                            </select>
                        </div>
                        <div :class="isNit ? 'sm:col-span-1' : 'sm:col-span-2'">
                            <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Número Documento</label>
                            <input v-model="form.document_number" type="text" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm focus:outline-none" />
                        </div>
                        <div v-if="isNit">
                            <label class="block text-xs font-bold text-slate-600 uppercase mb-1">DV</label>
                            <input v-model="form.verification_digit" type="text" maxlength="1" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm text-center focus:outline-none" placeholder="0" />
                        </div>
                    </div>

                    <!-- Fila 2: Nombres Condicionales (Si es NIT es Razón Social, si es CC es Persona Natural) -->
                    <div v-if="isNit" class="transition-all">
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Razón Social / Nombre de la Empresa</label>
                        <input v-model="form.company_name" type="text" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm focus:outline-none" />
                    </div>
                    <div v-else class="grid grid-cols-1 sm:grid-cols-2 gap-4 transition-all">
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Nombres</label>
                            <input v-model="form.first_name" type="text" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm focus:outline-none" />
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Apellidos</label>
                            <input v-model="form.last_name" type="text" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm focus:outline-none" />
                        </div>
                    </div>

                    <!-- Fila 3: Ubicación y Régimen -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Régimen Tributario</label>
                            <select v-model="form.regime_type" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm focus:outline-none">
                                <option value="NO_RESPONSABLE_IVA">No Responsable de IVA</option>
                                <option value="RESPONSABLE_IVA">Responsable de IVA</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Dirección</label>
                            <input v-model="form.address" type="text" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm focus:outline-none" />
                        </div>
                    </div>

                    <!-- Fila 4: Datos de Contacto Directo -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Teléfono Móvil</label>
                            <input v-model="form.phone" type="text" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm focus:outline-none" placeholder="300..." />
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Correo Electrónico</label>
                            <input v-model="form.email" type="email" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm focus:outline-none" placeholder="correo@ejemplo.com" />
                        </div>
                    </div>

                    <!-- Fila 5: Clasificación del Tercero (Roles Múltiples) -->
                    <div class="bg-slate-50 p-3 rounded-xl border border-slate-200">
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-2">Clasificación en el Sistema (Roles)</label>
                        <div class="flex gap-6">
                            <label class="flex items-center text-sm text-slate-700 cursor-pointer select-none">
                                <input v-model="form.is_client" type="checkbox" class="rounded border-slate-300 mr-2" />
                                Es Cliente
                            </label>
                            <label class="flex items-center text-sm text-slate-700 cursor-pointer select-none">
                                <input v-model="form.is_supplier" type="checkbox" class="rounded border-slate-300 mr-2" />
                                Es Proveedor
                            </label>
                            <label class="flex items-center text-sm text-slate-700 cursor-pointer select-none">
                                <input v-model="form.is_employee" type="checkbox" class="rounded border-slate-300 mr-2" />
                                Es Empleado
                            </label>
                        </div>
                    </div>

                    <!-- Botones de Acción de la modal -->
                    <div class="flex gap-3 pt-4 border-t border-slate-100 justify-end">
                        <button type="button" @click="showModal = false" class="bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold py-2 px-4 rounded-xl text-sm transition-all">
                            Cancelar
                        </button>
                        <button type="submit" :disabled="form.processing" class="text-white font-semibold py-2 px-5 rounded-xl text-sm shadow-md transition-all flex items-center gap-2" :style="{ backgroundColor: tenant.primary_color }">
                            <span v-if="form.processing" class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                            {{ form.processing ? 'Guardando...' : 'Guardar Tercero' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>
</template>
