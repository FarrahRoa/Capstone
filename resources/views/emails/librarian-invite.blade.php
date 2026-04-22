<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin access invitation</title>
</head>
<body style="font-family: system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif; line-height: 1.5; color: #0f172a;">
    <h2 style="margin: 0 0 12px;">You’ve been invited to the XU Library reservation system</h2>
    <p style="margin: 0 0 12px;">
        An administrator invited you to access the <strong>admin side</strong> of the XU Library reservation system as:
        <strong>{{ $roleName }}</strong>.
    </p>
    <p style="margin: 0 0 12px;">
        Use the credentials below on the <strong>Admin Sign-In</strong> page. These are credentials for this application only — they are
        <strong>not</strong> your Google/XU password.
    </p>
    <div style="margin: 16px 0; padding: 14px 16px; border: 1px solid #e2e8f0; border-radius: 10px; background: #f8fafc;">
        <p style="margin: 0 0 8px;"><strong>Login email:</strong> {{ $email }}</p>
        <p style="margin: 0;"><strong>Temporary password:</strong> {{ $temporaryPassword }}</p>
    </div>
    <p style="margin: 16px 0;">
        <a href="{{ $adminSignInUrl }}" style="display: inline-block; background: #0f172a; color: #ffffff; text-decoration: none; padding: 10px 14px; border-radius: 8px;">
            Open Admin Sign-In
        </a>
    </p>
    <p style="margin: 0 0 12px; color: #475569;">
        For security, please sign in promptly and change your password in <strong>Account Settings</strong> after your first login.
    </p>
    <p style="margin: 0; color: #475569;">
        If you did not expect this invitation, you can ignore this email.
    </p>
</body>
</html>
