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
        :root {
            color-scheme: light;
            --bg: #f6f8fa;
            --surface: #fff;
            --border: #e3e8ee;
            --text: #101828;
            --text-muted: #64748b;
            --accent: #0a6bd6;
            --danger: #dc2626;
        }
        html[data-theme="dark"] {
            color-scheme: dark;
            --bg: #0b1220;
            --surface: #141b2d;
            --border: #263043;
            --text: #e5e9f0;
            --text-muted: #93a0b4;
            --accent: #3b93ff;
            --danger: #f87171;
        }
        * { box-sizing: border-box; }
        html, body { width: 100%; height: 100%; margin: 0; overflow: hidden; }
        body { display: flex; background: var(--bg); color: var(--text); font-family: -apple-system, 'Helvetica Neue', Arial, sans-serif; }
        .chat-frame-content { display: flex; width: 100%; min-width: 0; min-height: 0; padding: 0; }
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
