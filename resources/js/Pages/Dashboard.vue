<script setup>
import { ref } from 'vue'

const titulo = ref('¡Hola desde Vue 3 + Inertia!')
</script>

<template>
  <div class="p-8 max-w-md mx-auto bg-white rounded-xl shadow-md space-y-4 my-10">
    <h1 class="text-2xl font-bold text-gray-900">{{ titulo }}</h1>
    <p class="text-gray-500 text-sm">El frontend reactivo de tu ERP está listo.</p>
  </div>
</template>
