<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import type { EChartsOption } from 'echarts';
import { computed, ref } from 'vue';
import Card from '@/components/Card.vue';
import Chart from '@/components/Chart.vue';
import EmptyState from '@/components/EmptyState.vue';
import MeterSelect from '@/components/MeterSelect.vue';
import SegmentedControl from '@/components/SegmentedControl.vue';
import { bucketLabel } from '@/lib/buckets';
import { baseOption, legend, seriesColour } from '@/lib/echarts';
import { addDays, endOfMonth, formatDate, kwh, money, pence, slotLabel, timezone } from '@/lib/format';
import { token } from '@/lib/theme';
import type { Granularity, MeterOption, Metric, SeriesPoint, Totals } from '@/types';

interface Selection {
    meter: number;
    from: string;
    to: string;
    granularity: Granularity;
    finer: Granularity | null;
}

const props = defineProps<{
    meters: MeterOption[];
    selection: Selection | null;
    coverage?: { from: string | null; to: string | null };
    totals?: Totals;
    series?: SeriesPoint[];
    heatmap?: { date: string; slot: number; kwh: number; cost: number }[] | null;
}>();

const tz = timezone();
const metric = ref<Metric>('cost');
const showTable = ref(false);

const granularityOptions: { value: Granularity; label: string }[] = [
    { value: 'half_hour', label: 'Half-hour' },
    { value: 'day', label: 'Day' },
    { value: 'week', label: 'Week' },
    { value: 'month', label: 'Month' },
];

function visit(changes: Partial<Selection>): void {
    if (!props.selection) return;
    const next = { ...props.selection, ...changes };
    router.get('/explore', { meter: next.meter, from: next.from, to: next.to, granularity: next.granularity }, { preserveState: true, preserveScroll: true });
}

function quickRange(days: number | 'all'): void {
    const end = props.coverage?.to ?? props.selection!.to;
    const start = days === 'all' ? (props.coverage?.from ?? end) : addDays(end, -(days - 1));
    const span = days === 'all' ? 9999 : days;
    const granularity: Granularity = span <= 3 ? 'half_hour' : span <= 92 ? 'day' : span <= 366 ? 'week' : 'month';
    visit({ from: start, to: end, granularity });
}

/** Clicking a bar opens that bucket one level finer. */
function drill(index: number): void {
    const s = props.selection;
    const point = props.series?.[index];
    if (!s || !point || !s.finer) return;
    const from = point.bucket;
    const to = s.granularity === 'month' ? endOfMonth(from) : s.granularity === 'week' ? addDays(from, 6) : from;
    visit({ from, to, granularity: s.finer });
}

const title = computed(() => {
    const s = props.selection;
    if (!s) return '';
    return s.from === s.to
        ? formatDate(s.from, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
        : `${formatDate(s.from, { day: 'numeric', month: 'short', year: 'numeric' })} – ${formatDate(s.to, { day: 'numeric', month: 'short', year: 'numeric' })}`;
});

const meterLabel = computed(() => props.meters.find((m) => m.id === props.selection?.meter)?.label ?? '');

function seriesOption(): EChartsOption {
    const s = props.selection!;
    const points = props.series ?? [];
    const base = baseOption();
    const surface = token('surface-1');
    const cost = metric.value === 'cost';
    const showStanding = cost && s.granularity !== 'half_hour';
    const many = points.length > 120;
    const bar = (name: string, data: (number | null)[], colour: string, top: boolean) => ({
        type: 'bar' as const,
        name,
        stack: 'total',
        data,
        barMaxWidth: 28,
        cursor: s.finer ? 'pointer' : 'default',
        itemStyle: { color: colour, borderColor: surface, borderWidth: many ? 0 : 1, borderRadius: top ? [4, 4, 0, 0] : 0 },
    });

    return {
        ...base,
        grid: { ...(base.grid as object), top: showStanding ? 36 : 24, bottom: many ? 40 : 8 },
        legend: showStanding ? legend() : undefined,
        tooltip: {
            ...(base.tooltip as object),
            trigger: 'axis',
            axisPointer: { type: 'shadow' },
            formatter: (params: any) => {
                const p = points[params[0].dataIndex]!;
                if (p.kwh === null) {
                    return `<b>${bucketLabel(p.bucket, s.granularity, tz, true)}</b><br>No readings yet`;
                }
                const lines = cost
                    ? [`${money(p.cost)} total`, showStanding ? `${money(p.unitCost)} energy + ${money(p.standing)} standing` : '', `${kwh(p.kwh, true)}`]
                    : [`${kwh(p.kwh, true)}`, money(p.cost)];
                const rate = p.kwh > 0 && p.unitCost !== null ? `<br><span style="opacity:.7">avg ${pence((p.unitCost * 100) / p.kwh)}/kWh</span>` : '';
                const hint = s.finer ? '<br><span style="opacity:.6">Click to drill down</span>' : '';
                return `<b>${bucketLabel(p.bucket, s.granularity, tz, true)}</b><br>${lines.filter(Boolean).join('<br>')}${rate}${hint}`;
            },
        },
        dataZoom: many ? [{ type: 'inside' }, { type: 'slider', height: 18, bottom: 4, borderColor: 'transparent' }] : [],
        xAxis: { ...(base.xAxis as object), type: 'category', data: points.map((p) => bucketLabel(p.bucket, s.granularity, tz)) },
        yAxis: {
            ...(base.yAxis as object),
            type: 'value',
            axisLabel: { ...((base.yAxis as any).axisLabel), formatter: cost ? (v: number) => `£${v}` : (v: number) => `${v}` },
            name: cost ? '' : 'kWh',
            nameTextStyle: { color: token('text-muted'), fontSize: 11 },
        },
        series: cost
            ? showStanding
                ? [bar('Energy', points.map((p) => p.unitCost), seriesColour(0), false), bar('Standing charge', points.map((p) => p.standing), seriesColour(1), true)]
                : [bar('Energy', points.map((p) => p.unitCost), seriesColour(0), true)]
            : [bar('Usage', points.map((p) => p.kwh), seriesColour(0), true)],
    };
}

function goBack(): void {
    window.history.back();
}

const heatmapDates = computed(() => [...new Set((props.heatmap ?? []).map((c) => c.date))]);

function heatmapOption(): EChartsOption {
    const cells = props.heatmap ?? [];
    const dates = heatmapDates.value;
    const index = new Map(dates.map((d, i) => [d, i]));
    const value = (c: { kwh: number; cost: number }) => (metric.value === 'cost' ? c.cost : c.kwh);
    const max = Math.max(0.001, ...cells.map(value));
    const base = baseOption();

    return {
        ...base,
        grid: { left: 8, right: 8, top: 8, bottom: 48, containLabel: true },
        tooltip: {
            ...(base.tooltip as object),
            formatter: (p: any) => {
                const c = cells[p.dataIndex]!;
                return `<b>${formatDate(c.date, { weekday: 'short', day: 'numeric', month: 'short' })} ${slotLabel(c.slot)}</b><br>${kwh(c.kwh, true)} · ${money(c.cost)}`;
            },
        },
        xAxis: { ...(base.xAxis as object), type: 'category', data: dates.map((d) => formatDate(d, { day: 'numeric', month: 'short' })), splitArea: { show: false } },
        yAxis: {
            ...(base.yAxis as object),
            type: 'category',
            inverse: true,
            data: Array.from({ length: 48 }, (_, i) => slotLabel(i)),
            axisLabel: { ...((base.yAxis as any).axisLabel), interval: 3 },
            splitLine: { show: false },
        },
        visualMap: {
            min: 0,
            max,
            calculable: false,
            orient: 'horizontal',
            left: 'center',
            bottom: 0,
            itemHeight: 160,
            itemWidth: 10,
            text: [metric.value === 'cost' ? money(max) : kwh(max, true), '0'],
            textStyle: { color: token('text-muted'), fontSize: 11 },
            inRange: { color: [token('seq-100'), token('seq-250'), token('seq-450'), token('seq-700')] },
        },
        series: [{
            type: 'heatmap',
            data: cells.map((c) => [index.get(c.date), c.slot, value(c)]),
            itemStyle: { borderColor: token('surface-1'), borderWidth: 1, borderRadius: 2 },
            emphasis: { itemStyle: { borderColor: token('text-primary'), borderWidth: 1 } },
        }],
    };
}
</script>

<template>
    <Head title="Explore" />

    <EmptyState v-if="!selection" title="Nothing to explore yet">Run a sync first to pull your meters and usage.</EmptyState>

    <div v-else class="space-y-4">
        <div class="flex flex-wrap items-end gap-3">
            <MeterSelect :meters="meters" :model-value="selection.meter" @update:model-value="(meter) => visit({ meter })" />
            <label class="flex flex-col gap-1 text-xs text-ink-3">
                From
                <input type="date" :value="selection.from" :min="coverage?.from ?? undefined" :max="selection.to" class="rounded-md border border-line bg-surface px-2 py-1 text-sm text-ink" @change="(e) => visit({ from: (e.target as HTMLInputElement).value })" />
            </label>
            <label class="flex flex-col gap-1 text-xs text-ink-3">
                To
                <input type="date" :value="selection.to" :min="selection.from" :max="coverage?.to ?? undefined" class="rounded-md border border-line bg-surface px-2 py-1 text-sm text-ink" @change="(e) => visit({ to: (e.target as HTMLInputElement).value })" />
            </label>
            <div class="flex gap-1 text-sm">
                <button v-for="d in [7, 30, 90, 365]" :key="d" type="button" class="rounded-md border border-line px-2.5 py-1.5 text-ink-2 hover:bg-surface-2" @click="quickRange(d)">{{ d === 365 ? '1y' : `${d}d` }}</button>
                <button type="button" class="rounded-md border border-line px-2.5 py-1.5 text-ink-2 hover:bg-surface-2" @click="quickRange('all')">All</button>
            </div>
        </div>

        <div class="grid gap-3 sm:grid-cols-4">
            <Card><p class="text-xs text-ink-3">Total cost</p><p class="tabular mt-1 text-2xl font-semibold">{{ money(totals!.cost) }}</p></Card>
            <Card><p class="text-xs text-ink-3">Usage</p><p class="tabular mt-1 text-2xl font-semibold">{{ kwh(totals!.kwh) }}</p></Card>
            <Card><p class="text-xs text-ink-3">Average unit rate</p><p class="tabular mt-1 text-2xl font-semibold">{{ pence(totals!.avgRate) }}<span class="text-sm font-normal text-ink-3">/kWh</span></p></Card>
            <Card><p class="text-xs text-ink-3">Standing charges</p><p class="tabular mt-1 text-2xl font-semibold">{{ money(totals!.standing) }}</p></Card>
        </div>

        <Card :title="`${meterLabel} · ${title}`" :subtitle="selection.finer ? 'Click a bar to drill down' : 'Half-hourly readings'">
            <template #actions>
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" class="rounded-md px-2 py-1 text-sm text-ink-2 hover:bg-surface-2" @click="goBack">← Back</button>
                    <SegmentedControl :model-value="selection.granularity" :options="granularityOptions" label="Granularity" @update:model-value="(granularity) => visit({ granularity })" />
                    <SegmentedControl v-model="metric" :options="[{ value: 'cost', label: '£' }, { value: 'kwh', label: 'kWh' }]" label="Metric" />
                </div>
            </template>
            <Chart :option="seriesOption" height="340px" :label="`${metric === 'cost' ? 'Cost' : 'Usage'} by ${selection.granularity.replace('_', '-')} for ${meterLabel}`" @click="(p) => drill(p.dataIndex)" />
            <button type="button" class="mt-2 text-xs text-accent hover:underline" :aria-expanded="showTable" @click="showTable = !showTable">{{ showTable ? 'Hide' : 'Show' }} table</button>
            <div v-if="showTable" class="mt-2 max-h-80 overflow-auto">
                <table class="tabular w-full text-sm">
                    <thead class="sticky top-0 bg-surface text-left text-xs text-ink-3">
                        <tr><th class="py-1 font-medium">Period</th><th class="text-right font-medium">kWh</th><th class="text-right font-medium">Energy</th><th class="text-right font-medium">Standing</th><th class="text-right font-medium">Total</th></tr>
                    </thead>
                    <tbody>
                        <tr v-for="p in series" :key="p.bucket" class="border-t border-line">
                            <td class="py-1">{{ bucketLabel(p.bucket, selection.granularity, tz, true) }}</td>
                            <td class="text-right">{{ p.kwh === null ? '—' : p.kwh.toFixed(3) }}</td>
                            <td class="text-right">{{ money(p.unitCost) }}</td>
                            <td class="text-right">{{ money(p.standing) }}</td>
                            <td class="text-right font-medium">{{ money(p.cost) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Card>

        <Card v-if="heatmap && heatmap.length" title="Time-of-day pattern" :subtitle="`${metric === 'cost' ? 'Cost' : 'Usage'} per half-hour, local time`">
            <Chart :option="heatmapOption" :height="`${Math.max(360, 48 * 7 + 60)}px`" label="Heatmap of half-hourly usage by day and time of day" />
        </Card>
    </div>
</template>
