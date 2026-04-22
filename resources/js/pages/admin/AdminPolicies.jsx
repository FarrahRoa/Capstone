import { useEffect, useState } from 'react';
import api from '../../api';
import { unwrapData } from '../../utils/apiEnvelope';
import { ui } from '../../theme';
import { formatLogTime } from '../../utils/timeDisplay';
import { SPACE_GUIDELINE_COUNT_FIELDS } from '../../utils/spaceGuidelineDisplay';

/** @type {readonly string[]} */
export const INTERNET_OPTION_CHOICES = ['LAN Cable', 'School Wifi', 'Boardroom Wifi', 'None'];

function emptySpaceGuidelineForm() {
    return {
        location: '',
        seating_capacity_note: '',
        whiteboard_count: '',
        projector_count: '',
        computer_count: '',
        dvd_player_count: '',
        sound_system_count: '',
        internet_options: [],
        others: '',
    };
}

/**
 * @param {unknown} guidelineDetails
 */
function detailsFromApi(guidelineDetails) {
    const base = emptySpaceGuidelineForm();
    const g = guidelineDetails && typeof guidelineDetails === 'object' ? guidelineDetails : {};
    const merged = { ...base, ...g };
    ['internet', 'whiteboard', 'projector', 'computer', 'dvd_player', 'sound_system'].forEach((k) => {
        delete merged[k];
    });
    SPACE_GUIDELINE_COUNT_FIELDS.forEach(({ key }) => {
        const v = merged[key];
        if (v === undefined || v === null) {
            merged[key] = '';
        } else {
            merged[key] = String(v);
        }
    });
    merged.internet_options = Array.isArray(g.internet_options) ? [...g.internet_options] : [];
    return merged;
}

/**
 * @param {string[]} selected
 * @param {string} option
 */
export function toggleInternetOption(selected, option) {
    const cur = Array.isArray(selected) ? [...selected] : [];
    if (option === 'None') {
        return cur.includes('None') ? [] : ['None'];
    }
    const withoutNone = cur.filter((x) => x !== 'None');
    if (withoutNone.includes(option)) {
        return withoutNone.filter((x) => x !== option);
    }
    return [...withoutNone, option];
}

export default function AdminPolicies() {
    const [content, setContent] = useState('');
    const [confabGuidelinesContent, setConfabGuidelinesContent] = useState('');
    const [updatedAt, setUpdatedAt] = useState('');
    const [spaceRows, setSpaceRows] = useState([]);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [banner, setBanner] = useState(null);

    const load = () => {
        setLoading(true);
        setBanner(null);
        api.get('/admin/policies/reservation-guidelines')
            .then(({ data }) => {
                const doc = unwrapData(data);
                setContent(doc?.content ?? '');
                setConfabGuidelinesContent(doc?.confab_guidelines_content ?? '');
                setUpdatedAt(doc?.updated_at || '');
                const spaces = Array.isArray(doc?.spaces) ? doc.spaces : [];
                setSpaceRows(
                    spaces.map((s) => ({
                        id: s.id,
                        name: s.name,
                        slug: s.slug,
                        capacity: s.capacity,
                        details: detailsFromApi(s.guideline_details),
                    })),
                );
            })
            .catch((err) => {
                setBanner({
                    type: 'error',
                    text: err.response?.data?.message || 'Failed to load guidelines.',
                });
            })
            .finally(() => setLoading(false));
    };

    useEffect(() => {
        load();
    }, []);

    const patchSpaceDetails = (id, patch) => {
        setSpaceRows((rows) =>
            rows.map((r) => (r.id === id ? { ...r, details: { ...r.details, ...patch } } : r)),
        );
    };

    const save = async (e) => {
        e.preventDefault();
        setSaving(true);
        setBanner(null);
        try {
            const { data } = await api.put('/admin/policies/reservation-guidelines', {
                content,
                confab_guidelines_content: confabGuidelinesContent,
                space_guidelines: spaceRows.map((r) => ({
                    space_id: r.id,
                    details: r.details,
                })),
            });
            const doc = unwrapData(data);
            setContent(doc?.content ?? content);
            setConfabGuidelinesContent(doc?.confab_guidelines_content ?? confabGuidelinesContent);
            setUpdatedAt(doc?.updated_at || '');
            const spaces = Array.isArray(doc?.spaces) ? doc.spaces : [];
            setSpaceRows(
                spaces.map((s) => ({
                    id: s.id,
                    name: s.name,
                    slug: s.slug,
                    capacity: s.capacity,
                    details: detailsFromApi(s.guideline_details),
                })),
            );
            setBanner({ type: 'success', text: data.message || 'Saved.' });
        } catch (err) {
            const msg = err.response?.data?.message;
            const field = err.response?.data?.errors?.content?.[0];
            const errors = err.response?.data?.errors;
            let firstDetailErr = '';
            if (errors && typeof errors === 'object') {
                const key = Object.keys(errors).find((k) => k.includes('space_guidelines') && k.includes('details'));
                if (key && Array.isArray(errors[key])) {
                    firstDetailErr = errors[key][0];
                }
            }
            setBanner({
                type: 'error',
                text: field || firstDetailErr || msg || 'Could not save guidelines.',
            });
        } finally {
            setSaving(false);
        }
    };

    return (
        <div className="min-w-0">
            <h1 className={`${ui.pageTitle} mb-2`}>Reservation guidelines</h1>
            <p className="text-sm text-slate-600 mb-4">
                General text is shown on the New reservation page for all users. For regular rooms, after a space is
                selected the same page shows that room&apos;s details (location, equipment counts, internet access,
                notes). For the shared <span className="font-medium text-slate-800">Confab</span> request type, users see
                general Confab guidance plus a comparison of every numbered Confab room (from each room&apos;s details
                below)—staff still assign the specific room at approval. Line breaks are preserved in guideline text and
                in Other notes.
            </p>

            {banner && (
                <div
                    className={`mb-4 text-sm p-3 rounded border ${
                        banner.type === 'success'
                            ? 'text-green-800 bg-green-50 border-green-200'
                            : 'text-red-700 bg-red-50 border-red-200'
                    }`}
                >
                    {banner.text}
                </div>
            )}

            {loading && <p className="text-slate-600">Loading…</p>}

            {!loading && (
                <form onSubmit={save} className="max-w-3xl min-w-0 space-y-8">
                    {updatedAt && (
                        <p className="text-xs text-slate-500">Last updated: {formatLogTime(updatedAt)}</p>
                    )}

                    <section className="space-y-3">
                        <h2 className="text-sm font-semibold text-xu-primary">General guidelines</h2>
                        <div>
                            <label htmlFor="policy-content" className="block text-sm font-medium text-slate-700 mb-1">
                                Content
                            </label>
                            <textarea
                                id="policy-content"
                                value={content}
                                onChange={(e) => setContent(e.target.value)}
                                rows={14}
                                disabled={saving}
                                className="w-full rounded-lg border border-slate-200 px-3 py-2 font-mono text-sm focus:ring-2 focus:ring-xu-secondary/35 focus:border-xu-secondary disabled:opacity-60"
                            />
                        </div>
                    </section>

                    <section className="space-y-3">
                        <h2 className="text-sm font-semibold text-xu-primary">General Confab guidelines (pool requests)</h2>
                        <p className="text-xs text-slate-500">
                            Shown only when a user selects the shared Confab booking (not a specific numbered room). Use
                            this for policies that apply before staff assign Confab 1, Confab 2, etc.
                        </p>
                        <div>
                            <label htmlFor="confab-policy-content" className="block text-sm font-medium text-slate-700 mb-1">
                                Content
                            </label>
                            <textarea
                                id="confab-policy-content"
                                value={confabGuidelinesContent}
                                onChange={(e) => setConfabGuidelinesContent(e.target.value)}
                                rows={10}
                                disabled={saving}
                                className="w-full rounded-lg border border-slate-200 px-3 py-2 font-mono text-sm focus:ring-2 focus:ring-xu-secondary/35 focus:border-xu-secondary disabled:opacity-60"
                            />
                        </div>
                    </section>

                    <section className="space-y-3">
                        <h2 className="text-sm font-semibold text-xu-primary">Per-space details</h2>
                        <p className="text-xs text-slate-500">
                            Tied to each space record (id / slug). Seating capacity number comes from the space record;
                            use the note field only for extra context. Equipment fields use quantities (0 or more). Internet:
                            check all that apply; &ldquo;None&rdquo; cannot be combined with other options.
                        </p>
                        <div className="space-y-3">
                            {spaceRows.map((row) => (
                                <details
                                    key={row.id}
                                    className="rounded-lg border border-slate-200/90 bg-white p-3 shadow-sm open:shadow"
                                >
                                    <summary className="cursor-pointer text-sm font-medium text-slate-800">
                                        {row.name}{' '}
                                        <span className="text-slate-400 font-normal">({row.slug})</span>
                                    </summary>
                                    <div className="mt-3 space-y-3 border-t border-slate-100 pt-3">
                                        <div>
                                            <label
                                                className="block text-xs font-medium text-slate-600 mb-1"
                                                htmlFor={`loc-${row.id}`}
                                            >
                                                Location
                                            </label>
                                            <input
                                                id={`loc-${row.id}`}
                                                type="text"
                                                value={row.details.location}
                                                onChange={(e) => patchSpaceDetails(row.id, { location: e.target.value })}
                                                disabled={saving}
                                                className={`w-full ${ui.input}`}
                                                placeholder="e.g. 2F Learning Commons"
                                            />
                                        </div>
                                        <div>
                                            <label
                                                className="block text-xs font-medium text-slate-600 mb-1"
                                                htmlFor={`seat-note-${row.id}`}
                                            >
                                                Seating capacity note (optional)
                                            </label>
                                            <input
                                                id={`seat-note-${row.id}`}
                                                type="text"
                                                value={row.details.seating_capacity_note}
                                                onChange={(e) =>
                                                    patchSpaceDetails(row.id, { seating_capacity_note: e.target.value })
                                                }
                                                disabled={saving}
                                                className={`w-full ${ui.input}`}
                                                placeholder={`Numeric capacity is ${row.capacity ?? '—'} from Spaces`}
                                            />
                                        </div>

                                        <fieldset className="rounded-lg border border-slate-200/80 p-3">
                                            <legend className="px-1 text-xs font-semibold text-slate-700">
                                                Internet access
                                            </legend>
                                            <p className="text-[11px] text-slate-500 mb-2">
                                                Select all that apply. Choosing &ldquo;None&rdquo; clears other options.
                                            </p>
                                            <div className="flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                                                {INTERNET_OPTION_CHOICES.map((opt) => (
                                                    <label
                                                        key={opt}
                                                        className="inline-flex cursor-pointer items-center gap-2 text-sm text-slate-700"
                                                    >
                                                        <input
                                                            type="checkbox"
                                                            checked={row.details.internet_options.includes(opt)}
                                                            onChange={() =>
                                                                patchSpaceDetails(row.id, {
                                                                    internet_options: toggleInternetOption(
                                                                        row.details.internet_options,
                                                                        opt,
                                                                    ),
                                                                })
                                                            }
                                                            disabled={saving}
                                                            className="rounded border-slate-300 text-xu-primary focus:ring-xu-secondary/40"
                                                        />
                                                        <span>{opt}</span>
                                                    </label>
                                                ))}
                                            </div>
                                        </fieldset>

                                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                            {SPACE_GUIDELINE_COUNT_FIELDS.map(({ key, label }) => (
                                                <div key={key}>
                                                    <label
                                                        className="block text-xs font-medium text-slate-600 mb-1"
                                                        htmlFor={`${key}-${row.id}`}
                                                    >
                                                        {label} (quantity)
                                                    </label>
                                                    <input
                                                        id={`${key}-${row.id}`}
                                                        type="number"
                                                        min={0}
                                                        max={99}
                                                        step={1}
                                                        value={row.details[key]}
                                                        onChange={(e) => patchSpaceDetails(row.id, { [key]: e.target.value })}
                                                        disabled={saving}
                                                        className={`w-full ${ui.input}`}
                                                        placeholder="0"
                                                    />
                                                </div>
                                            ))}
                                        </div>

                                        <div>
                                            <label
                                                className="block text-xs font-medium text-slate-600 mb-1"
                                                htmlFor={`others-${row.id}`}
                                            >
                                                Other notes
                                            </label>
                                            <textarea
                                                id={`others-${row.id}`}
                                                value={row.details.others}
                                                onChange={(e) => patchSpaceDetails(row.id, { others: e.target.value })}
                                                disabled={saving}
                                                rows={3}
                                                className={`w-full ${ui.input}`}
                                                placeholder="Policies, setup, or restrictions for this room only"
                                            />
                                        </div>
                                    </div>
                                </details>
                            ))}
                        </div>
                    </section>

                    <button type="submit" disabled={saving} className={ui.btnPrimary}>
                        {saving ? 'Saving…' : 'Save guidelines'}
                    </button>
                </form>
            )}
        </div>
    );
}
