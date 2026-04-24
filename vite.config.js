import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    // Ensure libraries that rely on NODE_ENV (including react-router) resolve production branches.
    define: {
        'process.env.NODE_ENV': '"production"',
    },
    plugins: [
        react(),
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.jsx', 'resources/js/admin-login.jsx'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    /**
     * Force production export conditions for react-router.
     *
     * Evidence: Lighthouse Treemap shows `node_modules/react-router/dist/development/*` dominating `app-*.js`
     * even on a production build. That indicates the resolver is still selecting the "development" condition.
     *
     * This does NOT change routing behavior; it only ensures production bundle resolution.
     */
    resolve: {
        conditions: ['production', 'browser', 'module', 'import', 'default'],
    },
    server: {
        // Use IPv4 so public/hot points to http://127.0.0.1:5173 — avoids [::] which can break when
        // php artisan serve is opened at 127.0.0.1 and the browser fails to load Vite assets.
        host: '127.0.0.1',
        port: 5173,
        strictPort: true,
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
    build: {
        // Production defaults, made explicit for audits.
        // Keep chunking conservative: fewer initial requests beats over-splitting tiny helpers.
        cssCodeSplit: true,
        // Required for Lighthouse Treemap module drill-down (it needs source maps for bundle analysis).
        // WARNING: sourcemaps can expose source structure; enable for analysis builds.
        sourcemap: true,
        minify: 'esbuild',
        rollupOptions: {
            output: {
                /**
                 * Split large shared deps out of the main app chunk so route JS stays leaner.
                 * This helps Lighthouse "Reduce unused JavaScript" on the dashboard route.
                 */
                manualChunks: {
                    'vendor-react': ['react', 'react-dom'],
                    /** react-router-dom stays in the app graph so Rollup can co-locate it with routes (fewer wasted KB vs a separate always-loaded vendor-router chunk). */
                },
            },
        },
    },
});
