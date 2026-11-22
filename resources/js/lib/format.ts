import { usePage } from '@inertiajs/vue3';
import type { SharedProps } from '@/types';

const gbp = new Intl.NumberFormat('en-GB', { style: 'currency', currency: 'GBP' });
const kwhFormat = new Intl.NumberFormat('en-GB', { maximumFractionDigits: 1 });
const kwhFine = new Intl.NumberFormat('en-GB', { maximumFractionDigits: 3 });
const pct = new Intl.NumberFormat('en-GB', { maximumFractionDigits: 1, signDisplay: 'exceptZero' });

export const money = (pounds: number | null): string => (pounds === null ? '—' : gbp.format(pounds));
export const kwh = (value: number | null, fine = false): string =>
    value === null ? '—' : `${(fine ? kwhFine : kwhFormat).format(value)} kWh`;
export const pence = (value: number | null | undefined): string =>
    value === null || value === undefined ? '—' : `${value.toFixed(2)}p`;
export const percent = (value: number | null | undefined): string =>
    value === null || value === undefined ? '—' : `${pct.format(value)}%`;

export function timezone(): string {
    return (usePage().props as unknown as SharedProps).app.timezone;
}

/** Local-date strings (Y-m-d) are calendar dates: format them without shifting zones. */
export function formatDate(ymd: string, options: Intl.DateTimeFormatOptions): string {
    return new Intl.DateTimeFormat('en-GB', { ...options, timeZone: 'UTC' }).format(new Date(`${ymd}T00:00:00Z`));
}

export function formatInstant(iso: string, options: Intl.DateTimeFormatOptions, tz: string): string {
    return new Intl.DateTimeFormat('en-GB', { ...options, timeZone: tz }).format(new Date(iso));
}

export function relativeTime(iso: string): string {
    const seconds = Math.round((new Date(iso).getTime() - Date.now()) / 1000);
    const rtf = new Intl.RelativeTimeFormat('en-GB', { numeric: 'auto' });
    const units: [Intl.RelativeTimeFormatUnit, number][] = [
        ['day', 86400],
        ['hour', 3600],
        ['minute', 60],
    ];
    for (const [unit, size] of units) {
        if (Math.abs(seconds) >= size) {
            return rtf.format(Math.round(seconds / size), unit);
        }
    }
    return 'just now';
}

/** Add days to a Y-m-d date string. */
export function addDays(ymd: string, days: number): string {
    const d = new Date(`${ymd}T00:00:00Z`);
    d.setUTCDate(d.getUTCDate() + days);
    return d.toISOString().slice(0, 10);
}

export function endOfMonth(ymd: string): string {
    const d = new Date(`${ymd}T00:00:00Z`);
    return new Date(Date.UTC(d.getUTCFullYear(), d.getUTCMonth() + 1, 0)).toISOString().slice(0, 10);
}

export function slotLabel(slot: number): string {
    return `${String(Math.floor(slot / 2)).padStart(2, '0')}:${slot % 2 ? '30' : '00'}`;
}
