<script setup>
import { ref, computed } from 'vue';
import { usePage, useForm, router, Head, Link } from '@inertiajs/vue3';
import ExportModal from '@/Components/ExportModal.vue';

const props = defineProps({
    expenses: Object,
    categories: Array,
    filters: Object
});

const page = usePage();
const tenant = computed(() => page.props.tenant || {});

// Lista local de categorías: la mantenemos reactiva para poder
// agregar una nueva sin tener que recargar toda la página.
const localCategories = ref([...props.categories]);

// --- Filtros ---
const categoryFilter = ref(props.filters?.category_id || '');
const dateFrom = ref(props.filters?.date_from || '');
const dateTo = ref(props.filters?.date_to || '');

const applyFilters = () => {
    router.get('/expenses', {
        category_id: categoryFilter.value,
        date_from: dateFrom.value,
        date_to: dateTo.value
    }, {
        preserveState: true,
        replace: true
    });
};

const formatCurrency = (amount) => {
    return new Intl.NumberFormat('es-CO', {
        style: 'currency',
        currency: 'COP',
        minimumFractionDigits: 0
    }).format(amount || 0);
};

// --- Modal de registro de gasto ---
const showExpenseModal = ref(false);
const successMessage = ref('');
const editingExpenseId = ref(null);

// Valor especial que dispara el modo "nueva categoría" en el select
const NEW_CATEGORY_OPTION = '__new__';
const showNewCategoryInput = ref(false);
const newCategoryName = ref('');
const savingCategory = ref(false);
const categoryError = ref('');

const expenseForm = useForm({
    expense_category_id: '',
    provider_id: '',
    description: '',
    amount: '',
    expense_date: new Date().toISOString().slice(0, 10),
    payment_method: 'EFECTIVO',
    reference: ''
});

const openExpenseModal = () => {
    editingExpenseId.value = null;
    successMessage.value = '';
    expenseForm.reset();
    expenseForm.expense_date = new Date().toISOString().slice(0, 10);
    showNewCategoryInput.value = false;
    newCategoryName.value = '';
    categoryError.value = '';
    showExpenseModal.value = true;
};

// Cuando cambia el select: si eligen "+ Agregar nueva categoría", mostramos el input inline
const onCategoryChange = () => {
    showNewCategoryInput.value = expenseForm.expense_category_id === NEW_CATEGORY_OPTION;
    if (showNewCategoryInput.value) {
        expenseForm.expense_category_id = ''; // aún no hay categoría real seleccionada
    }
};

// Guarda la categoría nueva sin salir del modal, y la deja seleccionada
const saveNewCategory = async () => {
    if (!newCategoryName.value.trim()) return;

    savingCategory.value = true;
    categoryError.value = '';

    try {
        const response = await fetch('/expenses/categories', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                'Accept': 'application/json'
            },
            body: JSON.stringify({ name: newCategoryName.value.trim() })
        });

        if (!response.ok) {
            const err = await response.json();
            categoryError.value = err.errors?.name?.[0] || 'No se pudo crear la categoría.';
            return;
        }

        const category = await response.json();
        localCategories.value.push(category);
        expenseForm.expense_category_id = category.id;
        showNewCategoryInput.value = false;
        newCategoryName.value = '';
    } catch (e) {
        categoryError.value = 'Error de conexión al crear la categoría.';
    } finally {
        savingCategory.value = false;
    }
};

// Se llama al hacer clic en una fila de la tabla
const openEditModal = (expense) => {
    editingExpenseId.value = expense.id;
    successMessage.value = '';
    showNewCategoryInput.value = false;
    newCategoryName.value = '';
    categoryError.value = '';

    expenseForm.clearErrors();
    expenseForm.expense_category_id = expense.expense_category_id;
    expenseForm.provider_id = expense.provider_id;
    expenseForm.description = expense.description;
    expenseForm.amount = expense.amount;
    expenseForm.expense_date = expense.expense_date;
    expenseForm.payment_method = expense.payment_method;
    expenseForm.reference = expense.reference;

    showExpenseModal.value = true;
};

const submitExpense = () => {
    if (editingExpenseId.value) {
        // Modo edición
        expenseForm.put(`/expenses/${editingExpenseId.value}`, {
            preserveScroll: true,
            onSuccess: () => {
                successMessage.value = 'Gasto actualizado correctamente.';
                showExpenseModal.value = false;
                expenseForm.reset();
            }
        });
    } else {
        // Modo creación
        expenseForm.post('/expenses', {
            preserveScroll: true,
            onSuccess: () => {
                successMessage.value = 'Gasto registrado correctamente.';
                showExpenseModal.value = false;
                expenseForm.reset();
            }
        });
    }
};

const cancelExpense = (expense) => {
    if (!confirm(`¿Anular el gasto "${expense.description}"?`)) return;

    router.post(`/expenses/${expense.id}/cancel`, {}, {
        preserveScroll: true,
        onSuccess: () => {
            successMessage.value = 'Gasto anulado correctamente.';
        }
    });
};

const showExportModal = ref(false);

const expenseColumns = [
    { key: 'expense_date', label: 'Fecha' },
    { key: 'category.name', label: 'Categoría' },
    { key: 'description', label: 'Descripción' },
    { key: 'provider.full_name', label: 'Proveedor' },
    { key: 'provider.document_number', label: 'Identificación Proveedor' },
    { key: 'amount', label: 'Monto' },
    { key: 'payment_method', label: 'Método de Pago' },
    { key: 'reference', label: 'Referencia' },
    { key: 'status', label: 'Estado' }
];
</script>

<template>
    <Head title="Gastos" />

    <div class="min-h-screen bg-slate-100 font-sans flex flex-col">
        <!-- CABECERA -->
        <nav class="bg-white border-b border-slate-200 px-6 py-4 flex justify-between items-center shadow-sm">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg flex items-center justify-center text-white font-bold text-sm"
                     :style="{ backgroundColor: tenant.primary_color || '#0f172a' }">
                    {{ tenant.company_name?.substring(0,2).toUpperCase() || 'ERP' }}
                </div>
                <div>
                    <span class="font-bold text-slate-800 text-lg">{{ tenant.company_name }} - Gastos</span>
                    <p class="text-sm text-slate-500">Registro y control de gastos de la empresa.</p>
                </div>
            </div>

            <div class="flex items-center gap-4">
                <Link href="/dashboard" class="text-sm font-semibold text-slate-500 hover:text-slate-800 transition-colors border-2 p-1 rounded-lg">
                    Volver al Inicio
                </Link>
            </div>
        </nav>

        <div v-if="successMessage" class="mx-6 mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 shadow-sm">
            {{ successMessage }}
        </div>

        <!-- CUERPO -->
        <div class="p-6 max-w-7xl w-full mx-auto space-y-6 flex-1">

            <!-- FILTROS + BOTÓN NUEVO GASTO -->
            <div class="bg-white p-4 rounded-2xl shadow-sm border border-slate-200 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div class="flex flex-wrap items-center gap-3">
                    <select v-model="categoryFilter" @change="applyFilters" class="bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm">
                        <option value="">Todas las categorías</option>
                        <option v-for="cat in localCategories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                    </select>
                    <input v-model="dateFrom" @change="applyFilters" type="date" class="bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm" />
                    <input v-model="dateTo" @change="applyFilters" type="date" class="bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm" />
                </div>

                <button @click="showExportModal = true" class="bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 font-semibold text-sm px-4 py-2.5 rounded-xl shadow-sm transition-all flex items-center gap-2">
                    <span>📥 Exportar</span>
                </button>

                <button
                    @click="openExpenseModal"
                    class="text-white font-semibold px-4 py-2 rounded-xl text-sm shadow-sm transition-all active:scale-95"
                    :style="{ backgroundColor: tenant.primary_color || '#0f172a' }">
                    + Registrar Gasto
                </button>
            </div>

            <!-- TABLA -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-slate-500 text-xs uppercase">
                        <tr>
                            <th class="px-6 py-3 text-left">Fecha</th>
                            <th class="px-6 py-3 text-left">Categoría</th>
                            <th class="px-6 py-3 text-left">Descripción</th>
                            <th class="px-6 py-3 text-left">Proveedor</th>
                            <th class="px-6 py-3 text-right">Monto</th>
                            <th class="px-6 py-3 text-left">Método</th>
                            <th class="px-6 py-3 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="expense in expenses.data" :key="expense.id"
                            class="hover:bg-slate-50 cursor-pointer"
                            @click="openEditModal(expense)">
                            <td class="px-6 py-3">{{ expense.expense_date }}</td>
                            <td class="px-6 py-3">{{ expense.category?.name }}</td>
                            <td class="px-6 py-3">{{ expense.description }}</td>
                            <td class="px-6 py-3">
                                {{ expense.provider?.company_name || expense.provider?.first_name || '—' }}
                            </td>
                            <td class="px-6 py-3 text-right font-semibold">{{ formatCurrency(expense.amount) }}</td>
                            <td class="px-6 py-3">{{ expense.payment_method }}</td>
                            <td class="px-6 py-3 text-right">
                                <button
                                    @click.stop="cancelExpense(expense)"
                                    class="text-rose-600 hover:text-rose-800 bg-rose-50 hover:bg-rose-100 px-3 py-1.5 rounded-lg text-xs font-semibold transition-all">
                                    Anular
                                </button>
                            </td>
                        </tr>

                        <tr v-if="expenses.data.length === 0">
                            <td colspan="7" class="px-6 py-12 text-center text-slate-400">
                                No se encontraron gastos registrados con los filtros aplicados.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- MODAL REGISTRAR GASTO -->
        <div v-if="showExpenseModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm" @click="showExpenseModal = false"></div>

            <div class="bg-white rounded-2xl shadow-xl border border-slate-200 max-w-md w-full relative z-10 p-6 flex flex-col">
                <div class="flex justify-between items-center pb-3 border-b border-slate-100 mb-4">
                    <h3 class="text-lg font-bold text-slate-800">
                        {{ editingExpenseId ? 'Editar Gasto' : 'Registrar Gasto' }}
                    </h3>
                    <button @click="showExpenseModal = false" class="text-slate-400 hover:text-slate-600 text-xl font-bold">&times;</button>
                </div>

                <form @submit.prevent="submitExpense" class="space-y-4">
                    <!-- Categoría -->
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Categoría</label>
                        <select v-model="expenseForm.expense_category_id" @change="onCategoryChange" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm">
                            <option value="" disabled>Selecciona una categoría</option>
                            <option v-for="cat in localCategories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                            <option value="__new__">+ Agregar nueva categoría</option>
                        </select>
                        <span v-if="expenseForm.errors.expense_category_id" class="text-xs text-rose-500 mt-1 block">{{ expenseForm.errors.expense_category_id }}</span>

                        <!-- Campo inline para nueva categoría -->
                        <div v-if="showNewCategoryInput" class="mt-2 flex gap-2">
                            <input
                                v-model="newCategoryName"
                                type="text"
                                placeholder="Nombre de la categoría"
                                class="flex-1 bg-amber-50 border border-amber-200 rounded-lg px-2.5 py-1.5 text-sm focus:outline-none"
                                @keydown.enter.prevent="saveNewCategory"
                            />
                            <button
                                type="button"
                                @click="saveNewCategory"
                                :disabled="savingCategory"
                                class="bg-slate-800 hover:bg-slate-900 text-white text-xs font-semibold px-3 rounded-lg transition-all">
                                {{ savingCategory ? '...' : 'Agregar' }}
                            </button>
                        </div>
                        <span v-if="categoryError" class="text-xs text-rose-500 mt-1 block">{{ categoryError }}</span>
                    </div>

                    <!-- Descripción -->
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Descripción</label>
                        <input v-model="expenseForm.description" type="text" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm" />
                        <span v-if="expenseForm.errors.description" class="text-xs text-rose-500 mt-1 block">{{ expenseForm.errors.description }}</span>
                    </div>

                    <!-- Monto y Fecha -->
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Monto</label>
                            <input v-model="expenseForm.amount" type="number" step="0.01" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm" />
                            <span v-if="expenseForm.errors.amount" class="text-xs text-rose-500 mt-1 block">{{ expenseForm.errors.amount }}</span>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Fecha</label>
                            <input v-model="expenseForm.expense_date" type="date" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm" />
                        </div>
                    </div>

                    <!-- Método de pago -->
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Método de Pago</label>
                        <select v-model="expenseForm.payment_method" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm">
                            <option value="EFECTIVO">Efectivo</option>
                            <option value="TRANSFERENCIA">Transferencia Bancaria</option>
                            <option value="TARJETA_DEBITO">Tarjeta Débito</option>
                            <option value="TARJETA_CREDITO">Tarjeta Crédito</option>
                        </select>
                    </div>

                    <!-- Referencia -->
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Referencia (Opcional)</label>
                        <input v-model="expenseForm.reference" type="text" placeholder="Ej: FAC-00123" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm" />
                    </div>

                    <!-- Botones -->
                    <div class="flex gap-3 pt-3 border-t border-slate-100 justify-end">
                        <button type="button" @click="showExpenseModal = false" class="bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold py-2 px-4 rounded-xl text-sm transition-all">
                            Cancelar
                        </button>
                        <button type="submit" :disabled="expenseForm.processing" class="text-white font-semibold py-2 px-5 rounded-xl text-sm shadow-md transition-all" :style="{ backgroundColor: tenant.primary_color || '#0f172a' }">
                            {{ expenseForm.processing ? 'Guardando...' : (editingExpenseId ? 'Guardar Cambios' : 'Confirmar Gasto') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <ExportModal
        :is-open="showExportModal"
        module-name="expenses"
        :available-columns="expenseColumns"
        :current-data="expenses.data"
        @close="showExportModal = false"
    />
</template>
