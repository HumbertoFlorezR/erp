<script setup>
import { ref, watch } from 'vue';
import { useForm, router } from '@inertiajs/vue3';

// 1. PROPIEDADES RECIBIDAS DESDE EL CONTROLADOR
const props = defineProps({
    products: Object,
    filters: Object
});

// 2. BUSCADOR Y FILTROS REACTIVOS
const search = ref(props.filters.search || '');
const currentType = ref(props.filters.type || 'TODOS');
const lowStockFilter = ref(props.filters.low_stock || false);

const applyFilters = () => {
    router.get('/products', {
        search: search.value,
        type: currentType.value === 'TODOS' ? null : currentType.value,
        low_stock: lowStockFilter.value ? true : null
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true
    });
};

// ⏱️ Debounce nativo hecho a mano para evitar instalar lodash
let debounceTimeout = null;
watch(search, (newValue) => {
    clearTimeout(debounceTimeout);
    debounceTimeout = setTimeout(() => {
        applyFilters();
    }, 350); // Espera 350ms después de que el usuario deja de escribir
});

const filterByType = (type) => {
    currentType.value = type;
    if (type === 'SERVICIO') lowStockFilter.value = false;
    applyFilters();
};

const toggleLowStock = () => {
    lowStockFilter.value = !lowStockFilter.value;
    applyFilters();
};

// 🚀 NUEVA FUNCIÓN PARA CARGAR DATOS EN LA MODAL
const openEditModal = (item) => {
    isEditing.value = true;
    editingId.value = item.id;

    form.type = item.type;
    form.code = item.code || '';
    form.name = item.name;
    form.description = item.description || '';
    form.price_excluding_tax = item.price_excluding_tax;
    form.tax_rate = item.tax_rate;
    form.tax_type = item.tax_type;
    form.minimum_stock = item.minimum_stock;
    form.manage_stock = item.manage_stock;
    form.is_perishable = item.is_perishable;
    form.unit_measure_code = item.unit_measure_code;

    showModal.value = true;
};

// 3. ESTADOS Y FORMULARIO PARA LA MODAL (CREACIÓN / EDICIÓN)
const showModal = ref(false);
const isEditing = ref(false);
const editingId = ref(null);

const form = useForm({
    type: 'PRODUCTO',
    code: '',
    name: '',
    description: '',
    price_excluding_tax: 0,
    tax_rate: 19,
    tax_type: 'GRAVADO',
    minimum_stock: 0,
    manage_stock: false,
    is_perishable: false,
    unit_measure_code: '94'
});

const openCreateModal = () => {
    isEditing.value = false;
    editingId.value = null;
    form.reset();
    showModal.value = true;
};

// Observar cambio de tipo dentro del formulario para ajustar defaults
watch(() => form.type, (newType) => {
    if (newType === 'SERVICIO') {
        form.manage_stock = false;
        form.minimum_stock = 0;
        form.is_perishable = false;
        form.unit_measure_code = 'WSD';
    } else {
        form.unit_measure_code = '94';
    }
});

const toggleStatus = (id) => {
    router.patch(`/products/${id}/toggle`, {}, { preserveScroll: true });
};

const submit = () => {
    if (isEditing.value) {
        form.put(`/products/${editingId.value}`, {
            onSuccess: () => {
                showModal.value = false;
                form.reset();
            }
        });
    } else {
        form.post('/products', {
            onSuccess: () => {
                showModal.value = false;
                form.reset();
            }
        });
    }
};
</script>

<template>
    <div class="p-6 bg-slate-50 min-h-screen">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-800 tracking-tight">Catálogo de Productos y Servicios</h1>
                <p class="text-sm text-slate-500">Gestión integrada de inventario, costos, tarifas de IVA y estándares DIAN.</p>
            </div>
            <button @click="openCreateModal" class="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-sm px-4 py-2.5 rounded-xl shadow-sm transition-all flex items-center gap-2">
                <span>+ Nuevo Item</span>
            </button>
        </div>

        <div class="bg-white p-4 rounded-2xl shadow-sm border border-slate-100 flex flex-col md:flex-row justify-between gap-4 mb-6">
            <div class="flex flex-wrap items-center gap-2">
                <button @click="filterByType('TODOS')" :class="currentType === 'TODOS' && !lowStockFilter ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'" class="px-4 py-1.5 rounded-lg text-xs font-semibold transition-all">Todos</button>
                <button @click="filterByType('PRODUCTO')" :class="currentType === 'PRODUCTO' && !lowStockFilter ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'" class="px-4 py-1.5 rounded-lg text-xs font-semibold transition-all">Productos</button>
                <button @click="filterByType('SERVICIO')" :class="currentType === 'SERVICIO' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'" class="px-4 py-1.5 rounded-lg text-xs font-semibold transition-all">Servicios</button>

                <button @click="toggleLowStock" :class="lowStockFilter ? 'bg-red-600 text-white shadow-sm shadow-red-100' : 'bg-red-50 text-red-600 hover:bg-red-100'" class="px-4 py-1.5 rounded-lg text-xs font-semibold transition-all flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-current animate-pulse"></span>
                    Stock Bajo / Críticos
                </button>
            </div>

            <div class="w-full md:w-80">
                <input v-model="search" type="text" placeholder="Buscar por código o nombre..." class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 text-sm focus:outline-none focus:border-slate-400 text-slate-700 placeholder-slate-400 transition-all" />
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-100 text-[11px] font-bold tracking-wider text-slate-400 uppercase">
                            <th class="py-4 px-6">Código / Tipo</th>
                            <th class="py-4 px-6">Descripción del Ítem</th>
                            <th class="py-4 px-6 text-right">Costo Prom.</th>
                            <th class="py-4 px-6 text-right">Precio Base</th>
                            <th class="py-4 px-6 text-center">IVA</th>
                            <th class="py-4 px-6 text-right">Margen</th>
                            <th class="py-4 px-6 text-center">Stock</th>
                            <th class="py-4 px-6 text-center">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50 text-sm text-slate-600">
                        <tr v-for="item in products.data" :key="item.id"
                            @click="openEditModal(item)"
                            :class="item.is_low_stock ? 'bg-red-50/40 hover:bg-red-50/70' : 'hover:bg-slate-50/50'"
                            class="transition-colors cursor-pointer"
                        >
                            <td class="py-4 px-6">
                                <span class="font-mono text-xs font-semibold text-slate-700 bg-slate-100 px-2 py-0.5 rounded-md block w-max mb-1">{{ item.code || 'S/C' }}</span>
                                <span :class="item.type === 'PRODUCTO' ? 'text-blue-600 bg-blue-50' : 'text-purple-600 bg-purple-50'" class="text-[10px] font-bold px-1.5 py-0.5 rounded uppercase">{{ item.type }}</span>
                            </td>
                            <td class="py-4 px-6">
                                <div class="font-semibold text-slate-800">{{ item.name }}</div>
                                <div class="text-xs text-slate-400 max-w-xs truncate">{{ item.description || 'Sin descripción' }}</div>
                            </td>
                            <td class="py-4 px-6 text-right font-mono text-slate-500">${{ item.average_cost }}</td>
                            <td class="py-4 px-6 text-right font-mono font-semibold text-slate-800">${{ item.price_excluding_tax }}</td>
                            <td class="py-4 px-6 text-center">
                                <span :class="item.tax_type === 'GRAVADO' ? 'text-amber-700 bg-amber-50 border border-amber-100' : 'text-slate-500 bg-slate-100'" class="text-xs px-2 py-0.5 rounded-md font-medium">
                                    {{ item.tax_rate }}%
                                </span>
                            </td>
                            <td class="py-4 px-6 text-right font-semibold text-emerald-600">{{ item.profit_margin }}%</td>
                            <td class="py-4 px-6 text-center">
                                <div v-if="item.type === 'PRODUCTO' && item.manage_stock">
                                    <span :class="item.is_low_stock ? 'text-red-700 bg-red-100 font-bold' : 'text-slate-700 bg-slate-100 font-medium'" class="px-2.5 py-0.5 rounded-full text-xs">
                                        {{ item.stock }}
                                    </span>
                                    <span class="text-[10px] text-slate-400 block mt-1">Mín: {{ item.minimum_stock }}</span>
                                </div>
                                <span v-else class="text-xs text-slate-400">—</span>
                            </td>
                            <td class="py-4 px-6 text-center">
                                <button @click="toggleStatus(item.id)" class="relative inline-flex items-center cursor-pointer focus:outline-none">
                                    <div :class="item.is_active ? 'bg-emerald-500' : 'bg-slate-200'" class="w-9 h-5 rounded-full transition-colors duration-200">
                                        <div :class="item.is_active ? 'translate-x-4' : 'translate-x-0.5'" class="w-4 h-4 bg-white rounded-full mt-0.5 transition-transform duration-200 shadow-sm"></div>
                                    </div>
                                </button>
                            </td>
                        </tr>
                        <tr v-if="products.data.length === 0">
                            <td colspan="8" class="text-center py-12 text-slate-400 text-sm">No se encontraron productos ni servicios registrados.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-if="showModal" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm flex justify-center items-center z-50 p-4">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-150">
                <div class="flex justify-between items-center px-6 py-4 border-b border-slate-100">
                    <h3 class="text-lg font-bold text-slate-800">
                        {{ isEditing ? 'Editar Ítem del Catálogo' : 'Registrar Ítem en Catálogo' }}
                    </h3>
                    <button @click="showModal = false" class="text-slate-400 hover:text-slate-600 text-xl font-medium">&times;</button>
                </div>

                <form @submit.prevent="submit" class="p-6 space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Tipo de Catálogo</label>
                            <select v-model="form.type" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:border-slate-400">
                                <option value="PRODUCTO">PRODUCTO (Bien Físico)</option>
                                <option value="SERVICIO">SERVICIO (Intangible)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Código / SKU</label>
                            <input v-model="form.code" type="text" placeholder="Ej: PROD-001" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:border-slate-400" />
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Nombre Comercial</label>
                        <input v-model="form.name" type="text" placeholder="Nombre completo del artículo o servicio" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:border-slate-400" required />
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Descripción Interna</label>
                        <textarea v-model="form.description" rows="2" placeholder="Detalles o especificaciones adicionales..." class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:border-slate-400"></textarea>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Precio Base (Sin IVA)</label>
                            <input v-model.number="form.price_excluding_tax" type="number" step="0.01" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:border-slate-400" required />
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Régimen Tarifario</label>
                            <select v-model="form.tax_type" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:border-slate-400">
                                <option value="GRAVADO">GRAVADO</option>
                                <option value="EXENTO">EXENTO</option>
                                <option value="EXCLUIDO">EXCLUIDO</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Porcentaje de IVA</label>
                            <select v-model.number="form.tax_rate" :disabled="form.tax_type !== 'GRAVADO'" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:border-slate-400 disabled:opacity-50">
                                <option value="19">19% (General)</option>
                                <option value="5">5% (Diferencial)</option>
                                <option value="0">0%</option>
                            </select>
                        </div>
                    </div>

                    <div v-if="form.type === 'PRODUCTO'" class="p-4 bg-slate-50 rounded-2xl border border-slate-100 space-y-3">
                        <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block">Configuración de Bodega</span>
                        <div class="flex flex-wrap gap-6">
                            <label class="flex items-center gap-2 cursor-pointer text-sm text-slate-700">
                                <input v-model="form.manage_stock" type="checkbox" class="rounded text-emerald-600 focus:ring-emerald-500 border-slate-300" />
                                Controlar Inventario
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer text-sm text-slate-700">
                                <input v-model="form.is_perishable" type="checkbox" class="rounded text-emerald-600 focus:ring-emerald-500 border-slate-300" />
                                Es Perecedero (Vencimiento)
                            </label>
                        </div>
                        <div v-if="form.manage_stock" class="w-1/2 pt-1">
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Stock Mínimo para Alertas</label>
                            <input v-model.number="form.minimum_stock" type="number" min="0" class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:border-slate-400" />
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 pt-2 border-t border-slate-100">
                        <button type="button" @click="showModal = false" class="bg-slate-100 hover:bg-slate-200 text-slate-600 font-semibold text-sm px-4 py-2 rounded-xl transition-all">Cancelar</button>
                        <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-sm px-4 py-2 rounded-xl shadow-sm transition-all">Guardar Ítem</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
