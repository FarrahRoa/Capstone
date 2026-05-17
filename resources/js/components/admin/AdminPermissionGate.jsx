import { Navigate } from 'react-router-dom';
import { useAuth } from '../../contexts/AuthContext';

/**
 * Per-page permission check inside the /admin layout (does not swap layouts).
 */
export default function AdminPermissionGate({ permission, children }) {
    const { user, loading, hasPermission } = useAuth();

    if (loading && !user) {
        return (
            <p className="py-8 text-center text-sm font-medium text-slate-600">Loading…</p>
        );
    }
    if (!user) {
        return <Navigate to="/admin/login" replace />;
    }
    if (!hasPermission(permission)) {
        return <Navigate to="/unauthorized" replace />;
    }

    return children;
}
