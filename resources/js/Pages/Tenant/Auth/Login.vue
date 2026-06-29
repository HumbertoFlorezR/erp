<script setup>
import { useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

// 1. Capturamos los datos estéticos del Tenant desde el Middleware
const page = usePage();
const tenant = computed(() => {
    return page.props.tenant || {
        company_name: 'Mi Empresa',
        primary_color: '#3b82f6',
        logo_url: null
    };
});

// 2. Instanciamos el formulario oficial de Inertia
const form = useForm({
    email: '',
    password: '',
    remember: false,
});

// 3. Envío directo usando el motor nativo de Inertia
const submit = () => {
    form.post(window.location.pathname, {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <div class="min-h-screen bg-slate-50 flex items-center justify-center p-4 sm:p-6 font-sans">
        <div class="bg-white p-8 rounded-2xl shadow-xl border border-slate-100 max-w-md w-full">

            <div class="text-center mb-8">
                <div v-if="tenant.logo_url" class="mb-4">
                    <img :src="tenant.logo_url" alt="Logo corporativo" class="max-h-16 mx-auto object-contain">
                </div>
                <div v-else
                     class="w-16 h-16 mx-auto mb-4 rounded-2xl flex items-center justify-center text-white text-2xl font-black shadow-md"
                     :style="{ backgroundColor: tenant.primary_color }">
                    {{ tenant.company_name.substring(0, 2).toUpperCase() }}
                </div>

                <h1 class="text-2xl font-bold text-slate-800 tracking-tight">
                    {{ tenant.company_name }}
                </h1>
                <p class="text-slate-500 text-sm mt-1">Ingresa tus credenciales para acceder al ERP</p>
            </div>

            <form @submit.prevent="submit" class="space-y-5">

                <div>
                    <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-2">Correo Electrónico</label>
                    <input v-model="form.email"
                           type="email"
                           required
                           class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-slate-700 focus:outline-none text-sm"
                           :class="{ 'border-red-500': form.errors.email }"
                           placeholder="usuario@empresa.com" />
                    <span v-if="form.errors.email" class="text-xs text-red-500 mt-1 block font-medium">{{ form.errors.email }}</span>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-2">Contraseña</label>
                    <input v-model="form.password"
                           type="password"
                           required
                           class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-slate-700 focus:outline-none text-sm"
                           :class="{ 'border-red-500': form.errors.password }"
                           placeholder="••••••••" />
                    <span v-if="form.errors.password" class="text-xs text-red-500 mt-1 block font-medium">{{ form.errors.password }}</span>
                </div>

                <div class="flex items-center justify-between text-sm">
                    <label class="flex items-center text-slate-600 cursor-pointer select-none">
                        <input v-model="form.remember" type="checkbox" class="rounded border-slate-300 text-blue-600 mr-2" />
                        Recordar sesión
                    </label>
                </div>

                <button type="submit"
                        :disabled="form.processing"
                        class="w-full text-white font-semibold py-3 px-4 rounded-xl shadow-md hover:opacity-90 active:scale-[0.98] transition-all duration-200 text-sm flex items-center justify-center gap-2"
                        :style="{ backgroundColor: tenant.primary_color }">
                    <span v-if="form.processing" class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                    {{ form.processing ? 'Autenticando...' : 'Iniciar Sesión' }}
                </button>
            </form>
        </div>
    </div>
</template>
