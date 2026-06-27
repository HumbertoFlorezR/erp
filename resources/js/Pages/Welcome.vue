<script setup>
// Recibimos la propiedad opcional que mandamos desde las rutas de Laravel
defineProps({
    tenantName: {
        type: String,
        default: ''
    }
})
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-slate-100 to-slate-200 flex items-center justify-center p-6 font-sans">
        <div class="bg-white p-8 rounded-2xl shadow-xl text-center max-w-md w-full border border-gray-100">
            <div class="w-16 h-16 mx-auto mb-4 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center text-2xl font-bold">
                {{ tenantName ? tenantName.substring(0, 2) : 'AD' }}
            </div>

            <h1 class="text-3xl font-black text-slate-800 mb-2 tracking-tight">
                {{ tenantName ? 'ERP ' + tenantName : 'ERP Global Admin' }}
            </h1>

            <p class="text-slate-600 mb-6 text-sm leading-relaxed">
                {{ tenantName ? 'Conectado con éxito a tu entorno e infraestructura de base de datos aislada.' : 'Panel central de control y aprovisionamiento de empresas.' }}
            </p>

            <div v-if="!tenantName">
                <a href="/empresas" class="inline-block w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2.5 px-4 rounded-xl shadow transition duration-200">
                    Ir a Administración de Empresas
                </a>
            </div>
            <div v-else class="space-y-2">
                <div class="inline-block w-full bg-emerald-600 text-white font-semibold py-2 px-4 rounded-lg text-xs uppercase tracking-wider">
                    Conexión Segura Activa
                </div>
                <p class="text-xs text-gray-400 mt-2">Próximamente: Formulario de Login del Tenant</p>
            </div>
        </div>
    </div>
</template>
