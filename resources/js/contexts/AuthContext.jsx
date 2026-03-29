import { createContext, useContext, useState, useEffect } from 'react';
import api from '../api';

const AuthContext = createContext(null);

function normalizeUser(userData) {
    if (!userData) return null;
    return {
        ...userData,
        permissions: Array.isArray(userData.permissions) ? userData.permissions : [],
    };
}

export function AuthProvider({ children }) {
    const [user, setUser] = useState(() => {
        try {
            const u = localStorage.getItem('user');
            return u ? normalizeUser(JSON.parse(u)) : null;
        } catch {
            return null;
        }
    });
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        const token = localStorage.getItem('token');
        if (!token) {
            setLoading(false);
            return;
        }
        api.get('/me')
            .then(({ data }) => {
                const normalized = normalizeUser(data);
                setUser(normalized);
                localStorage.setItem('user', JSON.stringify(normalized));
            })
            .catch(() => {
                localStorage.removeItem('token');
                localStorage.removeItem('user');
                setUser(null);
            })
            .finally(() => setLoading(false));
    }, []);

    const login = (token, userData) => {
        const normalized = normalizeUser(userData);
        localStorage.setItem('token', token);
        localStorage.setItem('user', JSON.stringify(normalized));
        setUser(normalized);
    };

    const refreshUser = async () => {
        const token = localStorage.getItem('token');
        if (!token) return;
        const { data } = await api.get('/me');
        const normalized = normalizeUser(data);
        localStorage.setItem('user', JSON.stringify(normalized));
        setUser(normalized);
    };

    const hasPermission = (permission) => {
        if (!permission) return false;
        return Array.isArray(user?.permissions) && user.permissions.includes(permission);
    };

    const hasAnyPermission = (permissions = []) => {
        if (!Array.isArray(permissions) || permissions.length === 0) return false;
        return permissions.some((permission) => hasPermission(permission));
    };

    const hasAllPermissions = (permissions = []) => {
        if (!Array.isArray(permissions) || permissions.length === 0) return true;
        return permissions.every((permission) => hasPermission(permission));
    };

    const logout = () => {
        api.post('/logout').catch(() => {});
        localStorage.removeItem('token');
        localStorage.removeItem('user');
        try {
            Object.keys(sessionStorage).forEach((k) => {
                if (k.startsWith('xu_gis_prompt_')) sessionStorage.removeItem(k);
            });
        } catch {
            /* ignore */
        }
        setUser(null);
    };

    return (
        <AuthContext.Provider
            value={{
                user,
                loading,
                login,
                logout,
                refreshUser,
                hasPermission,
                hasAnyPermission,
                hasAllPermissions,
            }}
        >
            {children}
        </AuthContext.Provider>
    );
}

export function useAuth() {
    const ctx = useContext(AuthContext);
    if (!ctx) throw new Error('useAuth must be used within AuthProvider');
    return ctx;
}
