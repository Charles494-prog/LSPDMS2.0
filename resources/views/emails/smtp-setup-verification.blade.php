@php
    // Dark email: use the white logo. Looks in public/images, then public/image.
    $logoPath = collect([
        public_path('images/LSP_logo_white.png'),
        public_path('image/LSP_logo_white.png'),
    ])->first(fn ($path) => is_file($path));

    // Keep the logo's real proportions (fixed height, width from the image).
    $logoHeight = 40;
    $logoWidth = $logoHeight;
    if ($logoPath && ($size = @getimagesize($logoPath)) && $size[1] > 0) {
        $logoWidth = (int) round($logoHeight * $size[0] / $size[1]);
    }
@endphp
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="color-scheme" content="dark">
        <meta name="supported-color-schemes" content="dark">
        <title>LSPDMS mailing setup verification</title>
        <style>
            :root { color-scheme: dark; supported-color-schemes: dark; }

            @media only screen and (max-width: 520px) {
                .container { width: 100% !important; }
                .padding   { padding: 1.5rem 1.25rem !important; }
                .code      { font-size: 1.5rem !important; letter-spacing: 0.28em !important; }
            }
        </style>
    </head>
    <body bgcolor="#0a0a0a" style="margin:0; padding:0; background:#0a0a0a; font-family: 'Inter', -apple-system, 'Segoe UI', 'Helvetica Neue', Helvetica, Arial, sans-serif;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" bgcolor="#0a0a0a" style="background:#0a0a0a; padding: 3rem 1rem;">
            <tr>
                <td align="center">

                    <table role="presentation" width="480" class="container" cellpadding="0" cellspacing="0" bgcolor="#111111"
                        style="max-width:480px; width:100%; background:#111111; border:1px solid #262626; border-radius:10px;">
                        <tr>
                            <td class="padding" style="padding: 2rem 2rem 2.1rem;">

                                @if ($logoPath)
                                    <img src="{{ $message->embed($logoPath) }}"
                                        width="{{ $logoWidth }}" height="{{ $logoHeight }}" alt="LSPDMS"
                                        style="display:block; width:{{ $logoWidth }}px; height:{{ $logoHeight }}px; margin:0 0 1.25rem; border:0;">
                                @endif

                                <h1 style="color:#ededed; font-size:1.25rem; line-height:1.4; margin:0 0 0.4rem; font-weight:600; letter-spacing:-0.01em;">
                                    Verify your mailing setup
                                </h1>

                                <p style="color:#a1a1a1; font-size:0.875rem; line-height:1.6; margin:0 0 1.5rem;">
                                    <a href="mailto:{{ $senderEmail }}" style="color:#ededed; font-weight:600; text-decoration:none;">{{ $senderEmail }}</a>
                                    is being set up as the sender for LSPDMS system emails. Enter this code in the Email delivery settings to confirm:
                                </p>

                                <table role="presentation" cellpadding="0" cellspacing="0" width="100%">
                                    <tr>
                                        <td align="center" bgcolor="#171717" style="background:#171717; border:1px solid #262626; border-radius:6px; padding:1rem 0.5rem;">
                                            <div class="code" style="display:block; font-size:1.625rem; font-weight:600; letter-spacing:0.4em; text-indent:0.4em; color:#ededed; font-family: 'SF Mono', 'Roboto Mono', Consolas, 'Courier New', monospace; font-variant-numeric:tabular-nums;">{{ $code }}</div>
                                        </td>
                                    </tr>
                                </table>

                                <p style="color:#ededed; font-size:0.8125rem; font-weight:600; line-height:1.6; margin:1.25rem 0 0;">
                                    This code expires in {{ $minutes }} minutes.
                                </p>

                                <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="margin-top:1.25rem;">
                                    <tr>
                                        <td style="border-top:1px solid #262626; padding-top:1.1rem;">
                                            <p style="color:#a1a1a1; font-size:0.8125rem; line-height:1.65; margin:0;">
                                                If you didn't request this, you can safely ignore this email. Nothing changes until the code is entered.
                                            </p>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>

                    <p style="max-width:480px; color:#737373; font-size:0.75rem; line-height:1.6; margin:1.25rem 0 0; text-align:center;">
                        Automated message from LSPDMS. Please don't reply to this email.
                    </p>

                </td>
            </tr>
        </table>
    </body>
</html>