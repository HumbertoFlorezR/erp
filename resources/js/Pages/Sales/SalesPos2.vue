<script setup>
import { ref, computed, watch } from 'vue';
import { Head, usePage, Link, useForm, router } from '@inertiajs/vue3';
import axios from 'axios';

// Recibir datos iniciales desde el controlador
const props = defineProps({
    nextInvoice: String,
    activeResolution: Object,
    defaultCustomer: Object
});

const page = usePage();
const tenant = computed(() => page.props.tenant || { company_name: 'ERP GLOBAL', primary_color: '#0f172a' });
const user = computed(() => page.props.auth?.user || { name: 'Cajero' });

// --- ESTADOS DE CAPTURA ---
const selectedCustomer = ref(props.defaultCustomer || { id: null, first_name: 'Consumidor', last_name: 'Final', document_number: '222222222222' });
const cart = ref([]);
// --- ESTADOS DE PRODUCTOS ---
const searchProductQuery = ref('');
const productsFound = ref([]);
const showProductDropdown = ref(false);

// Función para buscar productos de forma dinámica
const searchProducts = async () => {
    if (searchProductQuery.value.length < 2) {
        productsFound.value = [];
        showProductDropdown.value = false;
        return;
    }

    try {
        const response = await axios.get(`${window.location.origin}/sales/pos/search-products`, {
            params: { q: searchProductQuery.value }
        });
        productsFound.value = response.data;
        showProductDropdown.value = true;
    } catch (error) {
        console.error("Error buscando productos:", error);
    }
};

// Capturar el evento Enter para lectores de código de barras
const handleProductEnter = () => {
    // Si el lector de barras ingresa un código exacto y solo encuentra un producto, lo agregamos directo
    if (productsFound.value.length === 1) {
        addToCart(productsFound.value[0]);
    } else if (productsFound.value.length > 1) {
        // Si hay varios parecidos, abrimos el menú para que elija
        showProductDropdown.value = true;
    }
};

// Función de agregar al carrito (optimizada para usar las propiedades reales del modelo)
const addToCart = (product) => {
    const existing = cart.value.find(item => item.id === product.id);
    if (existing) {
        existing.qty++;
    } else {
        cart.value.push({
            id: product.id,
            name: product.name,
            code: product.code,
            price_excluding_tax: parseFloat(product.price_excluding_tax),
            tax_rate: parseFloat(product.tax_rate || 0),
            discount_p: 0, // Inicia sin descuento
            qty: 1
        });
    }
    // Limpiamos los estados de búsqueda
    searchProductQuery.value = '';
    productsFound.value = [];
    showProductDropdown.value = false;
};

const paymentMethod = ref('EFECTIVO');
const receivedAmount = ref(0);
const transactionReference = ref('');
const searchQuery = ref('');
const customersFound = ref([]);
const showDropdown = ref(false);

// --- MODAL DE CLIENTE NUEVO ---
const showCustomerModal = ref(false);
const customerForm = useForm({
    document_type: 'CC',
    document_number: '',
    first_name: '',
    last_name: '',
    company_name: '',
    phone: '',
    email: ''
});

const removeFromCart = (index) => cart.value.splice(index, 1);

// --- TOTALES COMPUTADOS ---
const totalItems = computed(() => cart.value.reduce((acc, item) => acc + item.qty, 0));

const financialTotals = computed(() => {
    let subtotal = 0;
    let discounts = 0;
    let taxes = 0;

    cart.value.forEach(item => {
        const itemSubtotalRaw = item.price_excluding_tax * item.qty;
        const itemDiscount = itemSubtotalRaw * (item.discount_p / 100);
        const itemTax = (itemSubtotalRaw - itemDiscount) * (item.tax_rate / 100);

        subtotal += itemSubtotalRaw;
        discounts += itemDiscount;
        taxes += itemTax;
    });

    const total = (subtotal - discounts) + taxes;

    return { subtotal, discounts, taxes, total };
});

const changeAmount = computed(() => {
    if (paymentMethod.value !== 'EFECTIVO') return 0;
    const change = receivedAmount.value - financialTotals.value.total;
    return change > 0 ? change : 0;
});

// Inicializar el efectivo recibido con el total exacto de forma cómoda
watch(() => financialTotals.value.total, (newTotal) => {
    if (paymentMethod.value === 'EFECTIVO') receivedAmount.value = Math.ceil(newTotal);
});

// --- ENVÍO DE LA VENTA AL BACKEND ---
const submitSale = () => {
    if (cart.value.length === 0) return alert('El carrito está vacío');

    const formPayload = {
        customer_id: selectedCustomer.value.id,
        items: cart.value.map(i => ({ id: i.id, qty: i.qty, discount_p: i.discount_p })),
        payments: [{
            method: paymentMethod.value,
            amount: financialTotals.value.total,
            received_amount: paymentMethod.value === 'EFECTIVO' ? receivedAmount.value : financialTotals.value.total,
            reference: transactionReference.value
        }]
    };

    // Usamos el router de Inertia para enviar de forma nativa al backend transaccional
    axios.post('/sales/pos', formPayload)
        .then(() => window.location.reload())
        .catch(err => alert('Error al procesar la venta. Verifique la resolución DIAN.'));
};

const createCustomerQuickly = () => {
    axios.post('/sales/pos/customer', customerForm.data())
        .then(res => {
            if (res.data.success) {
                selectedCustomer.value = res.data.customer;
                showCustomerModal.value = false;
                customerForm.reset();
            }
        });
};

// Función para buscar clientes en el backend
const searchCustomers = async () => {
    if (searchQuery.value.length < 3) {
        customersFound.value = [];
        showDropdown.value = false;
        return;
    }

    try {
        // 🌟 OBTENEMOS EL SUBDOMINIO (TENANT) DE LA URL ACTUAL:
        // window.location.origin ya contiene "http://sajjuna.erp-global.test"
        const response = await axios.get(`${window.location.origin}/sales/pos/search-customers`, {
            params: { q: searchQuery.value }
        });

        customersFound.value = response.data;
        showDropdown.value = true;
    } catch (error) {
        console.error("Error buscando clientes:", error);
    }
};

// Función para seleccionar el cliente y asignarlo a la venta
const selectCustomer = (customer) => {
    selectedCustomer.value = customer;
    // Si viene la razón social la usamos, de lo contrario construimos el nombre completo o usamos 'name'
    searchQuery.value = customer.name || customer.company_name || `${customer.first_name} ${customer.last_name}`;
    showDropdown.value = false;
    customersFound.value = [];
};
</script>

<template>
    <Head title="Facturación POS" />
    <div class="min-h-screen bg-slate-100 font-sans">
        <!-- CABECERA SUPERIOR -->
        <nav class="bg-white border-b border-slate-200 px-6 py-2 flex justify-between items-center shadow-sm">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg flex items-center justify-center text-white font-bold text-sm"
                     :style="{ backgroundColor: tenant.primary_color }">
                    {{ tenant.company_name?.substring(0,2).toUpperCase() }}
                </div>

                <div>
                    <span class="font-bold text-slate-800 text-lg">{{ tenant.company_name }}</span>
                    <p class="text-lg font-bold text-slate-800">Terminal de Venta POS</p>

                    <p class="text-xs text-slate-400">⚡ Cajero: <span class="font-bold text-slate-600">{{ user.name }}</span> | {{ new Date().toLocaleDateString('es-CO') }}</p>
                </div>
            </div>

            <div class="flex items-center gap-4">
                <div v-if="activeResolution">
                    <span class="text-xs uppercase tracking-wider font-bold text-slate-400 block">Consecutivo DIAN Sugerido</span>
                    <span class="text-xl font-black text-slate-900 font-mono bg-slate-100 px-3 py-1 rounded-xl border border-slate-200 mt-1 inline-block">
                        {{ nextInvoice }}
                    </span>
                </div>

                <div v-else>
                    <span class="text-[10px] uppercase tracking-wider font-bold text-rose-500 block mb-1">Requiere Atención</span>
                    <a
                        href="/settings/resolutions"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-rose-50 border border-rose-200 hover:bg-rose-100 text-rose-700 font-extrabold text-xs rounded-xl tracking-wide transition-all shadow-sm animate-pulse"
                    >
                        ⚙️ CONFIGURAR RESOLUCIÓN AQUÍ
                    </a>
                </div>
                <Link href="/dashboard" class="text-sm font-semibold text-slate-500 hover:text-slate-800 transition-colors border-2 p-1 my-3 rounded-lg hover:bg-slate-50">
                    Volver al Inicio
                </Link>
            </div>
        </nav>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-3 items-start mt-3">

            <div class="lg:col-span-8 space-y-3">

                <div class="bg-white p-2 rounded-2xl shadow-sm border border-slate-200 relative">
                    <div class="flex justify-between items-center mb-2">
                        <label class="text-xs font-bold text-slate-400 uppercase tracking-wide">👤 Cliente de la Venta</label>
                        <button @click="showCustomerModal = true" class="text-xs font-bold text-blue-600 hover:underline flex items-center gap-1">
                            ➕ Registrar Cliente Rápido
                        </button>
                    </div>

                    <div class="relative mb-2">
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-slate-400 text-xs">🔍</span>
                            <input
                                v-model="searchQuery"
                                @input="searchCustomers"
                                @focus="showDropdown = true"

                                type="text"
                                placeholder="Buscar por Cédula, NIT o Nombre del cliente existente..."
                                class="w-full text-xs border-slate-200 rounded-xl pl-9 pr-4 py-2.5 bg-slate-50 focus:ring-slate-800 focus:border-slate-800 font-medium"
                            />
                        </div>

                        <div v-if="showDropdown && customersFound.length > 0" class="absolute left-0 right-0 mt-1.5 bg-white border border-slate-200 rounded-xl shadow-xl z-50 max-h-48 overflow-y-auto divide-y divide-slate-100">
                            <button
                                v-for="customer in customersFound"
                                :key="customer.id"
                                @click="selectCustomer(customer)"
                                type="button"
                                class="w-full text-left p-3 hover:bg-slate-50 flex flex-col transition-colors text-xs"
                            >
                                <span class="font-bold text-slate-800">
                                    {{ customer.name || customer.company_name || `${customer.first_name} ${customer.last_name}` }}
                                </span>
                                <span class="text-[10px] text-slate-400 font-mono mt-0.5">🪪 CC/NIT: {{ customer.document_number }}</span>
                            </button>
                        </div>
                    </div>

                    <div class="p-2 bg-slate-50 border border-slate-200 rounded-xl flex justify-between items-center">
                        <div>
                            <p class="font-bold text-slate-800 text-sm">
                                {{ selectedCustomer.company_name || `${selectedCustomer.first_name} ${selectedCustomer.last_name}` }}
                            </p>
                            <p class="text-xs text-slate-400 font-mono mt-0.5">🪪 Doc: {{ selectedCustomer.document_number }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <button
                                v-if="selectedCustomer.id !== (props.defaultCustomer?.id || null)"
                                @click="selectedCustomer = props.defaultCustomer || { id: null, first_name: 'Consumidor', last_name: 'Final', document_number: '222222222222' }; searchQuery = ''"
                                type="button"
                                class="text-[10px] font-bold text-rose-500 hover:text-rose-700 bg-white border border-slate-200 px-2.5 py-1 rounded-lg shadow-sm transition-colors"
                            >
                                Restablecer
                            </button>
                            <span class="px-2.5 py-1 bg-white border border-slate-200 text-[10px] font-bold text-slate-500 rounded-lg shadow-sm">
                                Venta POS
                            </span>
                        </div>
                    </div>
                </div>

                <!-- ESCANEAR O BUSCAR PRODUCTO -->
                <div class="bg-white p-2 rounded-2xl shadow-sm border border-slate-200 relative">
                    <label class="text-xs font-bold text-slate-400 uppercase tracking-wide block mb-2">📦 Escanear o Buscar Producto</label>
                    <div class="relative">
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-slate-400 text-xs">🔍</span>
                            <input
                                v-model="searchProductQuery"
                                @input="searchProducts"
                                @keyup.enter="handleProductEnter"
                                type="text"
                                placeholder="Pasa el lector de barras o escribe el nombre del producto..."
                                class="w-full text-sm border-slate-200 focus:ring-slate-800 focus:border-slate-800 rounded-xl pl-9 pr-4 py-2.5 bg-slate-50 font-medium"
                                autocomplete="off"
                            />
                        </div>

                        <!-- Dropdown de Productos Encontrados -->
                        <div v-if="showProductDropdown && productsFound.length > 0" class="absolute left-0 right-0 mt-1.5 bg-white border border-slate-200 rounded-xl shadow-xl z-50 max-h-60 overflow-y-auto divide-y divide-slate-100">
                            <button
                                v-for="product in productsFound"
                                :key="product.id"
                                @click="addToCart(product)"
                                type="button"
                                class="w-full text-left p-3 hover:bg-slate-50 flex justify-between items-center transition-colors text-xs"
                            >
                                <div class="flex flex-col">
                                    <span class="font-bold text-slate-800">{{ product.name }}</span>
                                    <span class="text-[10px] text-slate-400 font-mono">Código: {{ product.code }} | Stock: {{ product.stock }}</span>
                                </div>
                                <div class="text-right">
                                    <span class="font-black text-slate-900">$ {{ parseFloat(product.price_excluding_tax).toLocaleString('es-CO', {maximumFractionDigits:0}) }}</span>
                                    <p class="text-[9px] text-slate-400">Sin IVA</p>
                                </div>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase text-[10px]">
                                <th class="p-2">Detalle del Item</th>
                                <th class="p-2 text-center w-20">Cant</th>
                                <th class="p-2 text-right">Precio Base</th>
                                <th class="p-2 text-center w-20">Desc %</th>
                                <th class="p-2 text-right">Subtotal</th>
                                <th class="p-2 text-center"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-600">
                            <tr v-for="(item, idx) in cart" :key="idx" class="hover:bg-slate-50/50">
                                <td class="p-2">
                                    <p class="font-bold text-slate-800">{{ item.name }}</p>
                                    <span class="text-[10px] text-slate-400 font-mono">{{ item.code }}</span>
                                </td>
                                <td class="p-2 text-center">
                                    <input v-model.number="item.qty" type="number" min="1" class="w-16 text-center text-xs p-1 border-slate-200 rounded-md font-bold text-slate-800" />
                                </td>
                                <td class="p-2 text-right font-medium font-mono">$ {{ item.price_excluding_tax.toLocaleString('es-CO') }}</td>
                                <td class="p-2 text-center">
                                    <input v-model.number="item.discount_p" type="number" min="0" max="100" class="w-14 text-center text-xs p-1 border-slate-200 rounded-md font-mono text-amber-600 font-bold bg-amber-50/30" />
                                </td>
                                <td class="p-2 text-right font-black font-mono text-slate-900">
                                    $ {{ ((item.price_excluding_tax * item.qty) * (1 - item.discount_p/100) * (1 + item.tax_rate/100)).toLocaleString('es-CO', {maximumFractionDigits: 0}) }}
                                </td>
                                <td class="p-2 text-center">
                                    <button @click="removeFromCart(idx)" class="text-rose-500 hover:text-rose-700 font-bold text-sm">❌</button>
                                </td>
                            </tr>
                            <tr v-if="cart.length === 0">
                                <td colspan="6" class="p-8 text-center text-slate-400 font-medium">
                                    🛒 Ningún artículo cargado en la venta actual.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

            </div>

            <div class="lg:col-span-4">
                <div class="bg-white p-6 rounded-2xl shadow-xl border border-slate-200 relative overflow-hidden flex flex-col">

                    <div class="absolute top-0 left-0 right-0 h-1 bg-linear-to-r from-slate-200 via-slate-300 to-slate-200 border-b border-dashed border-slate-400"></div>

                    <div class="text-center pb-4 border-b border-dashed border-slate-200 mt-2">
                        <h3 class="font-black text-slate-800 text-sm tracking-widest uppercase">{{ tenant.company_name }}</h3>
                        <p class="text-[10px] text-slate-400 uppercase mt-0.5">Comprobante de Venta POS</p>
                    </div>

                    <div class="py-4 space-y-2 text-xs border-b border-dashed border-slate-200 font-mono">
                        <div class="flex justify-between text-slate-400 text-[11px]">
                            <span>Items Facturados:</span>
                            <span class="font-bold text-slate-700">{{ totalItems }} u.</span>
                        </div>
                        <div class="flex justify-between text-slate-500">
                            <span>Subtotal Base:</span>
                            <span>$ {{ financialTotals.subtotal.toLocaleString('es-CO', {maximumFractionDigits:0}) }}</span>
                        </div>
                        <div class="flex justify-between text-amber-600 font-bold">
                            <span>Descuentos:</span>
                            <span>- $ {{ financialTotals.discounts.toLocaleString('es-CO', {maximumFractionDigits:0}) }}</span>
                        </div>
                        <div class="flex justify-between text-slate-500">
                            <span>Impuestos (IVA):</span>
                            <span>+ $ {{ financialTotals.taxes.toLocaleString('es-CO', {maximumFractionDigits:0}) }}</span>
                        </div>
                        <div class="flex justify-between text-base font-black text-slate-900 pt-2 border-t border-slate-100">
                            <span>TOTAL A PAGAR:</span>
                            <span>$ {{ financialTotals.total.toLocaleString('es-CO', {maximumFractionDigits:0}) }}</span>
                        </div>
                    </div>

                    <div class="py-4 space-y-4">
                        <label class="text-xs font-bold text-slate-400 uppercase tracking-wide block">Forma de Pago del Cliente</label>
                        <div class="grid grid-cols-2 gap-2">
                            <button
                                v-for="method in ['EFECTIVO', 'TRANSFERENCIA', 'TARJETA_DEBITO', 'TARJETA_CREDITO']"
                                :key="method"
                                @click="paymentMethod = method"
                                class="p-2.5 rounded-xl border text-[11px] font-extrabold transition-all text-center"
                                :class="paymentMethod === method ? 'bg-slate-900 text-white border-transparent shadow-sm' : 'bg-slate-50 border-slate-200 text-slate-600 hover:bg-slate-100'"
                            >
                                {{ method.replace('_', ' ') }}
                            </button>
                        </div>

                        <div v-if="paymentMethod === 'EFECTIVO'" class="bg-emerald-50/50 p-4 rounded-xl border border-emerald-200 space-y-3">
                            <div class="flex justify-between items-center gap-2">
                                <label class="text-xs font-bold text-emerald-800">Efectivo Recibido:</label>
                                <input v-model.number="receivedAmount" type="number" class="w-32 p-1.5 text-xs text-right font-bold text-emerald-900 border-emerald-300 rounded-lg focus:ring-emerald-500 bg-white" />
                            </div>
                            <div class="flex justify-between items-center pt-2 border-t border-emerald-200/50">
                                <span class="text-xs font-bold text-emerald-800">Cambio / Vueltos:</span>
                                <span class="text-lg font-black text-emerald-600 font-mono">$ {{ changeAmount.toLocaleString('es-CO', {maximumFractionDigits:0}) }}</span>
                            </div>
                        </div>

                        <div v-else class="bg-slate-50 p-3 rounded-xl border border-slate-200">
                            <label class="text-[10px] font-bold text-slate-400 uppercase tracking-wide block mb-1">Referencia / No. Váucher Datáfono</label>
                            <input v-model="transactionReference" type="text" placeholder="Ej: APROBADO 8832" class="w-full p-2 text-xs font-mono border-slate-200 rounded-lg bg-white" />
                        </div>
                    </div>

                    <button
                        @click="submitSale"
                        class="w-full text-center py-4 rounded-2xl text-white font-black text-sm tracking-wide shadow-md transition-all active:scale-95 mt-2"
                        :style="{ backgroundColor: tenant.primary_color }"
                    >
                        🟢 PROCESAR E IMPRIMIR FACTURA (POS)
                    </button>
                </div>
            </div>

        </div>

        <div v-if="showCustomerModal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm flex items-center justify-center p-4 z-50">
            <div class="bg-white p-6 rounded-2xl border border-slate-200 max-w-lg w-full shadow-2xl space-y-4">
                <div class="flex justify-between items-center border-b border-slate-100 pb-3">
                    <h3 class="font-extrabold text-slate-800 text-base">➕ Registrar Cliente Rápido</h3>
                    <button @click="showCustomerModal = false" class="text-slate-400 hover:text-slate-600 font-bold text-sm">✕</button>
                </div>
                <form @submit.prevent="createCustomerQuickly" class="grid grid-cols-2 gap-3 text-xs">
                    <div>
                        <label class="font-bold text-slate-500 block mb-1">Tipo Identificación</label>
                        <select v-model="customerForm.document_type" class="w-full border-slate-200 rounded-xl p-2 bg-slate-50 font-medium">
                            <option value="CC">Cédula de Ciudadanía (CC)</option>
                            <option value="NIT">NIT (Empresas)</option>
                            <option value="CE">Cédula de Extranjería</option>
                        </select>
                    </div>
                    <div>
                        <label class="font-bold text-slate-500 block mb-1">Número Identificación</label>
                        <input v-model="customerForm.document_number" type="text" required class="w-full border-slate-200 rounded-xl p-2 bg-slate-50" />
                    </div>
                    <div>
                        <label class="font-bold text-slate-500 block mb-1">Nombres</label>
                        <input v-model="customerForm.first_name" type="text" class="w-full border-slate-200 rounded-xl p-2 bg-slate-50" />
                    </div>
                    <div>
                        <label class="font-bold text-slate-500 block mb-1">Apellidos</label>
                        <input v-model="customerForm.last_name" type="text" class="w-full border-slate-200 rounded-xl p-2 bg-slate-50" />
                    </div>
                    <div class="col-span-2">
                        <label class="font-bold text-slate-500 block mb-1">Razón Social (Si aplica)</label>
                        <input v-model="customerForm.company_name" type="text" class="w-full border-slate-200 rounded-xl p-2 bg-slate-50" />
                    </div>
                    <div>
                        <label class="font-bold text-slate-500 block mb-1">Teléfono</label>
                        <input v-model="customerForm.phone" type="text" class="w-full border-slate-200 rounded-xl p-2 bg-slate-50" />
                    </div>
                    <div>
                        <label class="font-bold text-slate-500 block mb-1">Correo Electrónico</label>
                        <input v-model="customerForm.email" type="email" class="w-full border-slate-200 rounded-xl p-2 bg-slate-50" />
                    </div>
                    <div class="col-span-2 pt-3 flex gap-2">
                        <button type="button" @click="showCustomerModal = false" class="flex-1 bg-slate-100 hover:bg-slate-200 font-bold p-2.5 rounded-xl text-slate-600 transition-colors">Cancelar</button>
                        <button type="submit" class="flex-1 text-white font-bold p-2.5 rounded-xl transition-transform active:scale-95" :style="{ backgroundColor: tenant.primary_color }">Guardar e Inyectar</button>
                    </div>
                </form>
            </div>
        </div>

    </div>
</template>
