import { useState } from 'react';
import { useNavigate, useLocation } from 'react-router-dom';
import api from '../api';
import { useAuth } from '../contexts/AuthContext';

export default function OTPVerify() {
    const [otp, setOtp] = useState('');
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(false);
    const [resending, setResending] = useState(false);
    const { login } = useAuth();
    const navigate = useNavigate();
    const { state } = useLocation();
    const email = state?.email || '';

    const handleSubmit = async (e) => {
        e.preventDefault();
        if (!email) { setError('Session expired. Please log in again.'); return; }
        setError('');
        setLoading(true);
        try {
            const { data } = await api.post('/otp/verify', { email, otp });
            login(data.token, data.user);
            navigate('/', { replace: true });
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
        try {
            await api.post('/otp/resend', { email });
            setError('');
            alert('OTP resent to your email.');
        } catch (err) {
            setError(err.response?.data?.message || 'Could not resend OTP.');
        } finally {
            setResending(false);
        }
    };

    if (!email) {
        return (
            <div className="min-h-screen flex items-center justify-center bg-slate-100">
                <div className="bg-white p-8 rounded-xl shadow">
                    <p className="text-slate-600">No email in session. Please <a href="/login" className="text-slate-800 underline">log in again</a>.</p>
                </div>
            </div>
        );
    }

    return (
        <div className="min-h-screen flex items-center justify-center bg-slate-100 px-4">
            <div className="w-full max-w-md bg-white rounded-xl shadow-lg p-8">
                <h1 className="text-2xl font-bold text-slate-800 mb-2">Verify OTP</h1>
                <p className="text-slate-600 mb-6">Enter the 6-digit code sent to {email}</p>
                <form onSubmit={handleSubmit} className="space-y-4">
                    {error && <div className="text-red-600 text-sm bg-red-50 p-3 rounded">{error}</div>}
                    <div>
                        <label className="block text-sm font-medium text-slate-700 mb-1">OTP</label>
                        <input type="text" value={otp} onChange={(e) => setOtp(e.target.value.replace(/\D/g, '').slice(0, 6))} maxLength={6} required
                            className="w-full rounded-lg border border-slate-300 px-3 py-2 text-center text-lg tracking-widest focus:ring-2 focus:ring-slate-500" placeholder="000000" />
                    </div>
                    <button type="submit" disabled={loading}
                        className="w-full bg-slate-800 text-white py-2 rounded-lg font-medium hover:bg-slate-700 disabled:opacity-50">Verify</button>
                    <button type="button" onClick={handleResend} disabled={resending}
                        className="w-full text-slate-600 text-sm py-2 hover:underline disabled:opacity-50">Resend OTP</button>
                </form>
            </div>
        </div>
    );
}
