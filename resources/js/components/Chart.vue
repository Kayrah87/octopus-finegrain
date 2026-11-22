<script setup lang="ts">
import type { EChartsOption } from 'echarts';
import { computed } from 'vue';
import VChart from 'vue-echarts';
import '@/lib/echarts';
import { useTheme } from '@/lib/theme';

const props = defineProps<{ option: () => EChartsOption; height?: string; label: string }>();
const emit = defineEmits<{ click: [params: { dataIndex: number; data: unknown }] }>();
const { isDark } = useTheme();

/** Rebuild the option when the theme flips so token colours are re-read. */
const resolved = computed(() => {
    void isDark.value;
    return props.option();
});
</script>

<template>
    <div :style="{ height: height ?? '320px' }" role="img" :aria-label="label">
        <VChart :option="resolved" autoresize :update-options="{ notMerge: true }" @click="(p: any) => emit('click', p)" />
    </div>
</template>
