<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import type { EChartsOption } from 'echarts';
import Card from '@/components/Card.vue';
import ChangeBadge from '@/components/ChangeBadge.vue';
import Chart from '@/components/Chart.vue';
import EmptyState from '@/components/EmptyState.vue';
import LiveDemand from '@/components/LiveDemand.vue';
import { baseOption, seriesColour } from '@/lib/echarts';
import { formatDate, kwh, money, pence } from '@/lib/format';
import type { SeriesPoint, Totals } from '@/types';

interface Period {
    from: string;
    to: string;
    current: Totals;
    baseline: Totals;
    energyChangePercent: number | null;
    costChangePercent: number | null;
}

interface MeterSummary {
    id: number;
    label: string;
    fuel: string;
    direction: 'import' | 'export';
    latestDate: string | null;
    periods: { day: Period; week: Period; month: Period };
    last14Days: SeriesPoint[];
    tariff: { code: string; product: string; since: string; until: string | null; unitRate: number | null; standingCharge: number | null } | null;
}

defineProps<{ meters: MeterSummary[]; liveEnabled: boolean }>();

const periodTitles = { day: 'Latest day', week: 'Week to date', month: 'Month to date' } as const;
const shortDate = (d: string) => formatDate(d, { day: 'numeric', month: 'short' });
const range = (p: Period) => (p.from === p.to ? formatDate(p.from, { weekday: 'short', day: 'numeric', month: 'short' }) : `${shortDate(p.from)} – ${shortDate(p.to)}`);

function dailyOption(meter: MeterSummary): () => EChartsOption {
    return () => {
        const base = baseOption();
        return {
            ...base,
            grid: { left: 0, right: 0, top: 8, bottom: 0, containLabel: true },
            tooltip: {
                ...(base.tooltip as object),
                trigger: 'axis',
                axisPointer: { type: 'shadow' },
                formatter: (params: any) => {
                    const p = meter.last14Days[params[0].dataIndex]!;
                    return `<b>${formatDate(p.bucket, { weekday: 'short', day: 'numeric', month: 'short' })}</b><br>${money(p.cost)} · ${kwh(p.kwh)}`;
                },
            },
            xAxis: { ...(base.xAxis as object), type: 'category', data: meter.last14Days.map((p) => formatDate(p.bucket, { day: 'numeric' })) },
            yAxis: { ...(base.yAxis as object), type: 'value', axisLabel: { show: false }, splitNumber: 2 },
            series: [{
                type: 'bar',
                data: meter.last14Days.map((p) => p.cost),
                barMaxWidth: 18,
                itemStyle: { color: seriesColour(0), borderRadius: [4, 4, 0, 0] },
            }],
        };
    };
}
</script>

<template>
    <Head title="Overview" />

    <div class="space-y-6">
        <LiveDemand v-if="liveEnabled" />

        <EmptyState v-if="meters.length === 0" title="No data yet">
            Run a sync to pull your meters, usage and tariffs from Octopus. The first sync downloads all your history, so it can take a few minutes.
        </EmptyState>

        <section v-for="meter in meters" :key="meter.id" class="space-y-3">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h1 class="text-lg font-semibold tracking-tight">{{ meter.label }}</h1>
                <p class="text-xs text-ink-3">
                    <template v-if="meter.latestDate">Data up to {{ formatDate(meter.latestDate, { weekday: 'long', day: 'numeric', month: 'long' }) }}</template>
                    <template v-else>No readings yet</template>
                    · <Link :href="`/explore?meter=${meter.id}`" class="text-accent hover:underline">Explore</Link>
                </p>
            </div>

            <div class="grid gap-3 md:grid-cols-3">
                <Card v-for="(period, key) in meter.periods" :key="key">
                    <p class="text-xs font-medium text-ink-3">{{ periodTitles[key] }} · {{ range(period) }}</p>
                    <p class="tabular mt-2 text-3xl font-semibold tracking-tight">{{ money(period.current.cost) }}</p>
                    <p class="tabular mt-1 text-sm text-ink-2">
                        {{ kwh(period.current.kwh) }}
                        <span class="text-ink-3">· avg {{ pence(period.current.avgRate) }}/kWh</span>
                    </p>
                    <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1">
                        <ChangeBadge :value="period.costChangePercent" label=" cost" :higher-is-better="meter.direction === 'export'" />
                        <ChangeBadge :value="period.energyChangePercent" label=" usage" :higher-is-better="meter.direction === 'export'" />
                    </div>
                    <p class="mt-1 text-xs text-ink-3">vs {{ money(period.baseline.cost) }} / {{ kwh(period.baseline.kwh) }} the previous period</p>
                </Card>
            </div>

            <div class="grid gap-3 md:grid-cols-3">
                <Card class="md:col-span-2" title="Last 14 days" subtitle="Daily cost including standing charge">
                    <Chart :option="dailyOption(meter)" height="140px" :label="`Daily cost for ${meter.label} over the last 14 days`" />
                </Card>
                <Card title="Current tariff">
                    <template v-if="meter.tariff">
                        <p class="font-mono text-sm break-all">{{ meter.tariff.product }}</p>
                        <dl class="tabular mt-3 grid grid-cols-2 gap-y-2 text-sm">
                            <dt class="text-ink-3">Unit rate now</dt>
                            <dd class="text-right font-medium">{{ pence(meter.tariff.unitRate) }}/kWh</dd>
                            <dt class="text-ink-3">Standing charge</dt>
                            <dd class="text-right font-medium">{{ pence(meter.tariff.standingCharge) }}/day</dd>
                            <dt class="text-ink-3">Since</dt>
                            <dd class="text-right">{{ formatDate(meter.tariff.since.slice(0, 10), { day: 'numeric', month: 'short', year: 'numeric' }) }}</dd>
                        </dl>
                        <Link :href="`/tariffs?meter=${meter.id}`" class="mt-3 inline-block text-xs text-accent hover:underline">Rates and history</Link>
                    </template>
                    <p v-else class="text-sm text-ink-3">No active agreement.</p>
                </Card>
            </div>
        </section>
    </div>
</template>
