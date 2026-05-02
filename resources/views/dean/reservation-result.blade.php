<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }} – XU Library</title>
    <meta name="robots" content="noindex, nofollow">
    <style>
        body { margin: 0; font-family: system-ui, sans-serif; background: #f1f5f9; color: #0f172a; }
        .wrap { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; }
        .card { width: 100%; max-width: 520px; background: #fff; border: 1px solid #cbd5e1; border-radius: 12px; padding: 28px; text-align: center; }
        h1 { font-size: 1.35rem; color: #1a365d; margin: 0 0 12px; }
        p { margin: 0; line-height: 1.55; color: #334155; }
    </style>
</head>
<body>
    <main class="wrap">
        <div class="card">
            <h1>{{ $title }}</h1>
            <p>{{ $message }}</p>
        </div>
    </main>
</body>
</html>
