import React, { useMemo, useState } from 'react';
import { createRoot } from 'react-dom/client';
import api from './api';
import { ui } from './theme';
const xuLogotypeUrl = '/2023%20XU%20Logotype%20Revision%20V2%20Stacked_Full%20Color.png';

function AdminLoginStandalone() {
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(false);

    const alreadySignedIn = useMemo(() => Boolean(localStorage.getItem('token')), []);
    if (alreadySignedIn) {
        // Keep behavior simple and consistent with the SPA.
        window.location.replace('/');
        return null;
    }

    const handleSubmit = async (e) => {
        e.preventDefault();
        setError('');
        setLoading(true);
        try {
            const { data } = await api.post('/admin/login', { email, password });
            if (!data?.token || !data?.user) {
                setError('Unexpected response. Please try again.');
                return;
            }
            localStorage.setItem('token', data.token);
            localStorage.setItem('user', JSON.stringify(data.user));
            window.location.assign('/');
        } catch (err) {
            setError(err.response?.data?.message || 'Admin login failed.');
        } finally {
            setLoading(false);
        }
    };

    return (
        <div
            className={`${ui.pageCenter} w-full min-w-0 overflow-x-hidden px-4 py-10 pb-[max(2.5rem,env(safe-area-inset-bottom,0px))] sm:px-6 sm:py-14 sm:pb-14`}
        >
            <div className={`mx-auto w-full min-w-0 max-w-md p-6 sm:p-8 md:p-9 ${ui.card} shadow-xl shadow-slate-300/20 ring-1 ring-slate-200/65`}>
                <div className="text-center">
                    <div className="mb-6 flex w-full justify-center sm:mb-7">
                        <img
                            src={xuLogotypeUrl}
                            alt="Xavier University Library Logo"
                            width={560}
                            height={180}
                            fetchpriority="high"
                            decoding="async"
                            className="mx-auto h-auto w-full max-w-[min(100%,16rem)] object-contain sm:max-w-[18rem]"
                        />
                    </div>
                    <h1 className={`${ui.pageTitle} mb-2`}>Admin Sign-In</h1>
                    <p className="mx-auto mb-7 max-w-sm text-sm leading-relaxed text-slate-600 sm:mb-8">
                        Enter your admin email and password. Admin sign-in uses your account password (not an email OTP).
                    </p>
                </div>
                <form onSubmit={handleSubmit} className="space-y-4 text-left">
                    {error && <div className="rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700">{error}</div>}
                    <div>
                        <label className="block text-sm font-medium text-slate-700 mb-1">Email</label>
                        <input
                            type="email"
                            value={email}
                            onChange={(e) => setEmail(e.target.value)}
                            required
                            className={ui.input}
                            placeholder="you@xu.edu.ph"
                            autoComplete="email"
                        />
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-slate-700 mb-1">Password</label>
                        <input
                            type="password"
                            value={password}
                            onChange={(e) => setPassword(e.target.value)}
                            required
                            className={ui.input}
                            autoComplete="current-password"
                        />
                    </div>
                    <button type="submit" disabled={loading} className={ui.btnPrimaryFull}>
                        {loading ? 'Signing in…' : 'Sign in'}
                    </button>
                </form>
            </div>
        </div>
    );
}

const rootEl = document.getElementById('admin-login-root');
if (!rootEl) {
    throw new Error('Admin login: mount element #admin-login-root not found.');
}

createRoot(rootEl).render(
    <React.StrictMode>
        <AdminLoginStandalone />
    </React.StrictMode>
);

