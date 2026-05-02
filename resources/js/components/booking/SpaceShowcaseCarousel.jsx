import { useCallback, useEffect, useId, useMemo, useState } from 'react';
import { spaceShowcaseSubtitle } from '../../utils/spaceShowcaseSubtitle';
import { ui } from '../../theme';

/** Title for slides: real space record name when API provides it (e.g. Confab 1), else booking-facing `name`. */
function showcaseSpaceTitle(space) {
    if (!space) return '';
    const r = space.record_name;
    if (r != null && String(r).trim() !== '') return String(r).trim();
    return space.name ?? '';
}

/** Meta “book Confab” pool — not a physical room; numbered Confabs are shown instead. Do not match on `name === 'Confab'` (API uses that for all confab types). */
function isGenericConfabPoolForShowcase(space) {
    if (!space) return false;
    if (space.is_confab_pool) return true;
    return String(space.slug || '') === 'confab-pool';
}

/**
 * @param {{ spaces: object[], onSpaceSelect?: (space: object) => void, className?: string, heading?: string }} props
 */
export default function SpaceShowcaseCarousel({ spaces, onSpaceSelect, className = '', heading = 'Library spaces' }) {
    const list = useMemo(
        () =>
            Array.isArray(spaces)
                ? spaces.filter(Boolean).filter((s) => !isGenericConfabPoolForShowcase(s))
                : [],
        [spaces]
    );
    const [index, setIndex] = useState(0);
    const regionId = useId();
    const labelId = `${regionId}-label`;
    const liveId = `${regionId}-live`;

    useEffect(() => {
        setIndex(0);
    }, [list]);

    useEffect(() => {
        if (list.length === 0) return undefined;
        const el = document.getElementById(regionId);
        if (!el) return undefined;
        const onKey = (e) => {
            if (e.key === 'ArrowLeft') {
                e.preventDefault();
                setIndex((i) => (i - 1 + list.length) % list.length);
            } else if (e.key === 'ArrowRight') {
                e.preventDefault();
                setIndex((i) => (i + 1) % list.length);
            } else if (e.key === 'Home') {
                e.preventDefault();
                setIndex(0);
            } else if (e.key === 'End') {
                e.preventDefault();
                setIndex(list.length - 1);
            }
        };
        el.addEventListener('keydown', onKey);
        return () => el.removeEventListener('keydown', onKey);
    }, [list.length, regionId]);

    const go = useCallback(
        (delta) => {
            if (list.length === 0) return;
            setIndex((i) => (i + delta + list.length) % list.length);
        },
        [list.length]
    );

    if (list.length === 0) {
        return null;
    }

    const space = list[index];
    const title = showcaseSpaceTitle(space);
    const subtitle = spaceShowcaseSubtitle(space);
    const imgSrc = space.image_url || '/images/library-space-placeholder.svg';
    const imgAlt = `${title} — library space`;
    const canClickSelect = typeof onSpaceSelect === 'function';

    const slideBody = (
        <>
            <div className="relative aspect-[16/10] w-full overflow-hidden bg-slate-100">
                <img src={imgSrc} alt={imgAlt} className="h-full w-full object-cover" loading={index === 0 ? 'eager' : 'lazy'} />
                <div className="pointer-events-none absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/65 via-black/25 to-transparent px-4 pb-4 pt-16 sm:px-5 sm:pb-5">
                    <p className="font-serif text-lg font-semibold text-white drop-shadow-sm sm:text-xl">{title}</p>
                    {subtitle ? (
                        <p className="mt-1 text-sm text-white/90 drop-shadow-sm line-clamp-2">{subtitle}</p>
                    ) : null}
                </div>
            </div>
        </>
    );

    return (
        <section
            id={regionId}
            className={`rounded-xl border border-slate-200/90 bg-white shadow-sm outline-none focus-visible:ring-2 focus-visible:ring-xu-secondary/40 ${className}`}
            aria-roledescription="carousel"
            aria-labelledby={labelId}
            tabIndex={0}
        >
            <div className="border-b border-slate-100 px-4 py-3 sm:px-5">
                <h2 id={labelId} className={`${ui.sectionLabel} !normal-case tracking-normal text-xu-primary`}>
                    {heading}
                </h2>
                <p className="mt-1 text-xs text-slate-600">Browse rooms with photos. Use arrow buttons or keyboard arrows to move between spaces.</p>
            </div>

            <div className="relative">
                <div id={liveId} aria-live="polite" className="sr-only">
                    {list.length > 0 ? `Slide ${index + 1} of ${list.length}: ${title}` : ''}
                </div>

                {canClickSelect ? (
                    <button
                        type="button"
                        className="block w-full text-left focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-xu-secondary"
                        onClick={() => onSpaceSelect(space)}
                        aria-label={`Select ${title} for scheduling`}
                    >
                        {slideBody}
                    </button>
                ) : (
                    <div>{slideBody}</div>
                )}

                {list.length > 1 ? (
                    <>
                        <button
                            type="button"
                            className="absolute left-2 top-1/2 z-10 flex h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full border border-slate-200/90 bg-white/95 text-xu-primary shadow-md transition hover:bg-xu-page disabled:opacity-40 sm:left-3"
                            onClick={() => go(-1)}
                            aria-label="Previous space"
                        >
                            <svg className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden="true">
                                <path strokeLinecap="round" strokeLinejoin="round" d="M15 6l-6 6 6 6" />
                            </svg>
                        </button>
                        <button
                            type="button"
                            className="absolute right-2 top-1/2 z-10 flex h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full border border-slate-200/90 bg-white/95 text-xu-primary shadow-md transition hover:bg-xu-page disabled:opacity-40 sm:right-3"
                            onClick={() => go(1)}
                            aria-label="Next space"
                        >
                            <svg className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden="true">
                                <path strokeLinecap="round" strokeLinejoin="round" d="M9 6l6 6-6 6" />
                            </svg>
                        </button>
                    </>
                ) : null}
            </div>

            {list.length > 1 ? (
                <div className="flex justify-center gap-2 px-4 py-3" role="group" aria-label="Slides">
                    {list.map((s, i) => (
                        <button
                            key={s.id}
                            type="button"
                            aria-current={i === index ? true : undefined}
                            tabIndex={-1}
                            className={`h-2.5 w-2.5 rounded-full transition ${i === index ? 'bg-xu-secondary scale-110' : 'bg-slate-300 hover:bg-slate-400'}`}
                            onClick={() => setIndex(i)}
                            aria-label={`Show space ${i + 1}: ${showcaseSpaceTitle(s)}`}
                        />
                    ))}
                </div>
            ) : null}
        </section>
    );
}
