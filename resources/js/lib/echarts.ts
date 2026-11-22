import { BarChart, HeatmapChart, LineChart } from 'echarts/charts';
import {
    DataZoomComponent,
    GridComponent,
    LegendComponent,
    MarkLineComponent,
    TooltipComponent,
    VisualMapComponent,
} from 'echarts/components';
import { use } from 'echarts/core';
import type { EChartsOption } from 'echarts';
import { SVGRenderer } from 'echarts/renderers';
import { token } from '@/lib/theme';

use([
    BarChart,
    LineChart,
    HeatmapChart,
    GridComponent,
    TooltipComponent,
    LegendComponent,
    DataZoomComponent,
    VisualMapComponent,
    MarkLineComponent,
    SVGRenderer,
]);

export const SERIES_COUNT = 4;

const CHART_FONT = "ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif";

export function seriesColour(index: number): string {
    return token(`series-${(index % SERIES_COUNT) + 1}`);
}

/** A legend row that sits above the plot, left-aligned so long names never collide. */
export function legend(): EChartsOption['legend'] {
    return { top: 0, left: 0, itemGap: 16, itemWidth: 12, itemHeight: 8, textStyle: { color: token('text-secondary'), fontSize: 12 } };
}

/** Shared, recessive chart chrome built from the current theme tokens. */
export function baseOption(): EChartsOption {
    const muted = token('text-muted');
    const secondary = token('text-secondary');
    const grid = token('grid');

    return {
        backgroundColor: 'transparent',
        animationDuration: 300,
        textStyle: { fontFamily: CHART_FONT, color: secondary },
        grid: { left: 8, right: 16, top: 24, bottom: 8, containLabel: true },
        tooltip: {
            backgroundColor: token('surface-1'),
            borderColor: token('line'),
            textStyle: { color: token('text-primary'), fontSize: 12 },
            extraCssText: 'box-shadow: 0 4px 16px rgb(0 0 0 / 0.12); border-radius: 8px;',
        },
        xAxis: {
            axisLine: { lineStyle: { color: grid } },
            axisTick: { show: false },
            axisLabel: { color: muted, fontSize: 11 },
            splitLine: { show: false },
        },
        yAxis: {
            axisLine: { show: false },
            axisTick: { show: false },
            axisLabel: { color: muted, fontSize: 11 },
            splitLine: { lineStyle: { color: grid } },
        },
    };
}
