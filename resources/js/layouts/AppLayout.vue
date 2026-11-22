<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { relativeTime } from '@/lib/format';
import { useTheme } from '@/lib/theme';
import type { SharedProps } from '@/types';

const page = usePage();
const shared = computed(() => page.props as unknown as SharedProps);
const { choice, cycle } = useTheme();
const syncing = ref(false);

const nav = [
    { href: '/', label: 'Overview', component: 'Overview' },
    { href: '/explore', label: 'Explore', component: 'Explore' },
    { href: '/compare', label: 'Compare', component: 'Compare' },
    { href: '/tariffs', label: 'Tariffs', component: 'Tariffs' },
];

const sync = computed(() => shared.value.sync);

function startSync(): void {
    router.post('/sync', {}, {
        preserveScroll: true,
        onStart: () => (syncing.value = true),
        onFinish: () => (syncing.value = false),
    });
}

const themeLabel = computed(() => ({ system: 'Theme: system', light: 'Theme: light', dark: 'Theme: dark' })[choice.value]);
</script>

<template>
    <div class="min-h-screen">
        <header class="sticky top-0 z-20 border-b border-line bg-surface/90 backdrop-blur">
            <div class="mx-auto flex max-w-7xl flex-wrap items-center gap-x-6 gap-y-2 px-4 py-3">
                <Link href="/" class="flex items-center gap-2 font-semibold tracking-tight">
                    <img src="/favicon.svg" alt="" class="size-6" />
                    Finegrain
                </Link>

                <nav class="flex gap-1 text-sm" aria-label="Main">
                    <Link
                        v-for="item in nav"
                        :key="item.href"
                        :href="item.href"
                        prefetch
                        class="rounded-md px-3 py-1.5 text-ink-2 hover:bg-surface-2 hover:text-ink"
                        :class="{ 'bg-surface-2 font-medium text-ink': page.component === item.component }"
                        :aria-current="page.component === item.component ? 'page' : undefined"
                    >
                        {{ item.label }}
                    </Link>
                </nav>

                <div class="ml-auto flex items-center gap-2 text-sm">
                    <button
                        type="button"
                        class="rounded-md border border-line px-3 py-1.5 text-ink-2 hover:bg-surface-2"
                        :title="themeLabel"
                        :aria-label="themeLabel"
                        @click="cycle"
                    >
                        {{ choice === 'dark' ? '☾' : choice === 'light' ? '☀' : '◐' }}
                    </button>
                    <button
                        type="button"
                        class="rounded-md bg-accent px-3 py-1.5 font-medium text-accent-ink hover:opacity-90 disabled:opacity-60"
                        :disabled="syncing || !shared.app.configured"
                        @click="startSync"
                    >
                        {{ syncing ? 'Queuing…' : 'Sync now' }}
                    </button>
                    <Link
                        v-if="shared.app.loginEnabled"
                        href="/logout"
                        method="post"
                        as="button"
                        class="rounded-md px-3 py-1.5 text-ink-2 hover:bg-surface-2"
                    >
                        Log out
                    </Link>
                </div>
            </div>
        </header>

        <div v-if="!shared.app.configured" class="border-b border-line bg-surface-2">
            <p class="mx-auto max-w-7xl px-4 py-2 text-sm text-ink-2">
                Set <code class="font-mono">OCTOPUS_API_KEY</code> and
                <code class="font-mono">OCTOPUS_ACCOUNT_NUMBER</code> to start syncing your data.
            </p>
        </div>

        <div v-if="shared.flash.status" class="border-b border-line bg-surface-2" role="status">
            <p class="mx-auto max-w-7xl px-4 py-2 text-sm text-ink-2">{{ shared.flash.status }}</p>
        </div>

        <main class="mx-auto max-w-7xl px-4 py-6">
            <slot />
        </main>

        <footer v-if="sync" class="mx-auto max-w-7xl px-4 pb-8 text-xs text-ink-3">
            Last sync {{ relativeTime(sync.startedAt) }}
            <template v-if="sync.succeeded === false"> · <span class="text-critical">failed: {{ sync.summary }}</span></template>
            <template v-else-if="sync.summary"> · {{ sync.summary }}</template>
        </footer>
    </div>
</template>
