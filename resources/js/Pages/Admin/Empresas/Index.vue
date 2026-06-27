<script setup>
import { Link } from '@inertiajs/vue3'

// Recibimos las empresas registradas desde el controlador de Laravel
defineProps({
    empresas: Array
})
</script>

<template>
    <div class="min-h-screen bg-gray-100 p-6">
        <div class="max-w-6xl mx-auto">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-3xl font-bold text-gray-800">Administración de Empresas</h1>
                <Link href="/empresas/create" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-lg shadow transition duration-200">
                    + Nueva Empresa
                </Link>
            </div>

            <div class="bg-white rounded-xl shadow overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subdominio</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nombre de Empresa</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">NIT / RUT</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email Contacto</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <tr v-for="empresa in empresas" :key="empresa.id" class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap font-mono text-sm text-blue-600">{{ empresa.id }}.test</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ empresa.nombre_empresa }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ empresa.nit_rut }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ empresa.email_contacto || 'N/A' }}</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span :class="empresa.estado === 'activo' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'" class="px-2.5 py-0.5 rounded-full text-xs font-medium uppercase">
                                    {{ empresa.estado }}
                                </span>
                            </td>
                        </tr>
                        <tr v-if="empresas.length === 0">
                            <td colspan="5" class="px-6 py-10 text-center text-sm text-gray-500">
                                No hay empresas registradas aún.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
