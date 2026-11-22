<script setup lang="ts">
defineProps<{ title: string }>();
</script>

<template>
    <div class="rounded-xl border border-dashed border-line bg-surface p-10 text-center">
        <p class="font-medium">{{ title }}</p>
        <p class="mt-1 text-sm text-ink-3"><slot /></p>
    </div>
</template>
