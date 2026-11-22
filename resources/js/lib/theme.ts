import { readonly, ref } from 'vue';

export type ThemeChoice = 'system' | 'light' | 'dark';

const KEY = 'finegrain:theme';
const root = document.documentElement;
const media = window.matchMedia('(prefers-color-scheme: dark)');

const choice = ref<ThemeChoice>((root.dataset.theme as ThemeChoice) ?? 'system');
const isDark = ref(root.dataset.resolvedTheme === 'dark');

function apply(): void {
    const dark = choice.value === 'dark' || (choice.value === 'system' && media.matches);
    root.dataset.theme = choice.value;
    root.dataset.resolvedTheme = dark ? 'dark' : 'light';
    isDark.value = dark;
}

media.addEventListener('change', apply);

export function useTheme() {
    function cycle(): void {
        const order: ThemeChoice[] = ['system', 'light', 'dark'];
        choice.value = order[(order.indexOf(choice.value) + 1) % order.length] ?? 'system';
        try {
            localStorage.setItem(KEY, choice.value);
        } catch {
            /* storage unavailable: still applies for this visit */
        }
        apply();
    }

    return { choice: readonly(choice), isDark: readonly(isDark), cycle };
}

/** Read a CSS custom property; re-read whenever isDark changes. */
export function token(name: string): string {
    return getComputedStyle(root).getPropertyValue(`--${name}`).trim();
}
