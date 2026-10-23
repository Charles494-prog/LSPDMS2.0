<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Choose a new password - {{ config('app.name', 'LSPDMS') }}</title>
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

            /* Dark theme (default), matching the dashboard */
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
            min-height: 100vh;
            margin: 0;
            display: grid;
            place-items: center;
            padding: 1.5rem;
            background: var(--bg);
            color: var(--text);
            font-family: -apple-system, 'Helvetica Neue', Arial, sans-serif;
            -webkit-font-smoothing: antialiased;
        }
        .card {
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
        .intro { margin: 0 0 1.5rem; color: var(--text-muted); font-size: .9rem; line-height: 1.55; }

        /* Form */
        .field { margin-bottom: 1.1rem; }
        label { display: block; margin-bottom: .4rem; font-size: .85rem; font-weight: 600; }
        input[type="password"] {
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
        input[type="password"]:hover { border-color: var(--text-muted); }
        input[type="password"]:focus,
        input[type="password"]:focus-visible { outline: none; border-color: var(--text); }
        input[aria-invalid="true"] { border-color: var(--danger); }
        .error { margin: .35rem 0 0; color: var(--danger); font-size: .8rem; line-height: 1.4; }

        /* Button */
        .submit {
            width: 100%;
            min-height: 44px;
            margin-top: .4rem;
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
        .submit:hover:not(:disabled) { background: var(--accent-hover); border-color: var(--accent-hover); }
        .submit:active:not(:disabled) { transform: scale(.99); }
        .submit:disabled { opacity: .55; cursor: wait; }
        .submit:focus-visible { outline: 2px solid var(--border-strong); outline-offset: 2px; }

        @media (max-width: 480px) {
            body { padding: 1rem; }
            .card { padding: 1.5rem 1.2rem; }
        }
        @media (prefers-reduced-motion: reduce) {
            * { transition: none !important; }
        }
    </style>
</head>
<body>
    <x-flash-toast :message="session('status')" />
    <main class="card">
        <div class="brand">
            <span class="brand-mark"><img src="{{ asset('images/LSP_logo_white.png') }}?v={{ filemtime(public_path('images/LSP_logo_white.png')) }}" alt=""></span>
            <span class="brand-name">LSPDMS</span>
        </div>

        <h1>Choose a new password</h1>
        <p class="intro">Choose a strong password. Reset links expire after one hour.</p>

        <form method="POST" action="{{ route('password.reset') }}" id="resetForm">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <input type="hidden" name="email" value="{{ $email }}">

            <div class="field">
                <label for="password">New password</label>
                <input id="password" type="password" name="password" required autocomplete="new-password" minlength="8" autofocus @error('password') aria-invalid="true" aria-describedby="password_error" @enderror>
                @include('components.password-strength', ['inputId' => 'password'])
                @error('password')<p class="error" id="password_error">{{ $message }}</p>@enderror
            </div>

            <div class="field">
                <label for="password_confirmation">Confirm new password</label>
                <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" minlength="8">
            </div>

            <button class="submit" type="submit" id="resetSubmit">Reset password</button>
        </form>
    </main>

    <script>
        (function () {
            var form = document.getElementById('resetForm');
            var button = document.getElementById('resetSubmit');
            if (!form || !button) return;
            var idle = button.textContent;

            form.addEventListener('submit', function (e) {
                if (form.dataset.submitting === '1') { e.preventDefault(); return; }
                if (!form.checkValidity()) return;
                form.dataset.submitting = '1';
                button.disabled = true;
                button.textContent = 'Resetting…';
            });

            window.addEventListener('pageshow', function (e) {
                if (!e.persisted) return;
                delete form.dataset.submitting;
                button.disabled = false;
                button.textContent = idle;
            });
        })();
    </script>
</body>
</html>