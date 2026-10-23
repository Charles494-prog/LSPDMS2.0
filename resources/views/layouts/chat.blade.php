@php($authenticatedUser = $authenticatedUser ?? [])
<!DOCTYPE html>
<html lang="en" data-theme="{{ (($authenticatedUser['dark_mode'] ?? false)) ? 'dark' : 'light' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Messages') - {{ config('app.name', 'LSPDMS') }}</title>
    @include('components.brand-meta')
    @vite('resources/css/app.css')
    <style>
        /* Monochrome theme: black / white / gray, 1px line separators.
           Variable names are unchanged so child views keep working. */
        :root {
            color-scheme: light;
            --bg: #ffffff;
            --surface: #ffffff;
            --surface-raised: #f5f5f5;
            --border: #e2e2e2;
            --border-strong: #c8c8c8;
            --text: #000000;
            --text-muted: #6b6b6b;
            --accent: #000000;
            --accent-hover: #2e2e2e;
            --on-accent: #ffffff;
            --danger: #dc2626;
        }
        html[data-theme="dark"] {
            color-scheme: dark;
            --bg: #000000;
            --surface: #000000;
            --surface-raised: #0d0d0d;
            --border: #262626;
            --border-strong: #3a3a3a;
            --text: #ffffff;
            --text-muted: #9a9a9a;
            --accent: #ffffff;
            --accent-hover: #d9d9d9;
            --on-accent: #000000;
            --danger: #f87171;
        }
        * { box-sizing: border-box; }
        html, body { width: 100%; height: 100%; margin: 0; overflow: hidden; }
        body {
            display: flex;
            background: var(--bg);
            color: var(--text);
            font-family: -apple-system, 'Helvetica Neue', Arial, sans-serif;
            -webkit-font-smoothing: antialiased;
        }
        .chat-frame-content { display: flex; width: 100%; min-width: 0; min-height: 0; padding: 0; }

       :focus-visible { outline: 2px solid var(--border-strong); outline-offset: 2px; }
textarea:focus-visible,
input:focus-visible { outline: none; border-color: var(--text-muted); }
        ::selection { background: var(--text); color: var(--bg); }

        /* Thin, neutral scrollbars */
        * { scrollbar-width: thin; scrollbar-color: var(--border-strong) transparent; }
        *::-webkit-scrollbar { width: 8px; height: 8px; }
        *::-webkit-scrollbar-thumb { background: var(--border-strong); border-radius: 0; }
        *::-webkit-scrollbar-track { background: transparent; }
    </style>
    @stack('styles')
</head>
<body class="chat-frame-page">
    <x-flash-toast :message="session('status')" />
    <main class="chat-frame-content">
        @yield('content')
    </main>
    @stack('scripts')
</body>
</html>