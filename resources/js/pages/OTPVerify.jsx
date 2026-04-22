import { useState } from 'react';
import { Link, useNavigate, useLocation } from 'react-router-dom';
import api from '../api';
import { useAuth } from '../contexts/AuthContext';
import { ui } from '../theme';

export default function OTPVerify() {
    const [otp, setOtp] = useState('');
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(false);
    const [resending, setResending] = useState(false);
    const [resendSuccess, setResendSuccess] = useState(false);
    const { login } = useAuth();
    const navigate = useNavigate();
    const { state } = useLocation();
    const email = state?.email || '';
    const intent = state?.intent || 'user';

    const handleSubmit = async (e) => {
        e.preventDefault();
        if (!email) { setError('Session expired. Please log in again.'); return; }
        setError('');
        setResendSuccess(false);
        setLoading(true);
        try {
            const { data } = await api.post('/otp/verify', { email, otp });
            login(data.token, data.user);
            if (intent === 'admin') {
                sessionStorage.removeItem('xu_profile_completion_after_otp');
                navigate('/', { replace: true });
                return;
            }
            if (data.user?.requires_profile_completion) {
                sessionStorage.setItem('xu_profile_completion_after_otp', '1');
                navigate('/complete-profile', { replace: true });
            } else {
                sessionStorage.removeItem('xu_profile_completion_after_otp');
                navigate('/', { replace: true });
            }
        } catch (err) {
            setError(err.response?.data?.message || 'Invalid OTP.');
        } finally {
            setLoading(false);
        }
    };

    const handleResend = async () => {
        if (!email) return;
        setResending(true);
        setError('');
        setResendSuccess(false);
        try {
            await api.post('/otp/resend', { email });
            setResendSuccess(true);
        } catch (err) {
            setError(err.response?.data?.message || 'Could not resend OTP.');
        } finally {
            setResending(false);
        }
    };

    if (!email) {
        return (
            <div className={ui.pageCenter}>
                <div className={`p-8 ${ui.card}`}>
                    <p className="text-slate-600 text-sm">
                        No email in session. Please{' '}
                        <Link to={intent === 'admin' ? '/admin/login' : '/login'} className={ui.linkAccent}>
                            log in again
                        </Link>
                        .
                    </p>
                </div>
            </div>
        );
    }

    return (
        <div className={ui.pageCenter}>
            <div className={`w-full max-w-md p-8 ${ui.card}`}>
                <h1 className={`${ui.pageTitle} mb-2`}>Verify OTP</h1>
                <p className="text-slate-600 mb-6 text-sm">Enter the 6-digit code sent to {email}</p>
                <form onSubmit={handleSubmit} className="space-y-4">
                    {resendSuccess && (
                        <div className="text-green-800 text-sm bg-green-50 border border-green-200 p-3 rounded-lg">
                            A new code was sent to your email.
                        </div>
                    )}
                    {error && <div className="text-red-700 text-sm bg-red-50 border border-red-200 p-3 rounded-lg">{error}</div>}
                    <div>
                        <label className="block text-sm font-medium text-slate-700 mb-1">OTP</label>
                        <input
                            type="text"
                            value={otp}
                            onChange={(e) => setOtp(e.target.value.replace(/\D/g, '').slice(0, 6))}
                            maxLength={6}
                            required
                            className={`${ui.input} text-center text-lg tracking-widest`}
                            placeholder="000000"
                        />
                    </div>
                    <button type="submit" disabled={loading} className={ui.btnPrimaryFull}>
                        Verify
                    </button>
                    <button
                        type="button"
                        onClick={handleResend}
                        disabled={resending}
                        className="w-full text-xu-secondary text-sm py-2 hover:text-xu-primary hover:underline disabled:opacity-50"
                    >
                        Resend OTP
                    </button>
                </form>
            </div>
        </div>
    );
}
