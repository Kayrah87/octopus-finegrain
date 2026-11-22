<script setup lang="ts">
import type { MeterOption } from '@/types';

defineProps<{ meters: MeterOption[] }>();
const model = defineModel<number>({ required: true });
</script>

<template>
    <label class="flex flex-col gap-1 text-xs text-ink-3">
        Meter
        <select v-model.number="model" class="rounded-md border border-line bg-surface px-2 py-1.5 text-sm text-ink">
            <option v-for="meter in meters" :key="meter.id" :value="meter.id">{{ meter.label }} · {{ meter.mpxn }}</option>
        </select>
    </label>
</template>
