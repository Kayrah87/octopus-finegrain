<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import type { EChartsOption } from 'echarts';
import { computed, ref } from 'vue';
import Card from '@/components/Card.vue';
import ChangeBadge from '@/components/ChangeBadge.vue';
import Chart from '@/components/Chart.vue';
import EmptyState from '@/components/EmptyState.vue';
import MeterSelect from '@/components/MeterSelect.vue';
import SegmentedControl from '@/components/SegmentedControl.vue';
import { baseOption, legend, seriesColour } from '@/lib/echarts';
import { addDays, formatDate, formatInstant, kwh, money, pence, timezone } from '@/lib/format';
import { token } from '@/lib/theme';
import type { Granularity, MeterOption, Metric, SeriesPoint, Totals } from '@/types';

type PeriodKind = 'day' | 'week' | 'month';

interface Period {
    date: string;
    label: string;
    from: string;
    to: string;
    totals: Totals;
    days: number;
    daysWithData: number;
    costPerDay: number | null;
    energyChangePercent: number | null;
    costChangePercent: number | null;
    series: SeriesPoint[];
}

const props = defineProps<{
    meters: MeterOption[];
    selection: { meter: number; granularity: PeriodKind; finer: Granularity; periods: string[]; maxPeriods: number } | null;
    periods?: Period[];
}>();

const tz = timezone();
const metric = ref<Metric>('cost');
const isExport = computed(() => props.meters.find((m) => m.id === props.selection?.meter)?.direction === 'export');

function visit(changes: { meter?: number; granularity?: PeriodKind; periods?: string[] }): void {
    const s = props.selection!;
    const granularityChanged = changes.granularity && changes.granularity !== s.granularity;
    router.get('/compare', {
        meter: changes.meter ?? s.meter,
        granularity: changes.granularity ?? s.granularity,
        periods: granularityChanged ? [] : (changes.periods ?? s.periods),
    }, { preserveState: true, preserveScroll: true });
}

const dates = computed(() => props.periods?.map((p) => p.from) ?? []);

function setPeriod(index: number, value: string): void {
    if (!value) return;
    const next = [...dates.value];
    next[index] = value.length === 7 ? `${value}-01` : value;
    visit({ periods: next });
}

function addPeriod(): void {
    const s = props.selection!;
    const first = dates.value[0]!;
    const step = s.granularity === 'day' ? -1 : s.granularity === 'week' ? -7 : 0;
    const earlier = step !== 0 ? addDays(first, step * 1) : (() => {
        const d = new Date(`${first}T00:00:00Z`);
        d.setUTCMonth(d.getUTCMonth() - 1);
        return d.toISOString().slice(0, 10);
    })();
    visit({ periods: [earlier, ...dates.value] });
}

function removePeriod(index: number): void {
    visit({ periods: dates.value.filter((_, i) => i !== index) });
}

/** Overlay axis: position within the period (time of day, weekday, or day of month). */
const axisLabels = computed(() => {
    const s = props.selection;
    const longest = Math.max(0, ...(props.periods ?? []).map((p) => p.series.length));
    const sample = (props.periods ?? []).find((p) => p.series.length === longest)?.series ?? [];
    return sample.map((point, i) => {
        if (s?.finer === 'half_hour') return formatInstant(point.bucket, { hour: '2-digit', minute: '2-digit' }, tz);
        if (s?.granularity === 'week') return formatDate(point.bucket, { weekday: 'short' });
        return String(i + 1);
    });
});

function option(): EChartsOption {
    const base = baseOption();
    const periods = props.periods ?? [];
    const cost = metric.value === 'cost';
    const surface = token('surface-1');
    const value = (p: SeriesPoint) => (cost ? p.cost : p.kwh);

    return {
        ...base,
        grid: { ...(base.grid as object), top: 36, right: 24 },
        legend: { ...legend(), itemWidth: 16, itemHeight: 3 },
        tooltip: {
            ...(base.tooltip as object),
            trigger: 'axis',
            formatter: (params: any) => {
                const rows = params.map((p: any) => {
                    const point = periods[p.seriesIndex]?.series[p.dataIndex];
                    if (!point) return '';
                    const shown = point.kwh === null ? 'no readings' : cost ? money(point.cost) : kwh(point.kwh, true);
                    return `${p.marker}${p.seriesName}: <b>${shown}</b>`;
                });
                return `<b>${axisLabels.value[params[0].dataIndex] ?? ''}</b><br>${rows.filter(Boolean).join('<br>')}`;
            },
        },
        xAxis: { ...(base.xAxis as object), type: 'category', boundaryGap: false, data: axisLabels.value },
        yAxis: {
            ...(base.yAxis as object),
            type: 'value',
            axisLabel: { ...((base.yAxis as any).axisLabel), formatter: cost ? (v: number) => `£${v}` : (v: number) => `${v}` },
        },
        series: periods.map((p, i) => ({
            type: 'line' as const,
            name: p.label,
            data: p.series.map(value),
            smooth: false,
            showSymbol: p.series.length <= 31,
            symbolSize: 8,
            lineStyle: { width: 2, color: seriesColour(i) },
            itemStyle: { color: seriesColour(i), borderColor: surface, borderWidth: 2 },
            connectNulls: false,
        })),
    };
}

/** Partial periods (e.g. this month so far) compare fairly on their daily average. */
function isPartial(p: Period): boolean {
    return p.daysWithData < p.days;
}

function perDayChange(p: Period): number | null {
    const baseline = props.periods?.[0]?.costPerDay ?? null;
    if (baseline === null || baseline === 0 || p.costPerDay === null) return null;
    return Math.round(((p.costPerDay - baseline) / Math.abs(baseline)) * 1000) / 10;
}

const anyPartial = computed(() => (props.periods ?? []).some(isPartial));

const inputType = computed(() => (props.selection?.granularity === 'month' ? 'month' : 'date'));
const inputValue = (from: string) => (inputType.value === 'month' ? from.slice(0, 7) : from);
</script>

<template>
    <Head title="Compare" />

    <EmptyState v-if="!selection" title="Nothing to compare yet">Run a sync first to pull your meters and usage.</EmptyState>

    <div v-else class="space-y-4">
        <div class="flex flex-wrap items-end gap-3">
            <MeterSelect :meters="meters" :model-value="selection.meter" @update:model-value="(meter) => visit({ meter })" />
            <SegmentedControl
                :model-value="selection.granularity"
                :options="[{ value: 'day', label: 'Days' }, { value: 'week', label: 'Weeks' }, { value: 'month', label: 'Months' }]"
                label="Period length"
                @update:model-value="(granularity) => visit({ granularity })"
            />
            <SegmentedControl v-model="metric" :options="[{ value: 'cost', label: '£' }, { value: 'kwh', label: 'kWh' }]" label="Metric" />
        </div>

        <div class="grid gap-3" :class="periods!.length > 2 ? 'sm:grid-cols-2 lg:grid-cols-4' : 'sm:grid-cols-2'">
            <Card v-for="(period, i) in periods" :key="period.from">
                <div class="flex items-center justify-between gap-2">
                    <span class="flex items-center gap-2 text-sm font-medium">
                        <span class="inline-block h-0.5 w-4 rounded" :style="{ background: `var(--series-${i + 1})` }" aria-hidden="true" />
                        {{ period.label }}
                    </span>
                    <button v-if="periods!.length > 1" type="button" class="text-xs text-ink-3 hover:text-critical" :aria-label="`Remove ${period.label}`" @click="removePeriod(i)">✕</button>
                </div>
                <input
                    :type="inputType"
                    :value="inputValue(period.from)"
                    class="mt-2 w-full rounded-md border border-line bg-surface px-2 py-1 text-sm"
                    :aria-label="`Choose period ${i + 1}`"
                    @change="(e) => setPeriod(i, (e.target as HTMLInputElement).value)"
                />
                <p class="tabular mt-3 text-2xl font-semibold">{{ money(period.totals.cost) }}</p>
                <p class="tabular text-sm text-ink-2">{{ kwh(period.totals.kwh) }} · avg {{ pence(period.totals.avgRate) }}/kWh</p>
                <p class="tabular text-xs text-ink-3">
                    {{ money(period.costPerDay) }}/day · incl. {{ money(period.totals.standing) }} standing
                </p>
                <p v-if="isPartial(period)" class="mt-1 text-xs text-ink-3">
                    Partial: {{ period.daysWithData }} of {{ period.days }} days have data, so changes use the daily average.
                </p>
                <div v-if="i > 0" class="mt-2 flex flex-wrap gap-x-3">
                    <ChangeBadge v-if="anyPartial" :value="perDayChange(period)" label=" cost per day" :higher-is-better="isExport" />
                    <template v-else>
                        <ChangeBadge :value="period.costChangePercent" label=" cost" :higher-is-better="isExport" />
                        <ChangeBadge :value="period.energyChangePercent" label=" usage" :higher-is-better="isExport" />
                    </template>
                </div>
                <p v-else class="mt-2 text-xs text-ink-3">Baseline</p>
            </Card>
            <button
                v-if="periods!.length < selection.maxPeriods"
                type="button"
                class="rounded-xl border border-dashed border-line p-4 text-sm text-ink-3 hover:bg-surface hover:text-ink"
                @click="addPeriod"
            >
                + Add a period
            </button>
        </div>

        <Card :title="metric === 'cost' ? 'Cost overlay' : 'Usage overlay'" :subtitle="selection.finer === 'half_hour' ? 'Each half-hour, local time' : 'Each day of the period'">
            <Chart :option="option" height="360px" :label="`Overlay of ${periods!.map((p) => p.label).join(', ')}`" />
        </Card>
    </div>
</template>
