<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Forgot password - {{ config('app.name', 'LSPDMS') }}</title>
    @include('components.brand-meta')
    <script>
        /* Dark is the default. A saved light preference still wins. */
        try {
            var saved = localStorage.getItem('theme');
            if (saved === 'light' || saved === 'dark') document.documentElement.setAttribute('data-theme', saved);
        } catch (e) {}
    </script>
    <style>
        :root {
            /* Corner control: change these two numbers to restyle the whole page */
            --radius-card: 10px;
            --radius-control: 6px;

            /* Dark theme (default), matching the dashboard and the reset page */
            color-scheme: dark;
            --bg: #000000;
            --surface: #0a0a0a;
            --surface-raised: #171717;
            --border: #262626;
            --border-strong: #3a3a3a;
            --text: #ffffff;
            --text-muted: #a1a1a1;
            --accent: #ffffff;
            --accent-hover: #d9d9d9;
            --on-accent: #000000;
            --danger: #f87171;
        }
        html[data-theme="light"] {
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

        * { box-sizing: border-box; }
        body {
            display: grid;
            min-height: 100vh;
            place-items: center;
            margin: 0;
            padding: 1.5rem;
            background: var(--bg);
            color: var(--text);
            font-family: -apple-system, 'Helvetica Neue', Arial, sans-serif;
            -webkit-font-smoothing: antialiased;
        }
        main {
            width: min(100%, 440px);
            padding: 2rem;
            border: 1px solid var(--border);
            border-radius: var(--radius-card);
            background: var(--surface);
        }

        /* Heading */
        .brand { display: flex; align-items: center; gap: .7rem; margin-bottom: 1.6rem; }
        .brand-mark { display: grid; width: 36px; height: 36px; flex: none; place-items: center; overflow: hidden; border: 1px solid var(--border-strong); border-radius: 50%; background: #000; }
        .brand-mark img { width: 100%; height: 100%; padding: 5px; object-fit: contain; }
        .brand-name { font-size: .92rem; font-weight: 650; letter-spacing: -.01em; }
        h1 { margin: 0 0 .4rem; font-size: 1.5rem; font-weight: 650; letter-spacing: -.03em; line-height: 1.15; }
        p { margin: 0; color: var(--text-muted); font-size: .9rem; line-height: 1.55; }
        .intro { margin-bottom: 1.5rem; }

        /* Form */
        label { display: block; margin-bottom: .4rem; font-size: .85rem; font-weight: 600; }
        input[type="email"] {
            width: 100%;
            min-height: 44px;
            padding: .7rem .8rem;
            border: 1px solid var(--border-strong);
            border-radius: var(--radius-control);
            background: var(--bg);
            color: var(--text);
            font: inherit;
            font-size: .94rem;
            transition: border-color .15s ease;
        }
        input[type="email"]:hover { border-color: var(--text-muted); }
        input[type="email"]:focus,
        input[type="email"]:focus-visible { outline: none; border-color: var(--text); }
        input[aria-invalid="true"] { border-color: var(--danger); }
        .error { margin-top: .4rem; color: var(--danger); font-size: .8rem; line-height: 1.4; }

        /* Button */
        button {
            width: 100%;
            min-height: 44px;
            margin-top: 1.1rem;
            padding: .75rem 1rem;
            border: 1px solid var(--accent);
            border-radius: var(--radius-control);
            background: var(--accent);
            color: var(--on-accent);
            font: inherit;
            font-size: .92rem;
            font-weight: 600;
            cursor: pointer;
            transition: background .15s ease, border-color .15s ease, transform .15s ease;
        }
        button:hover:not(:disabled) { background: var(--accent-hover); border-color: var(--accent-hover); }
        button:active:not(:disabled) { transform: scale(.99); }
        button:disabled { opacity: .5; cursor: not-allowed; }
        button:focus-visible { outline: 2px solid var(--border-strong); outline-offset: 2px; }

        /* Back link */
        .back { display: block; margin-top: 1.1rem; color: var(--text-muted); font-size: .86rem; font-weight: 600; text-align: center; text-decoration: none; }
        .back:hover { color: var(--text); text-decoration: underline; text-underline-offset: 3px; }
        .back:focus-visible { outline: 2px solid var(--border-strong); outline-offset: 2px; }

        /* Link sent */
        .confirmation { text-align: center; }
        .confirmation-mark { display: grid; width: 44px; height: 44px; margin: 0 auto 1rem; place-items: center; border: 1px solid var(--border-strong); border-radius: 50%; color: var(--text); }
        .confirmation-mark svg { width: 20px; height: 20px; }
        .confirmation h1 { margin-bottom: .5rem; }
        .confirmation .error { margin: .9rem 0 0; }
        .countdown { min-height: 1.4em; margin-top: .7rem; font-size: .82rem; font-variant-numeric: tabular-nums; }

        @media (max-width: 480px) {
            body { padding: 1rem; }
            main { padding: 1.5rem 1.2rem; }
        }
        @media (prefers-reduced-motion: reduce) {
            * { transition: none !important; }
        }
    </style>
</head>
<body>
<x-flash-toast :message="session('status')" />
<main>
    <div class="brand">
        <span class="brand-mark"><img src="{{ asset('images/LSP_logo_white.png') }}?v={{ filemtime(public_path('images/LSP_logo_white.png')) }}" alt=""></span>
        <span class="brand-name">LSPDMS</span>
    </div>

    @if ($isSentScreen)
    <section class="confirmation" aria-live="polite">
        <span class="confirmation-mark" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12.5 4.5 4.5L19 7.5"/></svg>
        </span>
        <h1>Link sent!</h1>
        <p>Please check your email inbox.</p>
        @error('email') <p class="error">{{ $message }}</p> @enderror
        <form method="POST" action="{{ route('password.email.resend') }}">
            @csrf
            <button id="resendButton" type="submit" disabled>Send link again</button>
            <p class="countdown" id="resendCountdown" data-seconds="{{ $resendAvailableIn }}"></p>
        </form>
        <a class="back" href="{{ url('/login') }}">Go back to login</a>
    </section>
    <script>
        (() => {
            const button = document.getElementById('resendButton');
            const countdown = document.getElementById('resendCountdown');
            let remaining = Number(countdown.dataset.seconds) || 0;
            const render = () => {
                if (remaining <= 0) {
                    button.disabled = false;
                    countdown.textContent = 'You can request another link now.';
                    return;
                }
                button.disabled = true;
                const minutes = Math.floor(remaining / 60);
                const seconds = remaining % 60;
                countdown.textContent = `Send another link in ${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
                remaining -= 1;
                window.setTimeout(render, 1000);
            };
            render();
        })();
    </script>
    @else
    <h1>Reset your password</h1>
    <p class="intro">Enter the email address for your verified account. If it matches an active account, we’ll send a password reset link.</p>
    <form method="POST" action="{{ route('password.email') }}">
        @csrf
        <label for="email">Email address</label>
        <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="email" autofocus @error('email') aria-invalid="true" aria-describedby="email_error" @enderror>
        @error('email') <p class="error" id="email_error">{{ $message }}</p> @enderror
        <button type="submit">Send reset link</button>
    </form>
    <a class="back" href="{{ url('/login') }}">Back to login</a>
    @endif
</main>
</body>
</html>