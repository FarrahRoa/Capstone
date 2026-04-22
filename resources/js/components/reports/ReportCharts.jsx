import { BOOKING_TIMEZONE } from '../../utils/timeDisplay';

function peakHourAxisLabel(hour) {
    const inst = new Date(`2000-01-01T${String(hour).padStart(2, '0')}:00:00+08:00`);
    return new Intl.DateTimeFormat('en-US', {
        timeZone: BOOKING_TIMEZONE,
        hour: 'numeric',
        minute: '2-digit',
        hour12: true,
    }).format(inst);
}

/**
 * @param {Record<string, number>|null|undefined} obj
 * @returns {{ label: string, value: number }[]}
 */
export function bucketsFromRecord(obj) {
    if (!obj || typeof obj !== 'object' || Array.isArray(obj)) return [];
    return Object.entries(obj)
        .map(([label, v]) => ({ label: String(label), value: Number(v) || 0 }))
        .filter((x) => x.value > 0)
        .sort((a, b) => b.value - a.value || a.label.localeCompare(b.label));
}

/**
 * Full 0–23 series for time-axis charts (includes zeros).
 * @param {Record<string, number>|null|undefined} obj
 * @returns {{ label: string, value: number, hour: number }[]}
 */
export function peakHoursFullSeries(obj) {
    const out = [];
    for (let h = 0; h < 24; h += 1) {
        const key = String(h).padStart(2, '0');
        const raw =
            obj && typeof obj === 'object' && !Array.isArray(obj)
                ? obj[key] ?? obj[h] ?? obj[String(h)]
                : undefined;
        out.push({
            hour: h,
            value: Number(raw) || 0,
            label: peakHourAxisLabel(h),
        });
    }
    return out;
}

export function peakHoursSeriesIsEmpty(series) {
    if (!series?.length) return true;
    return series.every((p) => !p.value);
}

/**
 * @param {unknown} arr
 * @returns {{ label: string, value: number }[]}
 */
export function itemsFromRoomUtilization(arr) {
    if (!Array.isArray(arr)) return [];
    return arr
        .map((r) => ({
            label: String(r?.space_name ?? 'Unknown'),
            value: Number(r?.count) || 0,
        }))
        .filter((x) => x.value > 0)
        .sort((a, b) => b.value - a.value || a.label.localeCompare(b.label));
}

const DONUT_COLORS = ['#283971', '#3a52a3', '#b99430', '#4a6bc9', '#1e2d5c', '#5c6ba8', '#8a7340', '#6b7db3'];

export function ChartBlock({ title, subtitle, empty, children }) {
    return (
        <section>
            <h2 className="font-semibold text-xu-primary font-serif mb-1">{title}</h2>
            {subtitle && <p className="text-xs text-slate-500 mb-3">{subtitle}</p>}
            {empty ? (
                <div className="rounded-lg border border-dashed border-slate-200 bg-slate-50/90 px-4 py-10 text-center text-sm text-slate-500">
                    No data available for this period.
                </div>
            ) : (
                <div className="min-w-0 overflow-x-auto rounded-lg border border-slate-200/80 bg-xu-page/30 p-4 shadow-inner [scrollbar-width:thin]">
                    {children}
                </div>
            )}
        </section>
    );
}

/** Long category names (or time labels): label | bar | count */
export function HorizontalBarChart({
    items,
    variant = 'primary',
    compact = false,
    labelClassName = '',
    labelColClassName = '',
}) {
    const max = Math.max(1, ...items.map((i) => i.value));
    const barClass =
        variant === 'secondary'
            ? 'bg-gradient-to-r from-xu-secondary to-xu-secondary/85'
            : 'bg-gradient-to-r from-xu-primary to-xu-primary/85';
    const gapY = compact ? 'space-y-1.5' : 'space-y-3';
    const barH = compact ? 'h-5' : 'h-7';
    const countW = compact ? 'w-8' : 'w-9';
    const labelCol = labelColClassName || 'w-[min(40%,14rem)]';
    return (
        <ul className={gapY}>
            {items.map((item, idx) => {
                const pct = (item.value / max) * 100;
                return (
                    <li key={`${idx}-${item.label}`} className="flex items-center gap-2 sm:gap-3 text-sm min-w-0">
                        <span
                            className={`${labelCol} shrink-0 truncate text-slate-700 font-medium ${labelClassName}`}
                            title={item.label}
                        >
                            {item.label}
                        </span>
                        <div className={`flex-1 ${barH} bg-slate-100 rounded-md overflow-hidden min-w-0`}>
                            <div
                                className={`h-full rounded-md ${barClass}`}
                                style={{ width: `${pct}%`, minWidth: item.value > 0 ? '4px' : 0 }}
                            />
                        </div>
                        <span className={`${countW} shrink-0 text-right tabular-nums text-xu-primary font-semibold`}>
                            {item.value}
                        </span>
                    </li>
                );
            })}
        </ul>
    );
}

/** Short category labels: classic column chart (student-by-college). */
export function CategoryColumnChart({ items }) {
    const max = Math.max(1, ...items.map((i) => i.value));
    return (
        <div className="overflow-x-auto pb-1 [scrollbar-width:thin]">
            <div className="flex items-end gap-2 min-h-[11rem] h-44 px-1 border-b border-slate-200">
                {items.map((item, idx) => {
                    const hPct = Math.max(8, (item.value / max) * 100);
                    return (
                        <div
                            key={`${idx}-${item.label}`}
                            className="flex flex-col items-center justify-end h-full min-w-[3.25rem] max-w-[7rem] flex-1"
                        >
                            <span className="text-[11px] font-semibold tabular-nums text-xu-secondary mb-1">{item.value}</span>
                            <div
                                className="w-full max-w-[3rem] mx-auto rounded-t-md bg-xu-secondary/90 shadow-sm"
                                style={{ height: `${hPct}%` }}
                                title={`${item.label}: ${item.value}`}
                            />
                            <span
                                className="mt-2 text-[10px] leading-tight text-slate-600 text-center break-words w-full px-0.5 max-h-[3.5rem] overflow-hidden"
                                title={item.label}
                            >
                                {item.label}
                            </span>
                        </div>
                    );
                })}
            </div>
        </div>
    );
}

/** Distribution: donut + legend (use when few slices). */
export function DonutChart({ items }) {
    const total = items.reduce((s, i) => s + i.value, 0);
    const cx = 88;
    const cy = 88;
    const r = 62;
    const holeR = 34;
    let angle = -90;

    const polar = (deg) => {
        const rad = ((deg - 90) * Math.PI) / 180;
        return [cx + r * Math.cos(rad), cy + r * Math.sin(rad)];
    };

    if (items.length === 1) {
        const item = items[0];
        const color = DONUT_COLORS[0];
        return (
            <div className="flex flex-col sm:flex-row items-center gap-6">
                <div className="relative shrink-0">
                    <svg viewBox="0 0 176 176" className="w-44 h-44" aria-hidden>
                        <circle cx={cx} cy={cy} r={r} fill={color} />
                        <circle cx={cx} cy={cy} r={holeR} fill="var(--color-xu-page, #f4f6fb)" />
                    </svg>
                    <div className="absolute inset-0 flex items-center justify-center pointer-events-none">
                        <div className="text-center pt-1">
                            <div className="text-[10px] uppercase tracking-wide text-slate-500">Total</div>
                            <div className="text-lg font-serif font-bold text-xu-primary tabular-nums">{total}</div>
                        </div>
                    </div>
                </div>
                <ul className="flex-1 min-w-0 space-y-2 text-sm w-full max-w-md">
                    <li className="flex items-center gap-2 min-w-0">
                        <span className="w-2.5 h-2.5 rounded-sm shrink-0" style={{ backgroundColor: color }} />
                        <span className="truncate flex-1 text-slate-700" title={item.label}>
                            {item.label}
                        </span>
                        <span className="tabular-nums font-semibold text-xu-primary">{item.value}</span>
                        <span className="text-xs text-slate-500 w-12 text-right">100%</span>
                    </li>
                </ul>
            </div>
        );
    }

    const slices = items.map((item, idx) => {
        const sweep = (item.value / total) * 360;
        const start = angle;
        const end = angle + sweep;
        angle = end;
        const [x1, y1] = polar(start);
        const [x2, y2] = polar(end);
        const largeArc = sweep > 180 ? 1 : 0;
        const d = `M ${cx} ${cy} L ${x1} ${y1} A ${r} ${r} 0 ${largeArc} 1 ${x2} ${y2} Z`;
        return { ...item, d, color: DONUT_COLORS[idx % DONUT_COLORS.length], sweep, pct: (item.value / total) * 100 };
    });

    return (
        <div className="flex flex-col sm:flex-row items-center gap-6">
            <div className="relative shrink-0">
                <svg viewBox="0 0 176 176" className="w-44 h-44" aria-hidden>
                    {slices.map((s, idx) => (
                        <path key={`${idx}-${s.label}`} d={s.d} fill={s.color} stroke="#fff" strokeWidth="1" />
                    ))}
                    <circle cx={cx} cy={cy} r={34} fill="var(--color-xu-page, #f4f6fb)" />
                </svg>
                <div className="absolute inset-0 flex items-center justify-center pointer-events-none">
                    <div className="text-center pt-1">
                        <div className="text-[10px] uppercase tracking-wide text-slate-500">Total</div>
                        <div className="text-lg font-serif font-bold text-xu-primary tabular-nums">{total}</div>
                    </div>
                </div>
            </div>
            <ul className="flex-1 min-w-0 space-y-2 text-sm w-full max-w-md">
                {slices.map((s, idx) => (
                    <li key={`${idx}-leg-${s.label}`} className="flex items-center gap-2 min-w-0">
                        <span className="w-2.5 h-2.5 rounded-sm shrink-0" style={{ backgroundColor: s.color }} />
                        <span className="truncate flex-1 text-slate-700" title={s.label}>
                            {s.label}
                        </span>
                        <span className="tabular-nums font-semibold text-xu-primary">{s.value}</span>
                        <span className="text-xs text-slate-500 w-12 text-right">{s.pct < 10 ? s.pct.toFixed(1) : Math.round(s.pct)}%</span>
                    </li>
                ))}
            </ul>
        </div>
    );
}

