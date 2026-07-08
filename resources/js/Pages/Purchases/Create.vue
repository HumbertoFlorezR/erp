<script setup>
import { ref, computed } from 'vue';
import { useForm, usePage, Head, Link } from '@inertiajs/vue3';
import axios from 'axios';

// Props inyectados desde el controlador de Laravel
const props = defineProps({
    providers: Array,
    products: Array,
    initialData: Object, // Datos precargados en caso de venir de una acción "Anular y Corregir"
    ziggy_data: Object,  // Recibimos las rutas seguras del controlador
});

// Captura dinámica del Tenant de la Base de Datos para mantener los colores y marca
const page = usePage();
const tenant = computed(() => page.props.tenant || {});

// --- CATÁLOGOS LOCALES REACTIVOS ---
const localProducts = ref([...props.products]);
const localProviders = ref([...props.providers]);

// --- ESTADOS PARA MODAL DE PRODUCTO EXPRESS ---
const isProductModalOpen = ref(false);
const quickProductForm = ref({
    name: '',
    code: '', // Corregido: Tu backend usa 'code' en lugar de 'code'
    manage_stock: true, // Corregido: Tu backend usa 'manage_stock'
    price_excluding_tax: 0 // Corregido: Requerido por tu ProductController
});

// --- ESTADOS PARA MODAL DE PROVEEDOR EXPRESS ---
const isProviderModalOpen = ref(false);
const quickProviderForm = ref({
    company_name: '', // Corregido: Tu backend usa 'company_name' o first_name/last_name
    document_number: '', // Corregido: Tu backend usa 'document_number'
    document_type: 'NIT', // Requerido por tu ContactController
    regime_type: 'RESPONSABLE_IVA', // Requerido por tu ContactController
    first_name: '', // Corregido: Tu backend usa 'first_name'
    last_name: '' // Corregido: Tu backend usa 'last_name'
});

// --- FORMULARIO PRINCIPAL CON INERTIAJS ---
const form = useForm({
    contact_id: props.initialData?.contact_id || '',
    invoice_number: props.initialData?.invoice_number || '',
    issue_date: props.initialData?.issue_date || new Date().toISOString().substr(0, 10),
    due_date: props.initialData?.due_date || new Date().toISOString().substr(0, 10),
    notes: props.initialData?.notes || '',
    discount: 0.00, // Forzamos un valor base para que el backend no reciba null
    items: props.initialData?.items?.map(i => ({
        product_id: i.product_id,
        quantity: parseFloat(i.quantity) || 0,
        price_unit: parseFloat(i.price_unit) || 0,
        tax_rate: parseFloat(i.tax_rate) || 0,
        batch_number: i.batch_number || '',
        expiration_date: i.expiration_date || ''
    })) || []
});

// --- MANEJO DE FILAS DINÁMICAS ---
const addItem = () => {
    form.items.push({
        product_id: '',
        quantity: 1,      // Base 1 para evitar fallos de min:0.0001
        price_unit: 0,    // Inicializado en 0 para cumplir con 'numeric|min:0'
        tax_rate: 19,     // Tarifa estándar por defecto
        batch_number: '',
        expiration_date: ''
    });
};

const removeItem = (index) => {
    form.items.splice(index, 1);
};

const onProductChange = (index) => {
    const selectedId = form.items[index].product_id;
    const prod = localProducts.value.find(p => p.id === selectedId);
    if (prod) {
        form.items[index].price_unit = parseFloat(prod.price_cost) || 0;
    }
};

const isPerishable = (productId) => {
    const prod = localProducts.value.find(p => p.id === productId);
    return prod ? !!prod.controlar_inventario : false;
};

// --- GUARDAR PRODUCTO RÁPIDO (AXIOS) ---
const saveQuickProduct = async () => {
    try {
        const response = await axios.post('/products', {
            code: quickProductForm.value.code || '',
            name: quickProductForm.value.name,
            tax_rate: parseFloat(quickProductForm.value.tax_rate) || 19,
            tax_type: quickProductForm.value.tax_type || 'GRAVADO',
            manage_stock: quickProductForm.value.controlar_inventario ? 1 : 0,
            price_excluding_tax: 0 // Requerido por el controlador de productos para la base
        });

        if (response.data && response.data.success) {
            const p = response.data.product;

            const mappedProduct = {
                id: p.id,
                name: p.name,
                sku: p.code || '',
                price_cost: 0,
                stock: 0,
                controlar_inventario: p.manage_stock
            };

            // Lo inyectamos al catálogo local para que aparezca en los selects en caliente
            localProducts.value.push(mappedProduct);

            // Cerramos la modal y limpiamos
            isProductModalOpen.value = false;
            quickProductForm.value = { name: '', code: '', tax_rate: 19, tax_type: 'GRAVADO', controlar_inventario: true };

            alert('Producto creado e indexado correctamente.');
        }
    } catch (error) {
        console.error(error);
        alert('Error al crear el producto express: ' + (error.response?.data?.message || 'Verifique los campos requeridos'));
    }
};

// --- OPERACIONES MATEMÁTICAS ---
const totals = computed(() => {
    let subtotal = 0;
    let tax = 0;
    form.items.forEach(item => {
        const lineSubtotal = (item.quantity || 0) * (item.price_unit || 0);
        const lineTax = lineSubtotal * ((item.tax_rate || 0) / 100);
        subtotal += lineSubtotal;
        tax += lineTax;
    });
    return {
        subtotal,
        tax,
        total: (subtotal + tax) - (form.discount || 0)
    };
});

const submit = () => {
    form.post('/purchase-invoices', {
        onError: (errors) => {
            console.log("❌ Errores de validación devueltos por Laravel:", errors);
            alert("No se pudo grabar la compra. Revisa los campos obligatorios.");
        },
        onSuccess: (page) => {
            // Evaluamos si Laravel inyectó un error del bloque catch en la sesión
            if (page.props.errors && page.props.errors.error) {
                console.error("❌ Error interno del servidor:", page.props.errors.error);
                alert("Atención: El servidor rechazó el guardado físico: " + page.props.errors.error);
            } else {
                console.log("✅ Compra grabada con éxito total en la Base de Datos.");
            }
        }
    });
};
</script>

<template>
    <Head title="Registrar Factura de Compra" />
    <div v-if="Object.keys(form.errors).length" class="bg-rose-50 border border-rose-200 text-rose-700 p-4 rounded-xl mb-4 text-sm">
        <p class="font-bold">Por favor corrige los siguientes errores:</p>
        <ul>
            <li v-for="(error, key) in form.errors" :key="key">• {{ error }}</li>
        </ul>
    </div>
    <div v-if="Object.keys(form.errors).length" class="bg-rose-50 border border-rose-200 text-rose-700 p-4 rounded-xl mb-4 text-sm">
        <p class="font-bold">Por favor corrige los campos indicados:</p>
        <ul>
            <li v-for="(msg, key) in form.errors" :key="key">• {{ msg }}</li>
        </ul>
    </div>

    <div class="min-h-screen bg-slate-100 font-sans flex flex-col">
        <!-- CABECERA SUPERIOR INSTITUCIONAL (Idéntica a Contactos e Index) -->
        <nav class="bg-white border-b border-slate-200 px-6 py-4 flex justify-between items-center shadow-sm">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg flex items-center justify-center text-white font-bold text-sm"
                     :style="{ backgroundColor: tenant.primary_color }">
                    {{ tenant.company_name?.substring(0,2).toUpperCase() }}
                </div>
                <div>
                    <span class="font-bold text-slate-800 text-lg">{{ tenant.company_name }} - Formulario de Compra</span>
                    <p class="text-sm text-slate-500">Ingreso detallado de mercancías y provisiones al Kardex general.</p>
                </div>
            </div>
            <div class="flex items-center gap-4">
                <!--  POR ESTO (Solución): -->
                <Link href="/purchase-invoices" class="text-sm font-semibold text-slate-500 hover:text-slate-800 transition-colors border-2 px-3 py-1.5 rounded-xl border-slate-200 bg-white shadow-sm">
                    Volver al Historial
                </Link>
            </div>
        </nav>

        <!-- CUERPO CENTRAL -->
        <div class="p-6 max-w-7xl w-full mx-auto space-y-6 flex-1">
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">

                <div class="flex justify-between items-center pb-4 border-b border-slate-100 mb-6">
                    <h1 class="text-xl font-bold text-slate-800">Registrar Factura de Compra</h1>
                    <span v-if="props.initialData" class="bg-amber-50 text-amber-700 text-xs px-3 py-1.5 rounded-xl border border-amber-200 font-bold">
                        Modo Borrador / Corrección activa
                    </span>
                </div>

                <form @submit.prevent="submit" class="space-y-6">
                    <!-- SECCIÓN: METADATOS DE LA FACTURA -->
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Proveedor</label>
                            <div class="flex items-center space-x-2">
                                <select v-model="form.contact_id" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:border-transparent transition-all" :style="{ '--tw-ring-color': tenant.primary_color }">
                                    <option value="">Seleccione un proveedor...</option>
                                    <option v-for="prov in localProviders" :key="prov.id" :value="prov.id">
                                        {{ prov.name }} (NIT: {{ prov.nit }})
                                    </option>
                                </select>
                                <button type="button" @click="isProviderModalOpen = true" class="text-white font-bold text-lg px-3 py-1.5 rounded-xl shadow-md transition-all active:scale-95 flex items-center justify-center" :style="{ backgroundColor: tenant.primary_color }" title="Crear Proveedor Express">+</button>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Número de Factura</label>
                            <input type="text" v-model="form.invoice_number" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:border-transparent transition-all" :style="{ '--tw-ring-color': tenant.primary_color }" placeholder="Ej: FE-1024"/>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Fecha Emisión</label>
                            <input type="date" v-model="form.issue_date" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:border-transparent transition-all" :style="{ '--tw-ring-color': tenant.primary_color }"/>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Fecha Vencimiento</label>
                            <input type="date" v-model="form.due_date" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:border-transparent transition-all" :style="{ '--tw-ring-color': tenant.primary_color }"/>
                        </div>
                    </div>

                    <!-- SECCIÓN: TABLA DINÁMICA DE ÍTEMS -->
                    <div class="border border-slate-200 rounded-2xl overflow-hidden bg-white shadow-sm">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-slate-50 border-b border-slate-200 text-xs font-bold text-slate-500 uppercase tracking-wider">
                                    <th class="px-4 py-3">Producto / Artículo</th>
                                    <th class="px-4 py-3 w-24 text-right">Cant.</th>
                                    <th class="px-4 py-3 w-36 text-right">Costo Unit.</th>
                                    <th class="px-4 py-3 w-24 text-right">IVA %</th>
                                    <th class="px-4 py-3">Lote</th>
                                    <th class="px-4 py-3">Vencimiento</th>
                                    <th class="px-4 py-3 text-right">Subtotal</th>
                                    <th class="px-4 py-3 text-center"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 text-sm text-slate-600">
                                <tr v-for="(item, index) in form.items" :key="index" class="hover:bg-slate-50/50 transition-colors">
                                    <td class="p-3">
                                        <div class="flex items-center space-x-2">
                                            <select v-model="item.product_id" @change="onProductChange(index)" class="bg-slate-50 border border-slate-200 rounded-xl p-1.5 text-sm text-slate-700 flex-1 focus:outline-none focus:ring-2 focus:border-transparent transition-all" :style="{ '--tw-ring-color': tenant.primary_color }">
                                                <option value="">Seleccione un producto...</option>
                                                <option v-for="prod in localProducts" :key="prod.id" :value="prod.id">
                                                    {{ prod.name }} ({{ prod.code }}) <!-- 🌟 Ahora coincidirá perfectamente -->
                                                </option>
                                            </select>
                                            <button type="button" @click="isProductModalOpen = true" class="text-white font-bold text-sm px-2 py-1.5 rounded-lg shadow-sm transition-all active:scale-95" :style="{ backgroundColor: tenant.primary_color }" title="Crear Producto Express">+</button>
                                        </div>
                                    </td>
                                    <td class="p-3">
                                        <input type="number" v-model.number="item.quantity" step="any" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-1.5 text-sm text-right text-slate-700 focus:outline-none"/>
                                    </td>
                                    <td class="p-3">
                                        <input type="number" v-model.number="item.price_unit" step="0.01" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-1.5 text-sm text-right text-slate-700 focus:outline-none"/>
                                    </td>
                                    <td class="p-3">
                                        <input type="number" v-model.number="item.tax_rate" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-1.5 text-sm text-right text-slate-700 focus:outline-none"/>
                                    </td>
                                    <td class="p-3">
                                        <input type="text" v-model="item.batch_number" :disabled="!isPerishable(item.product_id)" placeholder="N/A" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-1.5 text-sm text-slate-700 disabled:opacity-40 disabled:cursor-not-allowed focus:outline-none"/>
                                    </td>
                                    <td class="p-3">
                                        <input type="date" v-model="item.expiration_date" :disabled="!isPerishable(item.product_id)" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-1.5 text-sm text-slate-700 disabled:opacity-40 disabled:cursor-not-allowed focus:outline-none"/>
                                    </td>
                                    <td class="p-3 text-right text-sm font-semibold text-slate-800 whitespace-nowrap">
                                        $ {{ ((item.quantity || 0) * (item.price_unit || 0)).toLocaleString('es-CO', { minimumFractionDigits: 2 }) }}
                                    </td>
                                    <td class="p-3 text-center">
                                        <button type="button" @click="removeItem(index)" class="text-rose-500 hover:text-rose-700 font-bold transition-colors">✕</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <!-- BOTÓN AGREGAR FILA -->
                        <div class="p-3 bg-slate-50 border-t border-slate-200">
                            <button type="button" @click="addItem" class="text-sm font-bold transition-colors hover:opacity-80" :style="{ color: tenant.primary_color }">
                                + Agregar Item / Artículo
                            </button>
                        </div>
                    </div>

                    <!-- SECCIÓN: OBSERVACIONES Y TOTALES FINALES -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-4 border-t border-slate-100">
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Observaciones / Notas Internas</label>
                            <textarea v-model="form.notes" rows="4" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:border-transparent transition-all" :style="{ '--tw-ring-color': tenant.primary_color }" placeholder="Anotaciones internas sobre condiciones de pago o entrega..."></textarea>
                        </div>
                        <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200 space-y-3 text-sm text-slate-600 h-fit">
                            <div class="flex justify-between font-medium"><span>Subtotal bruto:</span><span class="text-slate-800">$ {{ totals.subtotal.toLocaleString('es-CO', { minimumFractionDigits: 2 }) }}</span></div>
                            <div class="flex justify-between font-medium"><span>Impuestos calculados (IVA):</span><span class="text-slate-800">$ {{ totals.tax.toLocaleString('es-CO', { minimumFractionDigits: 2 }) }}</span></div>
                            <div class="flex justify-between items-center text-base font-bold pt-3 border-t border-slate-200" :style="{ color: tenant.primary_color }">
                                <span>TOTAL NETO DE COMPRA:</span>
                                <span class="text-lg">$ {{ totals.total.toLocaleString('es-CO', { minimumFractionDigits: 2 }) }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- BOTÓN PRINCIPAL DE GUARDADO -->
                    <div class="flex justify-end pt-4 border-t border-slate-100">
                        <button type="submit" :disabled="form.processing" class="text-white font-semibold py-2.5 px-6 rounded-xl shadow-md transition-all active:scale-95 flex items-center gap-2" :style="{ backgroundColor: tenant.primary_color }">
                            <span v-if="form.processing" class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                            {{ form.processing ? 'Procesando Kardex...' : 'Guardar Factura de Compra' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- 📦 MODAL FLOTANTE: PRODUCTO EXPRESS -->
        <div v-if="isProductModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm transition-opacity" @click="isProductModalOpen = false"></div>
            <div class="bg-white rounded-2xl shadow-xl border border-slate-200 max-w-md w-full relative z-10 p-6">
                <h3 class="text-lg font-bold text-slate-800 pb-3 border-b border-slate-100 mb-4">Creación Rápida de Producto</h3>
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">SKU / Código Referencia</label>
                        <input type="text" v-model="quickProductForm.code" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2 text-sm focus:outline-none" placeholder="Vacío para autogenerar"/>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Nombre Comercial</label>
                        <input type="text" v-model="quickProductForm.name" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2 text-sm focus:outline-none" placeholder="Ej: Jabón Antibacterial Antiséptico"/>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Porcentaje de IVA</label>
                        <input type="text" v-model="quickProductForm.tax_rate" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2 text-sm focus:outline-none" placeholder="Ej: Jabón Antibacterial Antiséptico"/>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Régimen tarifario</label>
                        <select v-model="quickProductForm.tax_type" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:border-slate-400">
                            <option value="GRAVADO">GRAVADO</option>
                            <option value="EXENTO">EXENTO</option>
                            <option value="EXCLUIDO">EXCLUIDO</option>
                        </select>
                    </div>
                    <div class="flex items-center space-x-2 py-2">
                        <input type="checkbox" id="ctrl_inv" v-model="quickProductForm.controlar_inventario" class="rounded border-slate-300 cursor-pointer" :style="{ color: tenant.primary_color }"/>
                        <label for="ctrl_inv" class="text-sm font-semibold text-slate-600 cursor-pointer select-none">Manejar Inventario Físico (Lotes / Vencimientos)</label>
                    </div>
                </div>
                <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-slate-100">
                    <button type="button" @click="isProductModalOpen = false" class="bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold py-2 px-4 rounded-xl text-sm transition-all">Cancelar</button>
                    <button type="button" @click="saveQuickProduct" class="text-white font-semibold py-2 px-5 rounded-xl text-sm shadow-md transition-all active:scale-95" :style="{ backgroundColor: tenant.primary_color }">Crear e Inyectar</button>
                </div>
            </div>
        </div>

        <!-- 🏢 MODAL FLOTANTE: PROVEEDOR EXPRESS -->
        <div v-if="isProviderModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm transition-opacity" @click="isProviderModalOpen = false"></div>
            <div class="bg-white rounded-2xl shadow-xl border border-slate-200 max-w-md w-full relative z-10 p-6">
                <h3 class="text-lg font-bold text-slate-800 pb-3 border-b border-slate-100 mb-4">Creación Rápida de Proveedor</h3>
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Tipo de Documento</label>
                        <select v-model="quickProviderForm.document_type" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:border-slate-400">
                            <option value="NIT">NIT</option>
                            <option value="CC">Cédula de Ciudadanía</option>
                            <option value="CE">Cédula de Extranjería</option>
                            <option value="TI">Tarjeta de Identidad</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">NIT / Cédula</label>
                        <input type="text" v-model="quickProviderForm.document_number" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2 text-sm focus:outline-none" placeholder="Ej: 901.458.122-3"/>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Nombres</label>
                        <input type="text" v-model="quickProviderForm.first_name" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2 text-sm focus:outline-none" placeholder="Ej: Jorge Alberto"/>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Apellidos</label>
                        <input type="text" v-model="quickProviderForm.last_name" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2 text-sm focus:outline-none" placeholder="Ej: García López"/>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Régimen Tributario</label>
                        <select v-model="quickProviderForm.regime_type" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:border-slate-400">
                            <option value="RESPONSABLE_IVA">Responsable de IVA</option>
                            <option value="NO_RESPONSABLE_IVA">No Responsable de IVA</option>
                            <option value="SIMPLIFICADO">Régimen Simplificado</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Razón Social / Nombre Empresa</label>
                        <input type="text" v-model="quickProviderForm.company_name" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2 text-sm focus:outline-none" placeholder="Ej: Distribuidora Médica de Antioquia S.A.S."/>
                    </div>
                </div>
                <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-slate-100">
                    <button type="button" @click="isProviderModalOpen = false" class="bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold py-2 px-4 rounded-xl text-sm transition-all">Cancelar</button>
                    <button type="button" @click="saveQuickProvider" class="text-white font-semibold py-2 px-5 rounded-xl text-sm shadow-md transition-all active:scale-95" :style="{ backgroundColor: tenant.primary_color }">Crear e Inyectar</button>
                </div>
            </div>
        </div>

    </div>
</template>
