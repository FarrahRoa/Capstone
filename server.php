<?php

/**
 * Router for `php artisan serve`.
 *
 * This file is used only by PHP's built-in server (and therefore by `php artisan serve`).
 * It helps local production-build Lighthouse runs by:
 * - serving real static files from /public
 * - attaching long-lived cache headers to Vite hashed assets under /build/assets
 *
 * In real production, caching MUST still be configured at the web server/CDN layer
 * (Apache/IIS/Nginx) and this file is not used.
 */

$uri = urldecode(
    parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/'
);

$publicPath = __DIR__ . DIRECTORY_SEPARATOR . 'public';
$path = realpath($publicPath . $uri);

// Serve the requested file directly if it exists under /public.
if ($path !== false && str_starts_with($path, realpath($publicPath)) && is_file($path)) {
    // NOTE: When the PHP built-in server router returns `false`, it serves the static file itself and does not
    // reliably preserve headers set by the router. For fingerprinted Vite assets we therefore serve the file
    // directly here so Cache-Control is guaranteed for Lighthouse/local audits.
    //
    // Vite hashes can include '-' / '_' (e.g. app-BmBjjO-B.js, vendor-react-N--QU9DW.js).
    // Support both /build/assets/* (Laravel default) and /assets/* (some deployments).
    $isFingerprintedViteAsset = (bool) preg_match(
        '#^/(?:build/)?assets/.+-[A-Za-z0-9_-]{8,}\\.(?:(?:js|css)(?:\\.map)?|woff2?|ttf|eot|svg|png|jpe?g|gif|webp)$#i',
        $uri
    );

    if ($isFingerprintedViteAsset) {
        header('Cache-Control: public, max-age=31536000, immutable');

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        // Minimal MIME map for assets Lighthouse checks most.
        $contentType = match ($ext) {
            'js' => 'application/javascript; charset=UTF-8',
            'css' => 'text/css; charset=UTF-8',
            'map', 'json' => 'application/json; charset=UTF-8',
            'svg' => 'image/svg+xml',
            'woff2' => 'font/woff2',
            'woff' => 'font/woff',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            default => 'application/octet-stream',
        };

        header("Content-Type: {$contentType}");
        header('Content-Length: '.(string) filesize($path));
        readfile($path);
        return true;
    }

    return false;
}

require_once $publicPath . DIRECTORY_SEPARATOR . 'index.php';

