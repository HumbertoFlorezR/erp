<script setup>
import { ref, computed } from 'vue';
import { Head, usePage, useForm } from '@inertiajs/vue3';
import { route } from 'ziggy-js';

// Props enviados desde el controlador
const props = defineProps({
    resolutions: Array
});

const page = usePage();
const tenant = computed(() => page.props.tenant || { company_name: 'ERP GLOBAL', primary_color: '#0f172a' });

// Formulario reactivo con Inertia para registrar una nueva resolución
const form = useForm({
    prefix: '',
    resolution_number: '',
    from_number: 1,
    to_number: 1000,
    current_number: 0,
    date_from: '',
    date_to: ''
});

const submitResolution = () => {
    form.post('/settings/resolutions', {
        onSuccess: () => {
            form.reset();
            alert('Resolución DIAN agregada correctamente.');
        },
        onError: (err) => {
            console.error(err);
            alert('Por favor verifique los campos del formulario.');
        }
    });
};

const toggleActive = (id) => {
    form.put(window.location.origin + `/settings/resolutions/${id}/toggle`);
};
</script>

<template>
    <Head title="Configuración - Resoluciones DIAN" />

    <div class="min-h-screen bg-slate-100 p-6 font-sans">

        <header class="bg-white px-6 py-4 rounded-2xl shadow-sm border border-slate-200 flex justify-between items-center mb-6">
            <div class="flex items-center gap-4">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center text-white font-black text-sm" :style="{ backgroundColor: tenant.primary_color }">
                    ⚙️
                </div>
                <div>
                    <h1 class="text-lg font-extrabold text-slate-800">Resoluciones de Facturación</h1>
                    <p class="text-xs text-slate-400">Panel de Configuración Global > DIAN</p>
                </div>
            </div>

            <a href="/sales/pos" class="px-4 py-2 bg-slate-200 hover:bg-slate-300 text-slate-700 text-xs font-bold rounded-xl transition-colors">
                Volver al POS ⚡
            </a>
        </header>

        <div class="grid grid-cols-1 xl:grid-cols-12 gap-6 items-start">

            <div class="xl:col-span-7 bg-white p-6 rounded-2xl shadow-sm border border-slate-200 space-y-4">
                <div>
                    <h2 class="text-sm font-extrabold text-slate-700 uppercase tracking-wide">Historial de Resoluciones</h2>
                    <p class="text-xs text-slate-400 mt-0.5">Control de consecutivos autorizados y folios disponibles.</p>
                </div>

                <div class="overflow-x-auto rounded-xl border border-slate-100">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase text-[10px]">
                                <th class="p-3">Número / Prefijo</th>
                                <th class="p-3 text-center">Rango Autorizado</th>
                                <th class="p-3 text-center">Progreso / Actual</th>
                                <th class="p-3">Vigencia</th>
                                <th class="p-3 text-center">Estado</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-600">
                            <tr v-for="res in resolutions" :key="res.id" class="hover:bg-slate-50/50" :class="{'bg-emerald-50/20': res.is_active}">
                                <td class="p-3">
                                    <p class="font-bold text-slate-800 font-mono">{{ res.resolution_number }}</p>
                                    <span v-if="res.prefix" class="px-1.5 py-0.5 bg-slate-100 border text-[10px] font-black text-slate-600 rounded mt-0.5 inline-block">
                                        Prefijo: {{ res.prefix }}
                                    </span>
                                </td>
                                <td class="p-3 text-center font-mono font-medium text-slate-500">
                                    {{ res.from_number }} - {{ res.to_number }}
                                </td>
                                <td class="p-3 text-center">
                                    <div class="flex flex-col items-center">
                                        <span class="font-black text-slate-900 font-mono text-sm bg-white border px-2 py-0.5 rounded shadow-sm">
                                            # {{ res.current_number }}
                                        </span>
                                        <span class="text-[9px] text-slate-400 mt-1">
                                            Quedan {{ res.to_number - res.current_number }} folios
                                        </span>
                                    </div>
                                </td>
                                <td class="p-3 text-[11px] space-y-0.5">
                                    <p>
                                        <span class="text-slate-400 font-medium">Desde:</span>
                                        <span class="font-bold text-slate-700 px-1">{{ res.date_from ? res.date_from.substring(0, 10) : '' }}</span>
                                    </p>
                                    <p>
                                        <span class="text-slate-400 font-medium">Hasta:</span>
                                        <span class="font-bold text-slate-700 px-1">{{ res.date_to ? res.date_to.substring(0, 10) : '' }}</span>
                                    </p>
                                </td>
                                <td class="p-3 text-center">
                                    <button
                                        @click="toggleActive(res.id)"
                                        class="px-2.5 py-1 rounded-full text-[10px] font-black tracking-wider uppercase shadow-sm transition-all border"
                                        :class="res.is_active
                                            ? 'bg-emerald-100 text-emerald-700 border-emerald-300 hover:bg-emerald-200'
                                            : 'bg-slate-100 text-slate-400 border-slate-200 hover:bg-slate-200'"
                                    >
                                        {{ res.is_active ? '🟢 Activo' : '⚪ Inactivo' }}
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="resolutions.length === 0">
                                <td colspan="5" class="p-8 text-center text-slate-400 font-medium">
                                    📋 No hay ninguna resolución registrada para este negocio.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="xl:col-span-5 bg-white p-6 rounded-2xl shadow-sm border border-slate-200 space-y-4">
                <div>
                    <h2 class="text-sm font-extrabold text-slate-700 uppercase tracking-wide">Nueva Resolución (Form. 1876)</h2>
                    <p class="text-xs text-slate-400 mt-0.5">Transcribe los datos del documento oficial otorgado por la DIAN.</p>
                </div>

                <form @submit.prevent="submitResolution" class="grid grid-cols-2 gap-4 text-xs">
                    <div class="col-span-2">
                        <label class="font-bold text-slate-500 block mb-1">Número de Resolución</label>
                        <input
                            v-model="form.resolution_number"
                            type="text"
                            required
                            placeholder="Ej: 187640000001"
                            class="w-full border-slate-200 rounded-xl p-2.5 bg-slate-50 font-medium focus:ring-slate-800 focus:border-slate-800"
                        />
                    </div>

                    <div>
                        <label class="font-bold text-slate-500 block mb-1">Prefijo (Opcional)</label>
                        <input
                            v-model="form.prefix"
                            type="text"
                            placeholder="Ej: SETP, POS"
                            class="w-full border-slate-200 rounded-xl p-2.5 bg-slate-50 font-mono font-bold uppercase focus:ring-slate-800 focus:border-slate-800"
                        />
                    </div>
                    <div>
                        <label class="font-bold text-slate-500 block mb-1">Número Actual (Último usado)</label>
                        <input
                            v-model.number="form.current_number"
                            type="number"
                            required
                            class="w-full border-slate-200 rounded-xl p-2.5 bg-slate-50 font-mono font-bold focus:ring-slate-800 focus:border-slate-800"
                        />
                    </div>

                    <div>
                        <label class="font-bold text-slate-500 block mb-1">Desde el Número</label>
                        <input
                            v-model.number="form.from_number"
                            type="number"
                            required
                            class="w-full border-slate-200 rounded-xl p-2.5 bg-slate-50 font-mono focus:ring-slate-800 focus:border-slate-800"
                        />
                    </div>
                    <div>
                        <label class="font-bold text-slate-500 block mb-1">Hasta el Número</label>
                        <input
                            v-model.number="form.to_number"
                            type="number"
                            required
                            class="w-full border-slate-200 rounded-xl p-2.5 bg-slate-50 font-mono focus:ring-slate-800 focus:border-slate-800"
                        />
                    </div>

                    <div>
                        <label class="font-bold text-slate-500 block mb-1">Fecha de Inicio (Vigencia)</label>
                        <input
                            v-model="form.date_from"
                            type="date"
                            required
                            class="w-full border-slate-200 rounded-xl p-2.5 bg-slate-50 focus:ring-slate-800 focus:border-slate-800"
                        />
                    </div>
                    <div>
                        <label class="font-bold text-slate-500 block mb-1">Fecha de Fin (Vencimiento)</label>
                        <input
                            v-model="form.date_to"
                            type="date"
                            required
                            class="w-full border-slate-200 rounded-xl p-2.5 bg-slate-50 focus:ring-slate-800 focus:border-slate-800"
                        />
                    </div>

                    <div class="col-span-2 pt-2">
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="w-full text-white font-black text-xs p-3 rounded-xl transition-all shadow-md active:scale-[0.98] tracking-wider uppercase"
                            :style="{ backgroundColor: tenant.primary_color }"
                        >
                            {{ form.processing ? 'Registrando...' : '💾 Guardar y Activar Resolución' }}
                        </button>
                    </div>
                </form>
            </div>

        </div>

    </div>
</template>
