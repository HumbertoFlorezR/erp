<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const props = defineProps({
    summary: Object // { current_month_total, previous_month_total, variation_percent, top_categories, monthly_trend }
});

const page = usePage();
const tenant = computed(() => page.props.tenant || { primary_color: '#0f172a' });
const primaryColor = computed(() => tenant.value?.primary_color || '#0f172a');

const formatCurrency = (amount) => {
    return new Intl.NumberFormat('es-CO', {
        style: 'currency',
        currency: 'COP',
        minimumFractionDigits: 0
    }).format(amount || 0);
};

const isIncrease = computed(() => (props.summary?.variation_percent ?? 0) > 0);

const maxCategoryTotal = computed(() => {
    const totals = (props.summary?.top_categories || []).map(c => c.total);
    return totals.length ? Math.max(...totals) : 1;
});

const maxTrendTotal = computed(() => {
    const totals = (props.summary?.monthly_trend || []).map(m => m.total);
    return totals.length ? Math.max(...totals, 1) : 1;
});

const barHeight = (total) => {
    const pct = (total / maxTrendTotal.value) * 100;
    return Math.max(pct, 4); // altura mínima para que siempre se vea la barra
};

const hexToRgb = (hex) => {
    const cleanHex = hex.replace('#', '');
    const normalized = cleanHex.length === 3
        ? cleanHex.split('').map(char => char + char).join('')
        : cleanHex;

    const intValue = Number.parseInt(normalized, 16);

    return {
        r: (intValue >> 16) & 255,
        g: (intValue >> 8) & 255,
        b: intValue & 255
    };
};

const mixColors = (colorA, colorB, weight) => {
    const a = hexToRgb(colorA);
    const b = hexToRgb(colorB);
    const mix = (start, end, amount) => Math.round(start + (end - start) * amount);

    return `#${[mix(a.r, b.r, weight), mix(a.g, b.g, weight), mix(a.b, b.b, weight)]
        .map(value => value.toString(16).padStart(2, '0'))
        .join('')}`;
};

const getScaleColor = (index, totalItems) => {
    const safeTotal = Math.max(totalItems || 1, 1);
    const progress = safeTotal <= 1 ? 1 : index / (safeTotal - 1);
    const weight = Math.max(0.25, Math.min(1, 0.25 + progress * 0.75));

    return mixColors('#e2e8f0', primaryColor.value, weight);
};
</script>

<template>
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5">
        <div class="flex items-start justify-between mb-4">
            <div>
                <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Gastos del Mes</p>
                <p class="text-2xl font-bold text-slate-800 mt-1">
                    {{ formatCurrency(summary.current_month_total) }}
                </p>
            </div>

            <span v-if="summary.variation_percent !== null"
                  :class="isIncrease ? 'bg-rose-50 text-rose-600' : 'bg-emerald-50 text-emerald-600'"
                  class="text-xs font-bold px-2.5 py-1 rounded-full flex items-center gap-1">
                {{ isIncrease ? '▲' : '▼' }} {{ Math.abs(summary.variation_percent) }}%
            </span>
        </div>

        <!-- Mini gráfico de tendencia (6 meses) -->
        <div class="flex items-end justify-between gap-2 h-16 mb-5 px-1">
            <div v-for="(m, idx) in summary.monthly_trend" :key="idx" class="flex-1 flex flex-col items-center gap-1">
                <div class="w-full bg-slate-100 rounded-t-md relative flex items-end" style="height: 48px;">
                    <div class="w-full rounded-t-md transition-all"
                         :style="{
                             height: barHeight(m.total) + '%',
                             backgroundColor: getScaleColor(idx, summary.monthly_trend?.length || 1)
                         }"
                         :title="formatCurrency(m.total)">
                    </div>
                </div>
                <span class="text-[10px] text-slate-400 capitalize">{{ m.month }}</span>
            </div>
        </div>

        <!-- Top categorías -->
        <div>
            <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Top Categorías</p>
            <div class="space-y-2">
                <div v-for="(cat, idx) in summary.top_categories" :key="cat.name" class="text-sm">
                    <div class="flex justify-between text-slate-600 mb-0.5">
                        <span>{{ cat.name }}</span>
                        <span class="font-semibold text-slate-800">{{ formatCurrency(cat.total) }}</span>
                    </div>
                    <div class="w-full bg-slate-100 rounded-full h-1.5">
                        <div class="h-1.5 rounded-full"
                             :style="{
                                 width: (cat.total / maxCategoryTotal * 100) + '%',
                                 backgroundColor: getScaleColor(idx, summary.top_categories?.length || 1)
                             }">
                        </div>
                    </div>
                </div>

                <p v-if="!summary.top_categories?.length" class="text-xs text-slate-400 italic">
                    Sin gastos registrados este mes.
                </p>
            </div>
        </div>
    </div>
</template>
