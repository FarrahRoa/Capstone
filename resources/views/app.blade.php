<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>XU Library - Space Reservation</title>
    {{--
        Laravel's @vite resolves assets in this order:
        1. If public/hot exists → load from the Vite dev server (see vite.config.js server.host for a stable URL on Windows).
        2. Else use public/build/manifest.json from "npm run build".
        If the page stays on "Loading…", delete public/hot when not running "npm run dev", or ensure Vite is running.
    --}}
    @vite(['resources/css/app.css', 'resources/js/app.jsx'])
</head>
<body class="antialiased min-h-screen bg-slate-50">
    <div id="root">
        <div class="min-h-screen flex items-center justify-center bg-slate-100 p-8" style="font-family: system-ui, sans-serif; min-height: 100vh; display: flex; align-items: center; justify-content: center; background: #f1f5f9; padding: 2rem;">
            <div class="text-center text-slate-600 max-w-md">
                <p class="text-lg font-medium text-slate-800 mb-2">XU Library – Space Reservation</p>
                <p>Loading the app…</p>
                <p class="mt-4 text-sm">If this message does not disappear:</p>
                <ul class="mt-2 text-sm text-left list-disc pl-5 space-y-1">
                    <li>Run <code class="bg-slate-200 px-1 rounded">npm run dev</code> and keep it running, <strong>or</strong></li>
                    <li>Run <code class="bg-slate-200 px-1 rounded">npm run build</code> and remove <code class="bg-slate-200 px-1 rounded">public/hot</code> if you are not using the dev server.</li>
                </ul>
            </div>
        </div>
    </div>
</body>
</html>
