<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import type { EChartsOption } from 'echarts';
import { computed } from 'vue';
import Card from '@/components/Card.vue';
import Chart from '@/components/Chart.vue';
import EmptyState from '@/components/EmptyState.vue';
import MeterSelect from '@/components/MeterSelect.vue';
import { baseOption, legend, seriesColour } from '@/lib/echarts';
import { formatInstant, pence, timezone } from '@/lib/format';
import { token } from '@/lib/theme';
import type { MeterOption } from '@/types';

interface RatePoint {
    from: string;
    to: string | null;
    pence: number;
}

const props = defineProps<{
    meters: MeterOption[];
    selected: number | null;
    agreements: { code: string; product: string; from: string; to: string | null; current: boolean }[];
    rates: Partial<Record<'standard' | 'day' | 'night' | 'standing', RatePoint[]>>;
}>();

const tz = timezone();
const when = (iso: string) => formatInstant(iso, { day: 'numeric', month: 'short', year: 'numeric' }, tz);
const unitKinds = computed(() => (['standard', 'day', 'night'] as const).filter((k) => (props.rates[k]?.length ?? 0) > 0));
const standing = computed(() => props.rates.standing?.at(-1)?.pence ?? null);
const unitRateCount = computed(() => unitKinds.value.reduce((n, k) => n + (props.rates[k]?.length ?? 0), 0));

function option(): EChartsOption {
    const base = baseOption();
    const now = Date.now();
    const labels = { standard: 'Unit rate', day: 'Day rate', night: 'Night rate' };
    const horizon = Date.now() + 2 * 86400_000;

    return {
        ...base,
        legend: unitKinds.value.length > 1 ? legend() : undefined,
        grid: { ...(base.grid as object), top: unitKinds.value.length > 1 ? 36 : 24, right: 24 },
        tooltip: {
            ...(base.tooltip as object),
            trigger: 'axis',
            valueFormatter: (v) => `${Number(v).toFixed(2)}p/kWh`,
        },
        xAxis: {
            ...(base.xAxis as object),
            type: 'time',
            axisLabel: {
                ...((base.xAxis as any).axisLabel),
                hideOverlap: true,
                showMinLabel: false,
                showMaxLabel: false,
                formatter: (v: number) => formatInstant(new Date(v).toISOString(), { weekday: 'short', day: 'numeric', month: 'short' }, tz),
            },
        },
        yAxis: { ...(base.yAxis as object), type: 'value', axisLabel: { ...((base.yAxis as any).axisLabel), formatter: '{value}p' } },
        series: unitKinds.value.map((kind, i) => ({
            type: 'line' as const,
            name: labels[kind],
            step: 'start' as const,
            showSymbol: false,
            lineStyle: { width: 2, color: seriesColour(i) },
            itemStyle: { color: seriesColour(i) },
            data: (props.rates[kind] ?? []).flatMap((r) => [
                [r.from, r.pence],
                [r.to ?? new Date(Math.min(horizon, Math.max(now, new Date(r.from).getTime()) + 3600_000)).toISOString(), r.pence],
            ]),
            markLine: i === 0 ? {
                symbol: 'none',
                silent: true,
                label: { formatter: 'Now', color: token('text-muted'), fontSize: 11 },
                lineStyle: { color: token('text-muted'), type: 'dashed', width: 1 },
                data: [{ xAxis: new Date(now).toISOString() }],
            } : undefined,
        })),
    };
}
</script>

<template>
    <Head title="Tariffs" />

    <EmptyState v-if="selected === null" title="No tariffs yet">Run a sync to pull your agreements and rates.</EmptyState>

    <div v-else class="space-y-4">
        <MeterSelect :meters="meters" :model-value="selected" @update:model-value="(meter) => router.get('/tariffs', { meter }, { preserveScroll: true })" />

        <Card title="Unit rates" :subtitle="`Last 7 days and any published prices ahead · standing charge ${pence(standing)}/day`">
            <Chart v-if="unitRateCount > 0" :option="option" height="320px" label="Unit rates over time for the current tariff" />
            <p v-else class="text-sm text-ink-3">No rates stored for the current tariff yet.</p>
        </Card>

        <Card title="Agreement history">
            <table class="w-full text-sm">
                <thead class="text-left text-xs text-ink-3">
                    <tr><th class="py-1 font-medium">Tariff</th><th class="font-medium">From</th><th class="font-medium">To</th></tr>
                </thead>
                <tbody>
                    <tr v-for="a in agreements" :key="a.code + a.from" class="border-t border-line">
                        <td class="py-2">
                            <span class="font-mono">{{ a.code }}</span>
                            <span v-if="a.current" class="ml-2 rounded bg-surface-2 px-1.5 py-0.5 text-xs text-ink-2">Current</span>
                        </td>
                        <td class="tabular">{{ when(a.from) }}</td>
                        <td class="tabular">{{ a.to ? when(a.to) : 'Ongoing' }}</td>
                    </tr>
                </tbody>
            </table>
        </Card>
    </div>
</template>
