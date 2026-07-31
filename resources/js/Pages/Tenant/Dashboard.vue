<script setup>
import { ref, computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';

// Captura de datos del sistema (Tenant para diseño y Auth para roles)
const page = usePage();
const tenant = computed(() => page.props.tenant || { company_name: 'ERP GLOBAL', primary_color: '#0f172a' });
const user = computed(() => page.props.auth?.user || { name: 'Usuario', role: 'admin' });

// Control del estado del menú lateral
const isSidebarOpen = ref(true);

const stats = computed(() => page.props.stats || {
    total_sales: 0,
    cash_balance: 0,
    accounts_receivable: 0,
    low_stock_count: 0
});

const accountsReceivableList = computed(() => page.props.accountsReceivableList || []);
const accountsPayableList = computed(() => page.props.accountsPayableList || []);
const lowStockProducts = computed(() => page.props.lowStockProducts || []);

const formatCurrency = (value) => {
    const number = Number(value ?? 0);
    return number.toLocaleString('es-CO', {
        style: 'currency',
        currency: 'COP',
        minimumFractionDigits: 0,
    });
};
</script>

<template>
    <Head title="Panel de Control" />

    <div class="min-h-screen bg-slate-100 font-sans flex">

        <aside
            class="bg-slate-900 text-slate-300 w-64 min-h-screen transition-all duration-300 flex flex-col justify-between shrink-0"
            :class="isSidebarOpen ? 'translate-x-0 block' : '-translate-x-full hidden md:block md:w-20'"
        >
            <div>
                <div class="px-6 py-5 bg-slate-950 border-b border-slate-800 flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center text-white font-bold text-sm shrink-0"
                         :style="{ backgroundColor: tenant.primary_color }">
                        {{ tenant.company_name?.substring(0,2).toUpperCase() }}
                    </div>
                    <span v-if="isSidebarOpen" class="font-bold text-white text-md tracking-wide truncate">{{ tenant.company_name }}</span>
                </div>

                <nav class="p-4 space-y-1">
                    <Link href="/dashboard" class="flex items-center gap-3 px-4 py-2.5 rounded-xl bg-slate-800 text-white font-medium text-sm transition-all">
                        <span>📊</span> <span v-if="isSidebarOpen">Dashboard</span>
                    </Link>

                    <Link v-if="user.role === 'admin' || user.role === 'seller'" href="/contacts" class="flex items-center gap-3 px-4 py-2.5 rounded-xl hover:bg-slate-800 hover:text-white font-medium text-sm transition-colors text-slate-400">
                        <span>👥</span> <span v-if="isSidebarOpen">Terceros / Contactos</span>
                    </Link>

                    <Link href="/products" class="flex items-center gap-3 px-4 py-2.5 rounded-xl hover:bg-slate-800 hover:text-white font-medium text-sm transition-colors text-slate-400">
                        <span>📦</span> <span v-if="isSidebarOpen">Inventario / Kardex</span>
                    </Link>

                    <Link v-if="user.role === 'admin'" href="/purchase-invoices" class="flex items-center gap-3 px-4 py-2.5 rounded-xl hover:bg-slate-800 hover:text-white font-medium text-sm transition-colors text-slate-400">
                        <span>📥</span> <span v-if="isSidebarOpen">Compras</span>
                    </Link>

                    <Link v-if="user.role === 'admin'" href="/" class="flex items-center gap-3 px-4 py-2.5 rounded-xl hover:bg-slate-800 hover:text-white font-medium text-sm transition-colors text-slate-400">
                        <span>📥</span> <span v-if="isSidebarOpen">Gastos</span>
                    </Link>

                    <Link href="/sales/pos" class="flex items-center gap-3 px-4 py-2.5 rounded-xl hover:bg-slate-800 hover:text-white font-medium text-sm transition-colors text-slate-400">
                        <span>📤</span> <span v-if="isSidebarOpen">Ventas / POS</span>
                    </Link>

                    <Link v-if="user.role === 'admin'" href="/cash-flow" class="flex items-center gap-3 px-4 py-2.5 rounded-xl hover:bg-slate-800 hover:text-white font-medium text-sm transition-colors text-slate-400">
                        <span>💰</span> <span v-if="isSidebarOpen">Caja y Cartera</span>
                    </Link>
                </nav>
            </div>

            <!-- Información del Usuario en la Base del Menú (Optimizado) -->
            <div class="p-4 bg-slate-700 border-t border-slate-800 flex items-center justify-between gap-2">
                <div class="truncate flex flex-col gap-1">
                    <!-- Nombre del usuario con mayor peso -->
                    <p class="font-bold text-slate-100 text-sm truncate select-none">
                        {{ user.name }}
                    </p>
                    <!-- Badge del Rol con alto contraste y visibilidad -->
                    <div class="flex">
                        <span class="px-2 py-0.5 rounded-md text-[10px] font-black tracking-wider uppercase bg-slate-800 text-emerald-400 border border-emerald-500/30 shadow-sm">
                            🛡️ {{ user.role }}
                        </span>
                    </div>
                </div>
                <!-- Botón de salida limpio -->
                <Link href="/logout" method="post" as="button" class="text-slate-500 hover:text-rose-400 font-bold text-lg p-1.5 hover:bg-slate-900 rounded-lg transition-colors shrink-0" title="Cerrar Sesión">
                    <!-- Icono o emoji de salida -->
                    🚪
                </Link>
            </div>
        </aside>

        <div class="flex-1 flex flex-col overflow-hidden">

            <header class="bg-white border-b border-slate-200 px-6 py-4 flex justify-between items-center shadow-sm">
                <div class="flex items-center gap-4">
                    <button @click="isSidebarOpen = !isSidebarOpen" class="text-slate-600 hover:text-slate-900 font-bold text-lg hidden md:block">
                        ☰
                    </button>
                    <div>
                        <h1 class="text-xl font-bold text-slate-800">Centro de Control</h1>
                        <p class="text-xs text-slate-500">Resumen operativo del estado actual del negocio.</p>
                    </div>
                </div>
            </header>

            <main class="p-6 space-y-6 overflow-y-auto flex-1">

                <div v-if="user.role === 'admin'" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex justify-between items-center">
                        <div>
                            <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Ventas del Mes</p>
                            <h3 class="text-xl font-black text-slate-800 mt-1">{{ formatCurrency(stats.total_sales) }}</h3>
                        </div>
                        <span class="text-2xl bg-emerald-50 p-3 rounded-xl">📈</span>
                    </div>

                    <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex justify-between items-center">
                        <div>
                            <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Efectivo en Caja</p>
                            <h3 class="text-xl font-black text-slate-800 mt-1">{{ formatCurrency(stats.cash_balance) }}</h3>
                        </div>
                        <span class="text-2xl bg-blue-50 p-3 rounded-xl">💵</span>
                    </div>

                    <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex justify-between items-center">
                        <div>
                            <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Cartera por Cobrar</p>
                            <h3 class="text-xl font-black text-slate-800 mt-1">{{ formatCurrency(stats.accounts_receivable) }}</h3>
                        </div>
                        <span class="text-2xl bg-amber-50 p-3 rounded-xl">⏳</span>
                    </div>

                    <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex justify-between items-center">
                        <div>
                            <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Alertas de Stock</p>
                            <h3 class="text-xl font-black text-rose-600 mt-1">{{ stats.low_stock_count }} Críticos</h3>
                        </div>
                        <span class="text-2xl bg-rose-50 p-3 rounded-xl">⚠️</span>
                    </div>
                </div>

                <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

                    <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                        <div class="flex justify-between items-center mb-3">
                            <h4 class="font-bold text-slate-800 text-xs uppercase tracking-wide">💼 Cuentas Por Cobrar</h4>
                            <span class="text-[10px] font-bold px-2 py-0.5 bg-amber-50 text-amber-700 rounded-full border border-amber-200">Clientes</span>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs border-collapse">
                                <thead>
                                    <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase text-[10px]">
                                        <th class="p-2.5">Factura</th>
                                        <th class="p-2.5">Cliente</th>
                                        <th class="p-2.5 text-right">Saldo</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 text-slate-600">
                                    <tr v-for="item in accountsReceivableList" :key="item.id" class="hover:bg-slate-50">
                                        <td class="p-2.5 font-bold font-mono text-slate-700">{{ item.number }}</td>
                                        <td class="p-2.5 font-medium truncate max-w-[120px]">{{ item.client }}</td>
                                        <td class="p-2.5 text-right font-extrabold text-slate-900">{{ formatCurrency(item.total) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                        <div class="flex justify-between items-center mb-3">
                            <h4 class="font-bold text-slate-800 text-xs uppercase tracking-wide">💸 Cuentas Por Pagar</h4>
                            <span class="text-[10px] font-bold px-2 py-0.5 bg-rose-50 text-rose-700 rounded-full border border-rose-200">Proveedores</span>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs border-collapse">
                                <thead>
                                    <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase text-[10px]">
                                        <th class="p-2.5">Factura</th>
                                        <th class="p-2.5">Proveedor</th>
                                        <th class="p-2.5 text-right">A Pagar</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 text-slate-600">
                                    <tr v-for="item in accountsPayableList" :key="item.id" class="hover:bg-slate-50">
                                        <td class="p-2.5 font-bold font-mono text-slate-700">{{ item.number }}</td>
                                        <td class="p-2.5 font-medium truncate max-w-[120px]">{{ item.provider }}</td>
                                        <td class="p-2.5 text-right font-extrabold text-rose-600">{{ formatCurrency(item.total) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                        <div class="flex justify-between items-center mb-3">
                            <h4 class="font-bold text-slate-800 text-xs uppercase tracking-wide">⚠️ Quiebre de Stock</h4>
                            <span class="text-[10px] font-bold px-2 py-0.5 bg-red-50 text-red-700 rounded-full border border-red-200">Reponer</span>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs border-collapse">
                                <thead>
                                    <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase text-[10px]">
                                        <th class="p-2.5">Producto</th>
                                        <th class="p-2.5 text-center">Actual</th>
                                        <th class="p-2.5 text-center">Mín</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 text-slate-600">
                                    <tr v-for="prod in lowStockProducts" :key="prod.id" class="hover:bg-slate-50">
                                        <td class="p-2.5 font-medium truncate max-w-[140px] text-slate-700">
                                            {{ prod.name }}
                                            <span class="block text-[10px] text-slate-400 font-mono">{{ prod.sku }}</span>
                                        </td>
                                        <td class="p-2.5 text-center font-bold text-rose-600 bg-rose-50/50">{{ prod.current_stock }}</td>
                                        <td class="p-2.5 text-center font-semibold text-slate-400">{{ prod.min_stock }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>

                <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex flex-col sm:flex-row items-center justify-between gap-4">
                    <div class="text-center sm:text-left">
                        <h4 class="font-bold text-slate-800 text-sm">¿Deseas iniciar una transacción ágil?</h4>
                        <p class="text-xs text-slate-400">Atajos optimizados para agilizar los procesos de facturación en mostrador.</p>
                    </div>
                    <div class="flex gap-2 w-full sm:w-auto">
                        <Link href="/sales" class="flex-1 sm:flex-none text-center text-xs font-bold text-white px-5 py-2.5 rounded-xl shadow-sm transition-transform active:scale-95" :style="{ backgroundColor: tenant.primary_color }">
                            ⚡ Terminal POS (Venta)
                        </Link>
                    </div>
                </div>

            </main>
        </div>

    </div>
</template>
