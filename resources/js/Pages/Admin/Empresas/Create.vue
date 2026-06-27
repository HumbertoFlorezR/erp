<script setup>
import { useForm, Link } from '@inertiajs/vue3'

const form = useForm({
    id: '',
    nombre_empresa: '',
    nit_rut: '',
    telefono: '',
    email_contacto: '',
})

const enviarFormulario = () => {
    // Enviamos los datos al método store() del EmpresaController
    form.post('/empresas', {
        onSuccess: () => form.reset(),
    })
}
</script>

<template>
    <div class="min-h-screen bg-gray-100 p-6 flex items-center justify-center">
        <div class="bg-white rounded-xl shadow-lg p-8 max-w-lg w-full">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-bold text-gray-800">Registrar Nueva Empresa</h2>
                <Link href="/empresas" class="text-sm text-gray-500 hover:text-gray-700 underline">Volver</Link>
            </div>

            <form @submit.prevent="enviarFormulario" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Subdominio del Sistema</label>
                    <div class="mt-1 flex rounded-md shadow-sm">
                        <input v-model="form.id" type="text" placeholder="ej: miempresa" class="p-2 border border-gray-300 flex-1 block w-full rounded-none rounded-l-md sm:text-sm focus:ring-blue-500 focus:border-blue-500" />
                        <span class="inline-flex items-center px-3 rounded-r-md border border-l-0 border-gray-300 bg-gray-50 text-gray-500 text-sm">.erp-global.test</span>
                    </div>
                    <p v-if="form.errors.id" class="text-red-500 text-xs mt-1">{{ form.errors.id }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Nombre Comercial</label>
                    <input v-model="form.nombre_empresa" type="text" class="mt-1 p-2 block w-full border border-gray-300 rounded-md shadow-sm sm:text-sm" />
                    <p v-if="form.errors.nombre_empresa" class="text-red-500 text-xs mt-1">{{ form.errors.nombre_empresa }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">NIT / Identificación Fiscal</label>
                    <input v-model="form.nit_rut" type="text" class="mt-1 p-2 block w-full border border-gray-300 rounded-md shadow-sm sm:text-sm" />
                    <p v-if="form.errors.nit_rut" class="text-red-500 text-xs mt-1">{{ form.errors.nit_rut }}</p>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Teléfono</label>
                        <input v-model="form.telefono" type="text" class="mt-1 p-2 block w-full border border-gray-300 rounded-md shadow-sm sm:text-sm" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Email de Contacto</label>
                        <input v-model="form.email_contacto" type="email" class="mt-1 p-2 block w-full border border-gray-300 rounded-md shadow-sm sm:text-sm" />
                        <p v-if="form.errors.email_contacto" class="text-red-500 text-xs mt-1">{{ form.errors.email_contacto }}</p>
                    </div>
                </div>

                <div class="pt-2">
                    <button type="submit" :disabled="form.processing" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-md shadow transition duration-200 disabled:opacity-50">
                        {{ form.processing ? 'Creando Empresa e Infraestructura...' : 'Crear Empresa' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
