import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import api from '../api';
import { useAuth } from '../contexts/AuthContext';
import { ui } from '../theme';

export default function Login() {
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
            const { data } = await api.post('/login', { email, password });
            if (data.requires_otp) {
                navigate('/otp', { state: { email } });
                return;
            }
            login(data.token, data.user);
            navigate('/', { replace: true });
        } catch (err) {
            setError(err.response?.data?.message || 'Login failed.');
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className={ui.pageCenter}>
            <div className={`w-full max-w-md p-8 ${ui.card}`}>
                <h1 className={`${ui.pageTitle} mb-1`}>XU Library</h1>
                <p className="text-slate-600 mb-6 text-sm">Sign in with your Xavier University email</p>
                <form onSubmit={handleSubmit} className="space-y-4">
                    {error && <div className="text-red-700 text-sm bg-red-50 border border-red-200 p-3 rounded-lg">{error}</div>}
                    <div>
                        <label className="block text-sm font-medium text-slate-700 mb-1">Email</label>
                        <input type="email" value={email} onChange={(e) => setEmail(e.target.value)} required className={ui.input} placeholder="you@xu.edu.ph or you@my.xu.edu.ph" />
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-slate-700 mb-1">Password</label>
                        <input type="password" value={password} onChange={(e) => setPassword(e.target.value)} required className={ui.input} />
                    </div>
                    <button type="submit" disabled={loading} className={ui.btnPrimaryFull}>
                        Sign in
                    </button>
                </form>
            </div>
        </div>
    );
}
