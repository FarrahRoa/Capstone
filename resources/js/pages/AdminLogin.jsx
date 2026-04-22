import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import api from '../api';
import { useAuth } from '../contexts/AuthContext';
import { ui } from '../theme';
import xuLogotypeUrl from '../../../2023 XU Logotype Revision V2 Stacked_Full Color.png';

export default function AdminLogin() {
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(false);
    const { login } = useAuth();
    const navigate = useNavigate();

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
            login(data.token, data.user);
            navigate('/', { replace: true });
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
            <div
                className={`mx-auto w-full min-w-0 max-w-md p-6 sm:p-8 md:p-9 ${ui.card} shadow-xl shadow-slate-300/20 ring-1 ring-slate-200/65`}
            >
                <div className="text-center">
                    <div className="mb-6 flex w-full justify-center sm:mb-7">
                        <img
                            src={xuLogotypeUrl}
                            alt="Xavier University"
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
                    {error && (
                        <div className="rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700">{error}</div>
                    )}
                    <div>
                        <label className="block text-sm font-medium text-slate-700 mb-1">Email</label>
                        <input
                            type="email"
                            value={email}
                            onChange={(e) => setEmail(e.target.value)}
                            required
                            className={ui.input}
                            placeholder="you@xu.edu.ph"
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

