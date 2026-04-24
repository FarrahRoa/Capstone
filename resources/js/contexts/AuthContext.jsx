import { createContext, useContext, useState, useEffect, useMemo } from 'react';
import { unwrapData } from '../utils/apiEnvelope';

const AuthContext = createContext(null);

let apiClientPromise = null;
async function getApiClient() {
    if (!apiClientPromise) {
        apiClientPromise = import('../api').then((m) => m.default || m);
    }
    return apiClientPromise;
}

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
    /**
     * "loading" means "we don't yet know if there's an authenticated user".
     * If we have a cached user, we can render immediately and refresh /me in the background.
     */
    const [loading, setLoading] = useState(() => {
        const token = localStorage.getItem('token');
        if (!token) return false;
        return !Boolean(user);
    });

    useEffect(() => {
        const token = localStorage.getItem('token');
        if (!token) {
            setLoading(false);
            return;
        }
        // If we already have a cached user, don't block the app shell on /me.
        // Also avoid pulling the API client/axios into the first paint critical path:
        // refresh /me after first paint/idle instead.
        const hasCachedUser = Boolean(user);
        if (hasCachedUser) setLoading(false);
        let cancelled = false;
        /** @type {number | null} */
        let timeoutId = null;
        /** @type {number | null} */
        let idleId = null;
        (async () => {
            try {
                const load = async () => {
                    const api = await getApiClient();
                    const { data } = await api.get('/me');
                    if (cancelled) return;
                    const normalized = normalizeUser(unwrapData(data));
                    setUser(normalized);
                    localStorage.setItem('user', JSON.stringify(normalized));
                };

                if (hasCachedUser) {
                    const w = /** @type {any} */ (window);
                    if (typeof w.requestIdleCallback === 'function') {
                        idleId = w.requestIdleCallback(() => load(), { timeout: 1500 });
                        return;
                    }
                    timeoutId = window.setTimeout(() => load(), 0);
                    return;
                }

                await load();
            } catch {
                if (cancelled) return;
                localStorage.removeItem('token');
                localStorage.removeItem('user');
                setUser(null);
            } finally {
                if (!cancelled) setLoading(false);
            }
        })();
        return () => {
            cancelled = true;
            if (timeoutId != null) window.clearTimeout(timeoutId);
            if (idleId != null) {
                const w = /** @type {any} */ (window);
                w.cancelIdleCallback?.(idleId);
            }
        };
    }, []);

    const permissionSet = useMemo(() => {
        const perms = user?.permissions;
        return Array.isArray(perms) ? new Set(perms) : new Set();
    }, [user?.permissions]);

    const login = (token, userData) => {
        const normalized = normalizeUser(userData);
        localStorage.setItem('token', token);
        localStorage.setItem('user', JSON.stringify(normalized));
        setUser(normalized);
    };

    const refreshUser = async () => {
        const token = localStorage.getItem('token');
        if (!token) return;
        const api = await getApiClient();
        const { data } = await api.get('/me');
        const normalized = normalizeUser(unwrapData(data));
        localStorage.setItem('user', JSON.stringify(normalized));
        setUser(normalized);
    };

    const hasPermission = (permission) => {
        if (!permission) return false;
        return permissionSet.has(permission);
    };

    const hasAnyPermission = (permissions = []) => {
        if (!Array.isArray(permissions) || permissions.length === 0) return false;
        for (const p of permissions) {
            if (permissionSet.has(p)) return true;
        }
        return false;
    };

    const hasAllPermissions = (permissions = []) => {
        if (!Array.isArray(permissions) || permissions.length === 0) return true;
        for (const p of permissions) {
            if (!permissionSet.has(p)) return false;
        }
        return true;
    };

    const logout = () => {
        getApiClient()
            .then((api) => api.post('/logout'))
            .catch(() => {});
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
