<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="color-scheme" content="light dark">
        <meta name="supported-color-schemes" content="light dark">
        <title>Your verification code</title>
        <style>
            :root { color-scheme: light dark; supported-color-schemes: light dark; }

            /* Dark-first: dark values override the light inline defaults */
            @media (prefers-color-scheme: dark) {
                .bg-page    { background:#0a0a0a !important; }
                .bg-panel   { background:#111111 !important; border-color:#262626 !important; }
                .bg-code    { background:#171717 !important; border-color:#262626 !important; }
                .rule       { border-top-color:#262626 !important; }
                .text-1     { color:#ededed !important; }
                .text-2     { color:#a1a1a1 !important; }
                .text-3     { color:#737373 !important; }
            }

            @media only screen and (max-width: 520px) {
                .container { width: 100% !important; }
                .padding   { padding: 1.5rem 1.25rem !important; }
                .code      { font-size: 1.5rem !important; letter-spacing: 0.28em !important; }
            }
        </style>
    </head>
    <body class="bg-page" style="margin:0; padding:0; background:#fafafa; font-family: 'Inter', -apple-system, 'Segoe UI', 'Helvetica Neue', Helvetica, Arial, sans-serif;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" class="bg-page" style="background:#fafafa; padding: 3rem 1rem;">
            <tr>
                <td align="center">

                    <!-- Panel: 10px radius, 1px border, no shadow -->
                    <table role="presentation" width="480" class="container bg-panel" cellpadding="0" cellspacing="0"
                        style="max-width:480px; width:100%; background:#ffffff; border:1px solid #e5e5e5; border-radius:10px;">
                        <tr>
                            <td class="padding" style="padding: 2rem 2rem 2.1rem;">

                                <h1 class="text-1" style="color:#171717; font-size:1.25rem; line-height:1.4; margin:0 0 0.4rem; font-weight:600; letter-spacing:-0.01em;">
                                    Confirm your login
                                </h1>

                                <p class="text-2" style="color:#525252; font-size:0.875rem; line-height:1.6; margin:0 0 1.5rem;">
                                    You requested to log in to <strong class="text-1" style="color:#171717; font-weight:600;">LSPDMS</strong>. Your one-time code is:
                                </p>

                                <!-- Code: 6px radius, neutral surface, monochrome -->
                                <table role="presentation" cellpadding="0" cellspacing="0" width="100%">
                                    <tr>
                                        <td align="center" class="bg-code" style="background:#f5f5f5; border:1px solid #e5e5e5; border-radius:6px; padding:1rem 0.5rem;">
                                            <div class="code text-1" style="display:block; font-size:1.625rem; font-weight:600; letter-spacing:0.4em; text-indent:0.4em; color:#171717; font-family: 'SF Mono', 'Roboto Mono', Consolas, 'Courier New', monospace; font-variant-numeric:tabular-nums; -webkit-user-select:all; user-select:all;">{{ $code }}</div>
                                        </td>
                                    </tr>
                                </table>

                                <p class="text-1" style="color:#171717; font-size:0.8125rem; font-weight:600; line-height:1.6; margin:1.25rem 0 0;">
                                    This code expires in 5 minutes.
                                </p>

                                <!-- Divider instead of another card -->
                                <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="margin-top:1.25rem;">
                                    <tr>
                                        <td class="rule" style="border-top:1px solid #e5e5e5; padding-top:1.1rem;">
                                            <p class="text-2" style="color:#525252; font-size:0.8125rem; line-height:1.65; margin:0;">
                                                If you didn't request this, you can safely ignore this email. Someone else might have typed your email address by mistake.
                                            </p>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>

                    <p class="text-3" style="max-width:480px; color:#737373; font-size:0.75rem; line-height:1.6; margin:1.25rem 0 0; text-align:center;">
                        Automated message from LSPDMS. Please don't reply to this email.
                    </p>

                </td>
            </tr>
        </table>
    </body>
</html>