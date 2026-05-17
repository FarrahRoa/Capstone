import { Navigate } from 'react-router-dom';
import { useAuth } from '../../contexts/AuthContext';
import AdminLayout from '../AdminLayout';
import { ADMIN_ROUTE_PERMISSIONS } from './adminShell';

function RouteLoading() {
    return (
        <div className="flex min-h-screen items-center justify-center bg-xu-page text-xu-primary font-medium">
            Loading…
        </div>
    );
}

/**
 * Auth gate for the /admin route tree. Renders AdminLayout (with Outlet) — never Layout.jsx.
 */
export default function AdminAreaRoute() {
    const { user, loading, hasAnyPermission } = useAuth();

    if (loading && !user) {
        return <RouteLoading />;
    }
    if (!user) {
        return <Navigate to="/admin/login" replace />;
    }
    if (!hasAnyPermission(ADMIN_ROUTE_PERMISSIONS)) {
        return <Navigate to="/unauthorized" replace />;
    }

    return <AdminLayout />;
}
