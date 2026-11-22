<script setup lang="ts" generic="T extends string">
defineProps<{ options: { value: T; label: string }[]; label: string }>();
const model = defineModel<T>({ required: true });
</script>

<template>
    <div class="inline-flex rounded-lg border border-line bg-surface-2 p-0.5 text-sm" role="radiogroup" :aria-label="label">
        <button
            v-for="option in options"
            :key="option.value"
            type="button"
            role="radio"
            :aria-checked="model === option.value"
            class="rounded-md px-3 py-1 text-ink-2"
            :class="{ 'bg-surface font-medium text-ink shadow-sm': model === option.value }"
            @click="model = option.value"
        >
            {{ option.label }}
        </button>
    </div>
</template>
