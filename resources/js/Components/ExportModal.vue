<script setup>
import { ref, onMounted } from 'vue';
import { useForm } from '@inertiajs/vue3';

// 1. CONFIGURACIÓN DEL COMPONENTE REUTILIZABLE
const props = defineProps({
    isOpen: Boolean,             // Controla la visibilidad de la modal
    moduleName: String,          // Identificador técnico: 'contacts', 'products'
    availableColumns: Array,     // Estructura: [{ key: 'name', label: 'Nombre' }]
    currentData: Array           // El set de datos actualmente filtrados en la tabla
});

const emit = defineEmits(['close']);

// 2. ESTADOS REACTIVOS
const selectedColumns = ref([]);
const selectedFormat = ref('XLSX');
const saveAsDefault = ref(false);

// Inicializar todas las columnas como seleccionadas por defecto
onMounted(() => {
    selectedColumns.value = props.availableColumns.map(col => col.key);
});

// 3. SELECCIÓN DINÁMICA
const toggleAll = (event) => {
    if (event.target.checked) {
        selectedColumns.value = props.availableColumns.map(col => col.key);
    } else {
        selectedColumns.value = [];
    }
};

// 4. ACCIÓN: PROCESAR EXPORTACIÓN
const submitExport = () => {

// ... dentro de const submitExport = () => { ...

    // Crear un formulario nativo temporal
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '/export/download';

    // 🔒 DETECCIÓN INFELLIBLE DEL TOKEN CSRF DESDE INERTIA
    // Buscamos primero en el objeto global de Inertia, si no, caemos en la meta tag tradicional
    const csrfToken = window._token || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    if (csrfToken) {
        const csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = '_token';
        csrfInput.value = csrfToken;
        form.appendChild(csrfInput);
    } else {
        // Truco definitivo si Laragon/Inertia no lo exponen en el DOM:
        // Buscamos la cookie de sesión XSRF-TOKEN que Axios/Inertia usan internamente
        const tokenFromCookie = document.cookie
            .split('; ')
            .find(row => row.startsWith('XSRF-TOKEN='))
            ?.split('=')[1];

        if (tokenFromCookie) {
            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = '_token';
            csrfInput.value = decodeURIComponent(tokenFromCookie);
            form.appendChild(csrfInput);
        }
    }

    if (selectedColumns.value.length === 0) {
        alert('Debes seleccionar al menos una columna para exportar.');
        return;
    }

    // Filtrar los nombres visibles (headings) emparejados con las columnas técnicas elegidas
    const activeHeadings = props.availableColumns
        .filter(col => selectedColumns.value.includes(col.key))
        .map(col => col.label);

    // Si el usuario quiere guardar sus preferencias a futuro, disparamos a la BD en segundo plano
    if (saveAsDefault.value) {
        const prefForm = useForm({
            module: props.moduleName,
            default_format: selectedFormat.value,
            selected_columns: selectedColumns.value
        });
        prefForm.post('/export/preferences', { preserveScroll: true });
    }


    // Preparar el paquete de datos estructurados para el backend
    const payload = {
        format: selectedFormat.value,
        module: props.moduleName,
        columns: selectedColumns.value,
        headings: activeHeadings,
        data: props.currentData
    };

    // Añadir las variables como inputs ocultos en el formulario
    for (const [key, value] of Object.entries(payload)) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = key;
        input.value = typeof value === 'object' ? JSON.stringify(value) : value;
        form.appendChild(input);
    }

    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);

    emit('close');
};
</script>

<template>
    <div v-if="isOpen" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm flex justify-center items-center z-50 p-4">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg overflow-hidden animate-in fade-in zoom-in-95 duration-150">

            <div class="flex justify-between items-center px-6 py-4 border-b border-slate-100">
                <div>
                    <h3 class="text-lg font-bold text-slate-800">Motor de Exportación Inteligente</h3>
                    <p class="text-xs text-slate-400">Personaliza tus columnas y el formato del reporte.</p>
                </div>
                <button @click="$emit('close')" class="text-slate-400 hover:text-slate-600 text-xl font-medium">&times;</button>
            </div>

            <div class="p-6 space-y-5">

                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-2">1. Formato del Archivo</label>
                    <div class="grid grid-cols-4 gap-2">
                        <button v-for="fmt in ['XLSX', 'CSV', 'PDF', 'TXT']" :key="fmt" type="button"
                            @click="selectedFormat = fmt"
                            :class="selectedFormat === fmt ? 'bg-slate-900 border-slate-900 text-white font-bold' : 'bg-slate-50 border-slate-200 text-slate-600 hover:bg-slate-100'"
                            class="py-2.5 rounded-xl border text-xs font-semibold tracking-wider transition-all text-center">
                            {{ fmt }}
                        </button>
                    </div>
                </div>

                <div>
                    <div class="flex justify-between items-center mb-2">
                        <label class="block text-xs font-bold text-slate-500 uppercase">2. Seleccionar Columnas Visibles</label>
                        <label class="flex items-center gap-1.5 text-xs text-emerald-600 font-semibold cursor-pointer">
                            <input type="checkbox" checked @change="toggleAll" class="rounded text-emerald-600 focus:ring-emerald-500 border-slate-300 w-3.5 h-3.5" />
                            Marcar Todas
                        </label>
                    </div>

                    <div class="bg-slate-50 border border-slate-100 rounded-2xl p-4 max-h-48 overflow-y-auto grid grid-cols-2 gap-3">
                        <div v-for="column in availableColumns" :key="column.key" class="flex items-center gap-2">
                            <input type="checkbox" :id="'col-' + column.key" :value="column.key" v-model="selectedColumns"
                                class="rounded text-slate-900 focus:ring-slate-800 border-slate-300 w-4 h-4 cursor-pointer" />
                            <label :for="'col-' + column.key" class="text-sm text-slate-700 cursor-pointer select-none truncate">{{ column.label }}</label>
                        </div>
                    </div>
                </div>

                <div class="pt-2">
                    <label class="flex items-center gap-2 cursor-pointer text-sm font-medium text-slate-600 bg-slate-50 hover:bg-slate-100/80 p-3 rounded-xl border border-slate-100 transition-colors">
                        <input v-model="saveAsDefault" type="checkbox" class="rounded text-emerald-600 focus:ring-emerald-500 border-slate-300 w-4 h-4" />
                        <div>
                            <span>Recordar mi configuración de columnas</span>
                            <span class="block text-[11px] text-slate-400 font-normal">Se aplicará automáticamente en tus próximas descargas.</span>
                        </div>
                    </label>
                </div>

            </div>

            <div class="flex justify-end gap-2 px-6 py-4 bg-slate-50 border-t border-slate-100">
                <button type="button" @click="$emit('close')" class="bg-white border border-slate-200 hover:bg-slate-100 text-slate-600 font-semibold text-sm px-4 py-2 rounded-xl transition-all">Cancelar</button>
                <button type="button" @click="submitExport" class="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-sm px-5 py-2 rounded-xl shadow-sm transition-all flex items-center gap-2">
                    📥 Generar Descarga
                </button>
            </div>

        </div>
    </div>
</template>
