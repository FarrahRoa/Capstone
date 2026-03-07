import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';

export default function Layout({ children }) {
    const { user, logout } = useAuth();
    const navigate = useNavigate();
    const isAdmin = user?.role?.slug === 'admin';

    const handleLogout = () => {
        logout();
        navigate('/login');
    };

    return (
        <div className="min-h-screen bg-slate-50">
            <nav className="bg-slate-800 text-white shadow">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="flex justify-between h-14 items-center">
                        <div className="flex gap-6">
                            <Link to="/" className="font-semibold text-lg">XU Library</Link>
                            <Link to="/" className="text-slate-300 hover:text-white">Calendar</Link>
                            {user?.role?.slug !== 'student_assistant' && (
                                <Link to="/reserve" className="text-slate-300 hover:text-white">New Reservation</Link>
                            )}
                            <Link to="/my-reservations" className="text-slate-300 hover:text-white">My Reservations</Link>
                            {isAdmin && (
                                <>
                                    <Link to="/admin/reservations" className="text-slate-300 hover:text-white">Admin</Link>
                                    <Link to="/admin/reports" className="text-slate-300 hover:text-white">Reports</Link>
                                </>
                            )}
                        </div>
                        <div className="flex items-center gap-4">
                            <span className="text-slate-300 text-sm">{user?.name} ({user?.role?.name})</span>
                            <button onClick={handleLogout} className="text-slate-300 hover:text-white text-sm">Logout</button>
                        </div>
                    </div>
                </div>
            </nav>
            <main className="max-w-7xl mx-auto px-4 py-6">{children}</main>
        </div>
    );
}
