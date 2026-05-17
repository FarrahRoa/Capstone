import { useCallback, useEffect, useState } from 'react';
import { Link, useNavigate, useLocation } from 'react-router-dom';
import api from '../api';
import { useAuth } from '../contexts/AuthContext';
import { ui } from '../theme';

const RESEND_COOLDOWN_SECONDS = 30;
const OTP_EXPIRY_SECONDS = 60;

function formatCountdown(seconds) {
    const s = Math.max(0, seconds);
    const m = Math.floor(s / 60);
    const r = s % 60;
    return `${m}:${String(r).padStart(2, '0')}`;
}

function isOtpLockoutError(err) {
    return err?.response?.status === 429;
}

export default function OTPVerify() {
    const [otp, setOtp] = useState('');
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(false);
    const [resending, setResending] = useState(false);
    const [resendSuccess, setResendSuccess] = useState(false);
    const [lockedOut, setLockedOut] = useState(false);
    const [resendTimer, setResendTimer] = useState(RESEND_COOLDOWN_SECONDS);
    const [expiryTimer, setExpiryTimer] = useState(OTP_EXPIRY_SECONDS);
    const { login } = useAuth();
    const navigate = useNavigate();
    const { state } = useLocation();
    const email = state?.email || '';
    const intent = state?.intent || 'user';

    const resetOtpTimers = useCallback(() => {
        setResendTimer(RESEND_COOLDOWN_SECONDS);
        setExpiryTimer(OTP_EXPIRY_SECONDS);
    }, []);

    useEffect(() => {
        if (lockedOut) return undefined;

        const id = window.setInterval(() => {
            setResendTimer((t) => (t > 0 ? t - 1 : 0));
            setExpiryTimer((t) => (t > 0 ? t - 1 : 0));
        }, 1000);

        return () => window.clearInterval(id);
    }, [lockedOut]);

    const handleApiError = (err, fallbackMessage) => {
        if (isOtpLockoutError(err)) {
            setLockedOut(true);
            setError('Maximum attempts reached. Please try again in 1 hour.');
            return;
        }
        setError(err.response?.data?.message || fallbackMessage);
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        if (!email) {
            setError('Session expired. Please log in again.');
            return;
        }
        if (lockedOut) return;
        if (expiryTimer <= 0) {
            setError('OTP has expired. Request a new code.');
            return;
        }
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
            handleApiError(err, 'Invalid OTP.');
        } finally {
            setLoading(false);
        }
    };

    const handleResend = async () => {
        if (!email || lockedOut || resendTimer > 0) return;
        setResending(true);
        setError('');
        setResendSuccess(false);
        try {
            await api.post('/otp/resend', { email });
            setResendSuccess(true);
            resetOtpTimers();
        } catch (err) {
            handleApiError(err, 'Could not resend OTP.');
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
                <p className="text-slate-600 mb-2 text-sm">Enter the 6-digit code sent to {email}</p>
                {!lockedOut && (
                    <p className="text-slate-500 mb-6 text-xs tabular-nums" aria-live="polite">
                        {expiryTimer > 0 ? (
                            <>Code expires in {formatCountdown(expiryTimer)}</>
                        ) : (
                            <span className="font-medium text-amber-800">Code expired — request a new one below.</span>
                        )}
                    </p>
                )}

                {lockedOut ? (
                    <div
                        className="rounded-lg border border-red-300 bg-red-50 p-4 text-sm font-medium text-red-900"
                        role="alert"
                    >
                        Maximum attempts reached. Please try again in 1 hour.
                    </div>
                ) : (
                    <form onSubmit={handleSubmit} className="space-y-4">
                        {resendSuccess && (
                            <div className="text-green-800 text-sm bg-green-50 border border-green-200 p-3 rounded-lg">
                                A new code was sent to your email.
                            </div>
                        )}
                        {error && (
                            <div className="text-red-700 text-sm bg-red-50 border border-red-200 p-3 rounded-lg">{error}</div>
                        )}
                        <div>
                            <label className="block text-sm font-medium text-slate-700 mb-1">OTP</label>
                            <input
                                type="text"
                                value={otp}
                                onChange={(e) => setOtp(e.target.value.replace(/\D/g, '').slice(0, 6))}
                                maxLength={6}
                                required
                                disabled={expiryTimer <= 0}
                                className={`${ui.input} text-center text-lg tracking-widest disabled:opacity-60`}
                                placeholder="000000"
                            />
                        </div>
                        <button
                            type="submit"
                            disabled={loading || expiryTimer <= 0}
                            className={ui.btnPrimaryFull}
                        >
                            Verify
                        </button>
                        <button
                            type="button"
                            onClick={handleResend}
                            disabled={resending || resendTimer > 0}
                            className="w-full text-xu-secondary text-sm py-2 hover:text-xu-primary hover:underline disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            {resendTimer > 0
                                ? `Resend OTP (${resendTimer}s)`
                                : resending
                                  ? 'Sending…'
                                  : 'Resend OTP'}
                        </button>
                    </form>
                )}
            </div>
        </div>
    );
}
