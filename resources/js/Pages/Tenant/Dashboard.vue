<script setup>
import { usePage, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue'; // 💡 ¡AQUÍ ESTÁ EL TRUCO! Añadimos ', ref'

const page = usePage();

// Volvemos a la lectura limpia y estándar de Laravel + Inertia
const user = computed(() => page.props.auth?.user || null);
const tenant = computed(() => page.props.tenant);
const authData = computed(() => page.props.auth || {});

// 💡 Control de estado para la modal de confirmación
const showLogoutModal = ref(false);
</script>

<template>
    <div class="min-h-screen bg-slate-100 font-sans flex flex-col">
        <nav class="bg-white border-b border-slate-200 px-6 py-4 flex justify-between items-center shadow-sm">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg flex items-center justify-center text-white font-bold text-sm"
                     :style="{ backgroundColor: tenant?.primary_color }">
                    {{ tenant?.company_name.substring(0,2).toUpperCase() }}
                </div>
                <span class="font-bold text-slate-800 text-lg">{{ tenant?.company_name }} - Panel de Control</span>
            </div>

            <div class="flex items-center gap-4">
                <span class="text-sm text-slate-600 font-medium">Hola, {{ user?.name }}</span>
                <button @click="showLogoutModal = true"
                        class="text-xs text-red-600 hover:text-red-700 font-semibold bg-red-50 hover:bg-red-100 px-3 py-1.5 rounded-lg transition-all duration-200">
                    Cerrar Sesión
                </button>
            </div>
        </nav>

        <main class="p-8 flex-1">
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 max-w-4xl">
                <h2 class="text-xl font-bold text-slate-800 mb-2">¡Bienvenido al Sistema!</h2>
                <p class="text-slate-600 text-sm">
                    Has ingresado correctamente a la infraestructura aislada de tu empresa. Desde aquí podrás gestionar inventarios, facturación y clientes de forma segura.
                </p>
            </div>
        </main>
    </div>

    <div v-if="showLogoutModal"
         class="fixed inset-0 z-50 flex items-center justify-center p-4">

        <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm transition-opacity" @click="showLogoutModal = false"></div>

        <div class="bg-white rounded-2xl shadow-xl border border-slate-100 max-w-sm w-full p-6 relative z-10 transform transition-all scale-100">

            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-50 mb-4">
                <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15M12 9l-3 3m0 0 3 3m-3-3h12.75" />
                </svg>
            </div>

            <div class="text-center mb-6">
                <h3 class="text-lg font-bold text-slate-800 tracking-tight">¿Cerrar sesión en el sistema?</h3>
                <p class="text-slate-500 text-sm mt-2">Cualquier cambio no guardado en los módulos activos se perderá.</p>
            </div>

            <div class="flex gap-3">
                <button @click="showLogoutModal = false"
                        class="flex-1 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold py-2.5 px-4 rounded-xl text-sm active:scale-[0.98] transition-all duration-200">
                    Cancelar
                </button>

                <Link href="/logout"
                      method="post"
                      as="button"
                      @click="showLogoutModal = false"
                      class="flex-1 text-white font-semibold py-2.5 px-4 rounded-xl text-sm shadow-md bg-red-600 hover:bg-red-700 active:scale-[0.98] transition-all duration-200 text-center">
                    Sí, Salir
                </Link>
            </div>
        </div>
    </div>
</template>
