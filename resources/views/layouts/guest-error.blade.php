<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>@yield('title', 'LSPDMS')</title>
        {{-- Error pages for visitors without a signed-in session: no sidebar, chat or session polling. --}}
        <style>
            :root {
                --bg: #fafafa;
                --surface: #ffffff;
                --border: #eaeaea;
                --text: #171717;
                --text-muted: #666666;
                --accent: #0070f3;
            }
            @media (prefers-color-scheme: dark) {
                :root {
                    --bg: #000000;
                    --surface: #0a0a0a;
                    --border: #262626;
                    --text: #ededed;
                    --text-muted: #a1a1a1;
                    --accent: #3291ff;
                }
            }
            body { margin: 0; background: var(--bg); color: var(--text); font-family: system-ui, -apple-system, "Segoe UI", sans-serif; }
        </style>
    </head>
    <body>
        @yield('content')
    </body>
</html>
