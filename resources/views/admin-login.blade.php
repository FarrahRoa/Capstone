<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>XU Library - Admin Sign-In</title>

    @php
        $logoUrl = Vite::asset('resources/images/xu-logotype-stacked.svg');
    @endphp
    <link rel="preload" as="image" href="{{ $logoUrl }}" fetchpriority="high">

    @vite(['resources/css/app.css', 'resources/js/admin-login.jsx'])
</head>
<body class="antialiased min-h-screen bg-xu-page">
    <div id="admin-login-root">
        <div class="min-h-screen min-w-0 flex items-center justify-center bg-xu-page px-4 py-10">
            <div class="mx-auto w-full min-w-0 max-w-md rounded-xl border border-slate-200/90 bg-white p-6 shadow-xl shadow-slate-300/20 ring-1 ring-slate-200/65 sm:p-8 md:p-9">
                <div class="text-center">
                    <div class="mb-6 flex w-full justify-center sm:mb-7">
                        <img
                            src="{{ $logoUrl }}"
                            alt="Xavier University"
                            width="560"
                            height="180"
                            fetchpriority="high"
                            decoding="async"
                            class="mx-auto h-auto w-full max-w-[min(100%,16rem)] object-contain sm:max-w-[18rem]"
                        />
                    </div>
                    <h1 class="mb-2 font-serif text-2xl font-bold tracking-tight text-xu-primary sm:text-3xl">Admin Sign-In</h1>
                    <p class="mx-auto mb-7 max-w-sm text-sm leading-relaxed text-slate-600 sm:mb-8">
                        Enter your admin email and password. Admin sign-in uses your account password (not an email OTP).
                    </p>
                </div>
                <div class="space-y-4 text-left">
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700">
                        Loading…
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

