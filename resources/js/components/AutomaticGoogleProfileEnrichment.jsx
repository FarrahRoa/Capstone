import { useCallback, useEffect, useRef, useState } from 'react';
import api from '../api';
import { useAuth } from '../contexts/AuthContext';

const clientId = import.meta.env.VITE_GOOGLE_CLIENT_ID || '';

function loadGsiScript() {
    if (window.google?.accounts?.id) {
        return Promise.resolve();
    }
    return new Promise((resolve, reject) => {
        let script = document.querySelector('script[data-xu-google-gsi]');
        if (!script) {
            script = document.createElement('script');
            script.src = 'https://accounts.google.com/gsi/client';
            script.async = true;
            script.defer = true;
            script.dataset.xuGoogleGsi = '1';
            document.body.appendChild(script);
        }
        if (window.google?.accounts?.id) {
            resolve();
            return;
        }
        const onLoad = () => {
            script.removeEventListener('load', onLoad);
            script.removeEventListener('error', onError);
            if (window.google?.accounts?.id) resolve();
            else reject(new Error('Google GSI not available'));
        };
        const onError = () => {
            script.removeEventListener('load', onLoad);
            script.removeEventListener('error', onError);
            reject(new Error('Google GSI script failed to load'));
        };
        script.addEventListener('load', onLoad);
        script.addEventListener('error', onError);
    });
}

/**
 * When display name is still the server fallback ("XU User"), automatically triggers Google Identity
 * Services One Tap (prompt). OpenID id_token only — no Gmail scopes. Backend verifies JWT and matches email.
 * First-time users may still need to interact with Google's own One Tap UI (consent/account picker); we do not
 * ask for a manual name field.
 */
export default function AutomaticGoogleProfileEnrichment() {
    const { user, refreshUser } = useAuth();
    const userId = user?.id;
    const needsEnrichment = Boolean(user?.needs_profile_enrichment);
    const missingViteClientId = Boolean(userId && needsEnrichment && !clientId);
    const shouldRun = Boolean(clientId && needsEnrichment && userId);

    const [phase, setPhase] = useState('idle');
    const callbackRef = useRef(null);

    useEffect(() => {
        if (import.meta.env.DEV && missingViteClientId) {
            // eslint-disable-next-line no-console -- intentional diagnostics (no secrets)
            console.warn(
                '[XU Library] Display name is still the fallback ("XU User") but VITE_GOOGLE_CLIENT_ID is not set. Add it to .env (same OAuth Web Client ID as GOOGLE_CLIENT_ID), then restart npm run dev or run npm run build.'
            );
        }
    }, [missingViteClientId]);

    const onCredential = useCallback(
        async (response) => {
            const credential = response?.credential;
            if (!credential) return;
            const key = userId != null ? `xu_gis_prompt_${userId}` : null;
            setPhase('posting');
            try {
                await api.post('/me/google-profile', { credential });
                await refreshUser();
                setPhase('done');
            } catch {
                if (key) {
                    try {
                        sessionStorage.removeItem(key);
                    } catch {
                        /* ignore */
                    }
                }
                setPhase('failed');
            }
        },
        [refreshUser, userId]
    );

    useEffect(() => {
        callbackRef.current = onCredential;
    }, [onCredential]);

    useEffect(() => {
        if (!shouldRun) {
            setPhase('idle');
            return undefined;
        }

        const key = `xu_gis_prompt_${userId}`;
        if (sessionStorage.getItem(key) === '1') {
            return undefined;
        }

        let cancelled = false;

        // Do NOT set sessionStorage until we are about to call prompt(). Setting it before the async
        // loadGsiScript() completes breaks React 18 Strict Mode (dev): effect #1 sets the key, cleanup runs,
        // effect #2 bails out because the key is set, while effect #1's .then() aborts on cancelled — so
        // google.accounts.id.prompt() never runs and the user stays "XU User".

        setPhase('loading');

        loadGsiScript()
            .then(() => {
                if (cancelled) return;
                const google = window.google;
                if (!google?.accounts?.id) {
                    setPhase('idle');
                    return;
                }

                google.accounts.id.initialize({
                    client_id: clientId,
                    callback: (response) => callbackRef.current?.(response),
                    auto_select: true,
                    cancel_on_tap_outside: true,
                });

                setPhase('prompting');

                sessionStorage.setItem(key, '1');

                google.accounts.id.prompt((notification) => {
                    if (cancelled) return;
                    if (notification?.isNotDisplayed?.() || notification?.isSkippedMoment?.()) {
                        try {
                            sessionStorage.removeItem(key);
                        } catch {
                            /* ignore */
                        }
                        setPhase('idle');
                        return;
                    }
                    if (notification?.isDismissedMoment?.()) {
                        try {
                            sessionStorage.removeItem(key);
                        } catch {
                            /* ignore */
                        }
                        setPhase('idle');
                    }
                });
            })
            .catch(() => {
                if (!cancelled) {
                    sessionStorage.removeItem(key);
                    setPhase('idle');
                }
            });

        return () => {
            cancelled = true;
        };
    }, [shouldRun, userId, clientId]);

    useEffect(() => {
        if (phase !== 'failed') return undefined;
        const t = setTimeout(() => setPhase('idle'), 10000);
        return () => clearTimeout(t);
    }, [phase]);

    if (missingViteClientId) {
        return (
            <div
                className="fixed top-0 left-0 right-0 z-[90] border-b border-amber-200 bg-amber-50 px-4 py-2 text-center text-sm text-amber-950 shadow-sm"
                role="status"
            >
                Display name could not use Google: <code className="font-mono text-xs">VITE_GOOGLE_CLIENT_ID</code> is
                not set in the frontend environment. Set it to the same OAuth Web Client ID as{' '}
                <code className="font-mono text-xs">GOOGLE_CLIENT_ID</code>, then restart{' '}
                <code className="font-mono text-xs">npm run dev</code> or run <code className="font-mono text-xs">npm run build</code>
                . Your account still works with the generated name until then.
            </div>
        );
    }

    if (!shouldRun) return null;

    if (phase === 'idle' || phase === 'done') return null;

    return (
        <div
            className="fixed top-0 left-0 right-0 z-[90] border-b border-slate-200 bg-white/95 px-4 py-2 text-center text-sm text-slate-700 shadow-sm backdrop-blur-sm"
            role="status"
            aria-live="polite"
        >
            {phase === 'loading' && 'Loading secure sign-in…'}
            {phase === 'prompting' && 'Completing your profile with Google (OpenID only — not Gmail)…'}
            {phase === 'posting' && 'Saving your display name…'}
            {phase === 'failed' && 'Could not load your name from Google. You can keep using the app with your current display name.'}
        </div>
    );
}
