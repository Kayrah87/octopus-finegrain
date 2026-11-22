export type Granularity = 'half_hour' | 'day' | 'week' | 'month';
export type Metric = 'kwh' | 'cost';

export interface MeterOption {
    id: number;
    label: string;
    fuel: 'electricity' | 'gas';
    direction: 'import' | 'export';
    mpxn: string;
}

/** Values are null when the bucket has no readings (e.g. not yet published). */
export interface SeriesPoint {
    bucket: string;
    kwh: number | null;
    cost: number | null;
    unitCost: number | null;
    standing: number | null;
    /** Running totals (incl. standing charges) as at the end of the bucket; Explore only. */
    cumulative?: Partial<Record<CumulativePeriod, { kwh: number; cost: number }>> | null;
}

export type CumulativePeriod = 'day' | 'week' | 'month' | 'year';

export interface Totals {
    kwh: number;
    cost: number;
    unitCost: number;
    standing: number;
    avgRate: number | null;
}

export interface SyncStatus {
    startedAt: string;
    finishedAt: string | null;
    succeeded: boolean | null;
    summary: string | null;
}

export interface SharedProps {
    app: {
        name: string;
        timezone: string;
        loginEnabled: boolean;
        configured: boolean;
    };
    flash: { status: string | null };
    sync: SyncStatus | null;
    [key: string]: unknown;
}
