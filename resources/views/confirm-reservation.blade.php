<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>XU Library – Email confirmation</title>
    <meta name="color-scheme" content="light">
    <style>
        :root { color-scheme: light; }
        body {
            margin: 0;
            font-family: system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, "Apple Color Emoji", "Segoe UI Emoji";
            background: #f1f5f9;
            color: #0f172a;
        }
        .wrap { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; }
        .card {
            width: 100%;
            max-width: 520px;
            background: #ffffff;
            border: 1px solid rgba(148,163,184,0.55);
            border-radius: 16px;
            box-shadow: 0 14px 30px rgba(15, 23, 42, 0.08);
            padding: 28px;
            text-align: center;
        }
        .kicker { font-size: 12px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: #334155; }
        h1 { margin: 10px 0 0; font-size: 26px; letter-spacing: -0.02em; color: #1a365d; }
        .msg { margin: 14px 0 0; font-size: 15px; line-height: 1.55; }
        .msg.ok { color: #166534; }
        .msg.bad { color: #b91c1c; }
        .actions { margin-top: 22px; display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; }
        a.btn {
            display: inline-block;
            padding: 10px 14px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 650;
            border: 2px solid rgba(26,54,93,0.25);
            color: #1a365d;
            background: #fff;
        }
        a.btn.primary { background: #1a365d; color: #fff; border-color: #1a365d; }
        a.btn:focus { outline: 3px solid rgba(185, 148, 48, 0.45); outline-offset: 2px; }
        .foot { margin-top: 18px; font-size: 12px; color: #64748b; }
    </style>
</head>
<body>
    <main class="wrap">
        <section class="card" aria-live="polite">
            <div class="kicker">Xavier University Library</div>
            <h1>Email confirmation</h1>
            <p class="msg {{ $success ? 'ok' : 'bad' }}">{{ $message }}</p>
            <div class="actions">
                <a class="btn primary" href="{{ config('app.frontend_url', config('app.url')) }}/calendar">Back to calendar</a>
                <a class="btn" href="{{ config('app.frontend_url', config('app.url')) }}/login">Sign in</a>
            </div>
            <div class="foot">Space Reservation System</div>
        </section>
    </main>
</body>
</html>

