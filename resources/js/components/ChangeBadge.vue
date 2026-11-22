<script setup lang="ts">
import { computed } from 'vue';
import { percent } from '@/lib/format';

const props = defineProps<{ value: number | null | undefined; label?: string; higherIsBetter?: boolean }>();

/** For spend and usage, down is good (export earnings: up is good). Arrow + sign carry the meaning, not colour alone. */
const tone = computed(() => {
    if (props.value === null || props.value === undefined || props.value === 0) return 'text-ink-3';
    const better = props.higherIsBetter ? props.value > 0 : props.value < 0;
    return better ? 'text-good' : 'text-critical';
});
const arrow = computed(() => (!props.value ? '' : props.value < 0 ? '▼' : '▲'));
</script>

<template>
    <span class="tabular inline-flex items-center gap-1 text-xs font-medium" :class="tone">
        <span aria-hidden="true">{{ arrow }}</span>{{ percent(value) }}<span v-if="label" class="font-normal text-ink-3">{{ label }}</span>
    </span>
</template>
