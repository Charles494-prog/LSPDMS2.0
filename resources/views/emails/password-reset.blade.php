<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <meta name="supported-color-schemes" content="light dark">
    <title>Reset your LSPDMS password</title>
    <style>
        :root { color-scheme: light dark; supported-color-schemes: light dark; }

        @media only screen and (max-width: 520px) {
            .container { width: 100% !important; }
            .padding { padding: 1.5rem 1.25rem !important; }
            .button-link { display: block !important; text-align: center !important; }
        }

        /* Dark version. Clients that don't support this keep the light inline styles. */
        @media (prefers-color-scheme: dark) {
            body, .bg-page { background: #000000 !important; color: #ffffff !important; }
            .bg-card { background: #0a0a0a !important; border-color: #262626 !important; }
            .t-main { color: #ffffff !important; }
            .t-muted { color: #a1a1a1 !important; }
            .t-footer { color: #6f6f6f !important; }
            .rule { border-top-color: #262626 !important; }
            .btn-cell { background: #ffffff !important; }
            .button-link { background: #ffffff !important; border-color: #ffffff !important; color: #000000 !important; }
            .link { color: #ffffff !important; }
        }

        /* Outlook.com / Outlook apps dark mode */
        [data-ogsc] body, [data-ogsc] .bg-page { background: #000000 !important; }
        [data-ogsc] .bg-card { background: #0a0a0a !important; }
        [data-ogsc] .t-main, [data-ogsc] .link { color: #ffffff !important; }
        [data-ogsc] .t-muted { color: #a1a1a1 !important; }
        [data-ogsc] .btn-cell, [data-ogsc] .button-link { background: #ffffff !important; color: #000000 !important; }
    </style>
</head>
<body style="margin:0;padding:0;background:#f5f5f5;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Helvetica,Arial,sans-serif;color:#000000;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" class="bg-page" style="background:#f5f5f5;padding:3rem 1rem;">
        <tr>
            <td align="center">
                <table role="presentation" width="480" class="container bg-card" cellpadding="0" cellspacing="0" style="max-width:480px;width:100%;background:#ffffff;border:1px solid #e2e2e2;border-radius:8px;">
                    <tr>
                        <td class="padding" style="padding:2rem;">

                            <p class="t-main" style="margin:0 0 1.5rem;color:#000000;font-size:.92rem;font-weight:700;letter-spacing:-.01em;line-height:1.2;">LSPDMS</p>

                            <h1 class="t-main" style="margin:0 0 .5rem;color:#000000;font-size:1.5rem;line-height:1.2;font-weight:700;letter-spacing:-.03em;">Reset your password</h1>
                            <p class="t-muted" style="margin:0 0 1.5rem;color:#6b6b6b;font-size:.92rem;line-height:1.65;">
                                We received a request to reset the password for your LSPDMS account. Use the button below to choose a new password.
                            </p>

                            <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 1.5rem;">
                                <tr>
                                    <td align="center" class="btn-cell" style="border-radius:6px;background:#000000;">
                                        <a href="{{ $url }}" class="button-link" style="display:inline-block;padding:.8rem 1.4rem;border:1px solid #000000;border-radius:6px;color:#ffffff;font-size:.92rem;font-weight:600;line-height:1.2;text-decoration:none;">Choose a new password</a>
                                    </td>
                                </tr>
                            </table>

                            <p class="t-muted" style="margin:0;color:#6b6b6b;font-size:.84rem;line-height:1.65;">
                                This secure link expires in <strong class="t-main" style="color:#000000;">{{ $expiresIn }} minutes</strong> and can only be used once.
                            </p>
                            <p class="t-muted" style="margin:1.1rem 0 0;color:#6b6b6b;font-size:.84rem;line-height:1.65;">
                                If the button does not work, copy and paste this link into your browser:
                            </p>
                            <p class="t-main" style="margin:.45rem 0 0;overflow-wrap:anywhere;word-break:break-all;color:#000000;font-size:.78rem;line-height:1.6;">
                                <a href="{{ $url }}" class="link" style="color:#000000;text-decoration:underline;overflow-wrap:anywhere;word-break:break-all;">{{ $url }}</a>
                            </p>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:1.5rem;">
                                <tr>
                                    <td class="rule t-muted" style="border-top:1px solid #e2e2e2;padding-top:1rem;color:#6b6b6b;font-size:.8rem;line-height:1.65;">
                                        If you did not request a password reset, you can safely ignore this email. Your password will not change unless you use the link.
                                    </td>
                                </tr>
                            </table>

                        </td>
                    </tr>
                </table>
                <p class="t-footer" style="max-width:480px;margin:1rem 0 0;color:#9a9a9a;font-size:.75rem;line-height:1.6;text-align:center;">
                    Automated message from LSPDMS. Please do not reply to this email.
                </p>
            </td>
        </tr>
    </table>
</body>
</html>