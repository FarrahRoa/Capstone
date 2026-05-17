import { Link } from 'react-router-dom';

/**
 * Half-hour slot row: time label, status, and Book (link or disabled).
 */
export default function AvailableBookingSlotCard({
    label,
    reserveUrl,
    spaceLabel,
    bookDisabled = false,
    disabledTitle = '',
    className = '',
    /** `row` = time/status left, Book bottom-right (User Dashboard). */
    layout = 'stack',
}) {
    const isRow = layout === 'row';
    const baseCard = isRow
        ? `flex min-h-[5.25rem] w-full min-w-0 flex-row items-stretch justify-between gap-3 rounded-xl border-2 px-4 py-3 ${className}`
        : `flex min-h-[5.25rem] w-full flex-col justify-between gap-3 rounded-xl border-2 px-4 py-3 ${className}`;

    const bookBadge =
        'shrink-0 self-end rounded-md px-2.5 py-1 text-xs font-bold uppercase tracking-wide';

    if (bookDisabled) {
        return (
            <article
                title={disabledTitle || undefined}
                aria-label={`${label} — booking unavailable`}
                aria-disabled="true"
                className={`${baseCard} border-slate-200/90 bg-white opacity-90 cursor-not-allowed`}
            >
                <section className="flex min-w-0 flex-1 flex-col justify-center text-left">
                    <p className="text-sm font-semibold text-slate-900">{label}</p>
                    <p className="mt-1 text-xs font-medium text-slate-600">Available</p>
                </section>
                <span className={`${bookBadge} border border-slate-300 bg-slate-100 text-slate-500`}>
                    Book
                </span>
            </article>
        );
    }

    return (
        <Link
            to={reserveUrl}
            aria-label={`Book ${label} in ${spaceLabel}`}
            className={`group ${baseCard} border-slate-200/90 bg-white shadow-sm transition hover:border-xu-secondary hover:bg-xu-page/50 hover:shadow-md focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-xu-secondary`}
        >
            <section className="flex min-w-0 flex-1 flex-col justify-center text-left">
                <p className="text-sm font-semibold text-slate-900 group-hover:text-xu-primary">{label}</p>
                <p className="mt-1 text-xs font-medium text-slate-600">Available</p>
            </section>
            <span
                className={`${bookBadge} bg-xu-primary/10 text-xu-primary group-hover:bg-xu-primary group-hover:text-white`}
            >
                Book
            </span>
        </Link>
    );
}
