import { useEffect, useMemo, useState } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import api from '../api';
import { ui } from '../theme';

export default function AdminInviteSetPassword() {
    const [params] = useSearchParams();
    const navigate = useNavigate();

    const email = useMemo(() => (params.get('email') || '').trim(), [params]);
    const token = useMemo(() => (params.get('token') || '').trim(), [params]);

    const [loading, setLoading] = useState(true);
    const [valid, setValid] = useState(false);
    const [error, setError] = useState('');

    const [password, setPassword] = useState('');
    const [passwordConfirmation, setPasswordConfirmation] = useState('');
    const [saving, setSaving] = useState(false);
    const [done, setDone] = useState(false);

    useEffect(() => {
        setLoading(true);
        setError('');
        setValid(false);
        if (!email || !token) {
            setError('Invalid invite link.');
            setLoading(false);
            return;
        }
        api.get('/admin/librarian-invite/validate', { params: { email, token } })
            .then(() => setValid(true))
            .catch((err) => {
                setError(err.response?.data?.message || 'Invite link is not valid.');
                setValid(false);
            })
            .finally(() => setLoading(false));
    }, [email, token]);

    const submit = async (e) => {
        e.preventDefault();
        setError('');
        setSaving(true);
        try {
            await api.post('/admin/librarian-invite/accept', {
                email,
                token,
                password,
                password_confirmation: passwordConfirmation,
            });
            setDone(true);
        } catch (err) {
            const message =
                err.response?.data?.message ||
                err.response?.data?.errors?.password?.[0] ||
                'Failed to set password.';
            setError(message);
        } finally {
            setSaving(false);
        }
    };

    return (
        <div className={ui.pageCenter}>
            <div className={`w-full max-w-md p-8 ${ui.card}`}>
                <h1 className={`${ui.pageTitle} mb-1`}>Set your password</h1>
                <p className="text-slate-600 mb-6 text-sm">
                    This password is for the XU Library reservation system. It is not your Google/XU password.
                </p>

                {loading && <p className="text-slate-600 text-sm">Checking invite…</p>}

                {!loading && error && (
                    <div className="text-red-700 text-sm bg-red-50 border border-red-200 p-3 rounded-lg">{error}</div>
                )}

                {!loading && done && (
                    <div className="space-y-4">
                        <div className="text-green-800 text-sm bg-green-50 border border-green-200 p-3 rounded-lg">
                            Password set successfully. You can now sign in via the admin login page.
                        </div>
                        <button type="button" className={ui.btnPrimaryFull} onClick={() => navigate('/admin/login', { replace: true })}>
                            Go to admin login
                        </button>
                    </div>
                )}

                {!loading && valid && !done && (
                    <form onSubmit={submit} className="space-y-4">
                        <div className="text-xs text-slate-600">
                            Invited email: <span className="font-medium text-slate-900">{email}</span>
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-slate-700 mb-1">New password</label>
                            <input
                                type="password"
                                value={password}
                                onChange={(e) => setPassword(e.target.value)}
                                required
                                className={ui.input}
                                placeholder="Create a strong password"
                            />
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-slate-700 mb-1">Confirm password</label>
                            <input
                                type="password"
                                value={passwordConfirmation}
                                onChange={(e) => setPasswordConfirmation(e.target.value)}
                                required
                                className={ui.input}
                            />
                        </div>

                        <button type="submit" disabled={saving} className={ui.btnPrimaryFull}>
                            {saving ? 'Saving…' : 'Set password'}
                        </button>
                    </form>
                )}
            </div>
        </div>
    );
}

