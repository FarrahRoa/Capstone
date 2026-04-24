# Admin Dashboard performance (Lighthouse)

This project is a Laravel + React/Vite SPA. Lighthouse scores vary heavily depending on **how you serve the production build**.

## Always test a production build

1. Build frontend assets:

```
npm install
npm run build
```

2. Make sure you are **not** on the Vite dev server:
- If `public/hot` exists and you are not running `npm run dev`, delete `public/hot`.

## Cache lifetimes (the #1 remaining penalty)

Lighthouse will penalize you if hashed assets are served without long-lived cache headers.

### What should be cached

Only **fingerprinted** build outputs (hashed filenames) should be cached aggressively:
- `/(build/)?assets/*-<hash>.(js|css|woff2|svg|png|...)`

Those should be served with:
- `Cache-Control: public, max-age=31536000, immutable`

### What should NOT be cached aggressively

- The SPA HTML document (`/`) should not be marked immutable.
- API responses under `/api/*` should not be marked immutable.
- Any non-fingerprinted files should not be marked immutable.

### Apache / IIS / local PHP server

This repo includes cache-header rules for:
- **Apache**: `public/.htaccess`
- **IIS**: `public/web.config`
- **Local `php artisan serve`**: `server.php` (router script for PHP built-in server)

If Lighthouse still shows **Cache TTL = None**, verify the response headers in DevTools:
- Network → click `vendor-react-*.js` / `app-*.css` → Response Headers → `Cache-Control`

## Running Lighthouse locally (production-style)

If you’re using PHP’s built-in server, ensure it uses the router script:

```
php artisan serve
```

If you are not using `artisan serve`, use the router explicitly:

```
php -S 127.0.0.1:8000 server.php
```

Then run Lighthouse against `http://127.0.0.1:8000/` after logging in as an admin user.

## “Reduce unused JavaScript”

This project uses route-level `React.lazy()` for non-dashboard pages to keep the Admin Dashboard initial route lean.

If Lighthouse still reports unused JS, use:
- Lighthouse → “View Treemap”
- Identify the largest unused modules inside `vendor-react-*.js` and `app-*.js`
- Only then move route-irrelevant imports out of the dashboard bootstrap path (avoid random lazy loading).

## Router bundle sanity check

If Lighthouse Treemap shows `react-router/dist/development/*` inside a production build, check your installed dependency
versions match `package.json` (delete any stale lockfile and re-install). A mismatched lockfile can pin a newer major
router that bundles development builds by default.

