import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import { ui } from '../theme';

export default function Unauthorized() {
    const { user, logout, loading } = useAuth();
    const navigate = useNavigate();

    const handleLogout = () => {
        logout();
        navigate('/login', { replace: true });
    };

    if (loading) {
        return (
            <div className={ui.pageCenter}>
                <p className="text-slate-600">Loading…</p>
            </div>
        );
    }

    return (
        <div className={ui.pageCenter}>
            <div className={`w-full max-w-md p-8 text-center ${ui.card}`}>
                <h1 className="text-xl font-bold text-xu-primary font-serif mb-2">Access denied</h1>
                <p className="text-slate-600 text-sm mb-6">
                    You do not have permission to open that page. If you followed a bookmark or link, use an account with the right role.
                </p>
                <div className="flex flex-col gap-3">
                    {user && (
                        <>
                            <Link to="/" className={`block w-full text-center ${ui.btnPrimaryFull}`}>
                                Back to home
                            </Link>
                            <button
                                type="button"
                                onClick={handleLogout}
                                className="w-full text-xu-secondary text-sm py-2 hover:text-xu-primary hover:underline"
                            >
                                Log out
                            </button>
                        </>
                    )}
                    {!user && (
                        <Link to="/login" className={`block w-full text-center ${ui.btnPrimaryFull}`}>
                            Go to sign in
                        </Link>
                    )}
                </div>
            </div>
        </div>
    );
}
