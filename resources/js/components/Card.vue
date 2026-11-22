<script setup lang="ts">
defineProps<{ title?: string; subtitle?: string }>();
</script>

<template>
    <section class="rounded-xl border border-line bg-surface p-4 sm:p-5">
        <header v-if="title || $slots.actions" class="mb-3 flex flex-wrap items-start justify-between gap-2">
            <div>
                <h2 v-if="title" class="text-sm font-semibold">{{ title }}</h2>
                <p v-if="subtitle" class="text-xs text-ink-3">{{ subtitle }}</p>
            </div>
            <slot name="actions" />
        </header>
        <slot />
    </section>
</template>
