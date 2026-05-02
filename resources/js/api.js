import axios from 'axios';

const api = axios.create({
    baseURL: '/api',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    withCredentials: true,
});

api.interceptors.request.use((config) => {
    const token = localStorage.getItem('token');
    if (token) config.headers.Authorization = `Bearer ${token}`;
    return config;
});

api.interceptors.response.use(
    (r) => r,
    (err) => {
        const reqPath = `${err.config?.baseURL ?? ''}${err.config?.url ?? ''}`;
        const isAdminPasswordLogin = reqPath.includes('/admin/login');
        if (err.response?.status === 401 && !isAdminPasswordLogin) {
            localStorage.removeItem('token');
            localStorage.removeItem('user');
            window.location.href = '/login';
        }
        return Promise.reject(err);
    }
);

/**
 * POST multipart (e.g. file upload). Omits JSON Content-Type so the browser sets multipart boundaries.
 * @param {string} url
 * @param {FormData} formData
 */
export function postMultipart(url, formData) {
    return api.post(url, formData, {
        transformRequest: [
            (data, headers) => {
                if (data instanceof FormData) {
                    delete headers['Content-Type'];
                }
                return data;
            },
        ],
    });
}

/**
 * @param {string} url
 * @param {FormData} formData
 */
export function putMultipart(url, formData) {
    return api.put(url, formData, {
        transformRequest: [
            (data, headers) => {
                if (data instanceof FormData) {
                    delete headers['Content-Type'];
                }
                return data;
            },
        ],
    });
}

export default api;
