<script setup lang="ts">
import type { EChartsOption } from 'echarts';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import Card from '@/components/Card.vue';
import Chart from '@/components/Chart.vue';
import { baseOption, seriesColour } from '@/lib/echarts';
import { formatInstant, timezone } from '@/lib/format';

interface Point {
    readAt: string;
    demandWatts: number | null;
    consumptionDeltaWh: number | null;
}

const POLL_MS = 30_000;
const tz = timezone();
const latest = ref<Point | null>(null);
const points = ref<Point[]>([]);
const error = ref<string | null>(null);
let timer: ReturnType<typeof setInterval> | undefined;

async function refresh(): Promise<void> {
    if (document.hidden) return;
    try {
        const response = await fetch('/live', { headers: { Accept: 'application/json' } });
        const body = await response.json();
        error.value = body.error ?? null;
        latest.value = body.latest;
        points.value = body.points ?? [];
    } catch {
        error.value = 'Live data is unavailable right now.';
    }
}

onMounted(() => {
    void refresh();
    timer = setInterval(refresh, POLL_MS);
});
onBeforeUnmount(() => clearInterval(timer));

const time = (iso: string) => formatInstant(iso, { hour: '2-digit', minute: '2-digit', second: '2-digit' }, tz);

function option(): EChartsOption {
    const base = baseOption();
    return {
        ...base,
        grid: { left: 0, right: 0, top: 8, bottom: 0, containLabel: true },
        tooltip: { ...(base.tooltip as object), trigger: 'axis', valueFormatter: (v) => `${Math.round(Number(v))} W` },
        xAxis: { ...(base.xAxis as object), type: 'category', data: points.value.map((p) => time(p.readAt)), axisLabel: { show: false } },
        yAxis: { ...(base.yAxis as object), type: 'value', axisLabel: { color: 'inherit', fontSize: 10, formatter: '{value} W' } },
        series: [{
            type: 'line',
            name: 'Demand',
            data: points.value.map((p) => p.demandWatts),
            showSymbol: false,
            lineStyle: { width: 2, color: seriesColour(0) },
            itemStyle: { color: seriesColour(0) },
            areaStyle: { color: seriesColour(0), opacity: 0.08 },
        }],
    };
}
</script>

<template>
    <Card title="Live demand" subtitle="Octopus Home Mini · last 15 minutes · refreshes every 30 seconds">
        <div class="flex flex-wrap items-end gap-6">
            <div>
                <p class="tabular text-4xl font-semibold tracking-tight">
                    {{ latest?.demandWatts != null ? Math.round(latest.demandWatts).toLocaleString('en-GB') : '—' }}<span class="ml-1 text-lg font-normal text-ink-3">W</span>
                </p>
                <p class="text-xs text-ink-3">
                    <template v-if="latest">at {{ time(latest.readAt) }}</template>
                    <template v-else-if="error">{{ error }}</template>
                    <template v-else>Waiting for the first reading…</template>
                </p>
            </div>
            <div class="min-w-60 flex-1">
                <Chart v-if="points.length" :option="option" height="96px" label="Electricity demand over the last 15 minutes" />
            </div>
        </div>
    </Card>
</template>
