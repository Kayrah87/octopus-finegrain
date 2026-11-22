import { formatDate, formatInstant } from '@/lib/format';
import type { Granularity } from '@/types';

/** Human label for a series bucket. Day+ buckets are local dates; half-hours are UTC instants. */
export function bucketLabel(bucket: string, granularity: Granularity, tz: string, long = false): string {
    switch (granularity) {
        case 'month':
            return formatDate(bucket, { month: long ? 'long' : 'short', year: 'numeric' });
        case 'week':
            return `${long ? 'Week of ' : ''}${formatDate(bucket, { day: 'numeric', month: 'short', ...(long ? { year: 'numeric' } : {}) })}`;
        case 'day':
            return formatDate(bucket, long ? { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' } : { day: 'numeric', month: 'short' });
        case 'half_hour':
            return formatInstant(bucket, long ? { weekday: 'short', day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' } : { hour: '2-digit', minute: '2-digit' }, tz);
    }
}
