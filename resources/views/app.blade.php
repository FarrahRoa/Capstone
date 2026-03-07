<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>XU Library - Space Reservation</title>
    @php
        $manifestPath = public_path('build/manifest.json');
        $useBuild = file_exists($manifestPath);
    @endphp
    @if($useBuild)
        @php $manifest = json_decode(file_get_contents($manifestPath), true); @endphp
        @if(isset($manifest['resources/css/app.css']['file']))
            <link rel="stylesheet" href="{{ asset('build/' . $manifest['resources/css/app.css']['file']) }}">
        @endif
        @if(isset($manifest['resources/js/app.jsx']['file']))
            <script type="module" src="{{ asset('build/' . $manifest['resources/js/app.jsx']['file']) }}"></script>
        @endif
    @else
        @vite(['resources/css/app.css', 'resources/js/app.jsx'])
    @endif
</head>
<body class="antialiased min-h-screen bg-slate-50">
    <div id="root">
        <div class="min-h-screen flex items-center justify-center bg-slate-100 p-8" style="font-family: system-ui, sans-serif; min-height: 100vh; display: flex; align-items: center; justify-content: center; background: #f1f5f9; padding: 2rem;">
            <div class="text-center text-slate-600 max-w-md">
                <p class="text-lg font-medium text-slate-800 mb-2">XU Library – Space Reservation</p>
                <p>Loading the app…</p>
                <p class="mt-4 text-sm">If this message does not disappear, the frontend assets are not built. In the project folder run:</p>
                <pre class="mt-2 p-3 bg-slate-200 rounded text-left text-xs overflow-x-auto">npm install
npm run build</pre>
                <p class="text-sm mt-2">Then refresh this page. For live reload during development use <code class="bg-slate-200 px-1 rounded">npm run dev</code> in one terminal and <code class="bg-slate-200 px-1 rounded">php artisan serve</code> in another.</p>
            </div>
        </div>
    </div>
</body>
</html>
