import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import api from '../api';
import { useAuth } from '../contexts/AuthContext';

export default function Login() {
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [name, setName] = useState('');
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(false);
    const { login } = useAuth();
    const navigate = useNavigate();

    const handleSubmit = async (e) => {
        e.preventDefault();
        setError('');
        setLoading(true);
        try {
            const { data } = await api.post('/login', { email, password, name });
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
        <div className="min-h-screen flex items-center justify-center bg-slate-100 px-4">
            <div className="w-full max-w-md bg-white rounded-xl shadow-lg p-8">
                <h1 className="text-2xl font-bold text-slate-800 mb-2">XU Library</h1>
                <p className="text-slate-600 mb-6">Sign in with your Xavier University email</p>
                <form onSubmit={handleSubmit} className="space-y-4">
                    {error && <div className="text-red-600 text-sm bg-red-50 p-3 rounded">{error}</div>}
                    <div>
                        <label className="block text-sm font-medium text-slate-700 mb-1">Email</label>
                        <input type="email" value={email} onChange={(e) => setEmail(e.target.value)} required
                            className="w-full rounded-lg border border-slate-300 px-3 py-2 focus:ring-2 focus:ring-slate-500 focus:border-slate-500" placeholder="you@xu.edu.ph or you@my.xu.edu.ph" />
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-slate-700 mb-1">Password</label>
                        <input type="password" value={password} onChange={(e) => setPassword(e.target.value)} required
                            className="w-full rounded-lg border border-slate-300 px-3 py-2 focus:ring-2 focus:ring-slate-500 focus:border-slate-500" />
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-slate-700 mb-1">Name (optional, for first-time)</label>
                        <input type="text" value={name} onChange={(e) => setName(e.target.value)}
                            className="w-full rounded-lg border border-slate-300 px-3 py-2 focus:ring-2 focus:ring-slate-500 focus:border-slate-500" placeholder="Your full name" />
                    </div>
                    <button type="submit" disabled={loading}
                        className="w-full bg-slate-800 text-white py-2 rounded-lg font-medium hover:bg-slate-700 disabled:opacity-50">Sign in</button>
                </form>
            </div>
        </div>
    );
}
