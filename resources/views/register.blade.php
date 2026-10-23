<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Create an account · {{ config('app.name', 'LSPDMS') }}</title>
    @include('components.brand-meta')
    <style>
        :root {
            /* Corner control: change these two numbers to restyle the whole page */
            --radius-card: 8px;
            --radius-control: 6px;

            color-scheme: light dark;
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
        @media (prefers-color-scheme: dark) {
            :root {
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
        }
        html[data-theme="light"] { color-scheme: light; --bg:#ffffff; --surface:#ffffff; --surface-raised:#f5f5f5; --border:#e2e2e2; --border-strong:#c8c8c8; --text:#000000; --text-muted:#6b6b6b; --accent:#000000; --accent-hover:#2e2e2e; --on-accent:#ffffff; --danger:#dc2626; }
        html[data-theme="dark"]  { color-scheme: dark;  --bg:#000000; --surface:#000000; --surface-raised:#0d0d0d; --border:#262626; --border-strong:#3a3a3a; --text:#ffffff; --text-muted:#9a9a9a; --accent:#ffffff; --accent-hover:#d9d9d9; --on-accent:#000000; --danger:#f87171; }

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
            width: min(100%, 460px);
            padding: 2rem;
            border: 1px solid var(--border);
            border-radius: var(--radius-card);
            background: var(--surface);
        }

        /* Heading */
        .brand { display: flex; align-items: center; gap: .7rem; margin-bottom: 1.6rem; }
        .brand-mark { display: grid; width: 36px; height: 36px; flex: none; place-items: center; overflow: hidden; border: 1px solid var(--border-strong); border-radius: 50%; background: #000; }
        .brand-mark img { width: 100%; height: 100%; padding: 5px; object-fit: contain; }
        .brand-name { font-size: .92rem; font-weight: 700; letter-spacing: -.01em; }
        h1 { margin: 0 0 .4rem; font-size: 1.5rem; font-weight: 700; letter-spacing: -.03em; line-height: 1.15; }
        .intro { margin: 0 0 1.5rem; color: var(--text-muted); font-size: .9rem; line-height: 1.55; }

        /* Alert */
        .notice { margin-bottom: 1.2rem; padding: .7rem .85rem; border: 1px solid var(--danger); border-radius: var(--radius-control); color: var(--danger); font-size: .85rem; line-height: 1.5; }

        /* Form */
        .fields { display: grid; grid-template-columns: 1fr 1fr; gap: 0 1rem; }
        .field { margin-bottom: 1.1rem; }
        label { display: block; margin-bottom: .4rem; font-size: .85rem; font-weight: 600; }
        input {
            width: 100%;
            min-height: 44px;
            padding: .7rem .8rem;
            border: 1px solid var(--border-strong);
            border-radius: var(--radius-control);
            background: transparent;
            color: var(--text);
            font: inherit;
            font-size: .94rem;
            transition: border-color .15s ease;
        }
        input:hover { border-color: var(--text-muted); }
        input:focus, input:focus-visible { outline: none; border-color: var(--text); }
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

        /* Footer */
        .footer { margin: 1.4rem 0 0; padding-top: 1.2rem; border-top: 1px solid var(--border); color: var(--text-muted); font-size: .85rem; text-align: center; }
        a { color: var(--text); font-weight: 600; text-decoration: underline; text-underline-offset: 3px; text-decoration-color: var(--border-strong); }
        a:hover { text-decoration-color: var(--text); }
        a:focus-visible { outline: 2px solid var(--border-strong); outline-offset: 2px; border-radius: 2px; }

        @media (max-width: 480px) {
            body { padding: 1rem; }
            .card { padding: 1.5rem 1.2rem; }
            .fields { grid-template-columns: 1fr; }
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

        <h1>Create your account</h1>
        <p class="intro">Submit your details to request an account. The Head Secretary must grant access before you can sign in.</p>

        @if ($errors->any())
            <div class="notice" role="alert">Please correct the highlighted fields and submit again.</div>
        @endif

        <form method="POST" action="{{ route('register.store') }}" id="registerForm" novalidate>
            @csrf
            <div class="fields">
                <div class="field">
                    <label for="first_name">First name</label>
                    <input id="first_name" name="first_name" type="text" value="{{ old('first_name') }}" maxlength="100" autocomplete="given-name" required autofocus @error('first_name') aria-invalid="true" aria-describedby="first_name_error" @enderror>
                    @error('first_name')<p class="error" id="first_name_error">{{ $message }}</p>@enderror
                </div>
                <div class="field">
                    <label for="last_name">Last name</label>
                    <input id="last_name" name="last_name" type="text" value="{{ old('last_name') }}" maxlength="100" autocomplete="family-name" required @error('last_name') aria-invalid="true" aria-describedby="last_name_error" @enderror>
                    @error('last_name')<p class="error" id="last_name_error">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="field">
                <label for="email">Email address</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" maxlength="255" autocomplete="email" required @error('email') aria-invalid="true" aria-describedby="email_error" @enderror>
                @error('email')<p class="error" id="email_error">{{ $message }}</p>@enderror
            </div>

            <div class="field">
                <label for="password">Strong password</label>
                <input id="password" name="password" type="password" minlength="8" autocomplete="new-password" required @error('password') aria-invalid="true" aria-describedby="password_error" @enderror>
                @include('components.password-strength', ['inputId' => 'password'])
                @error('password')<p class="error" id="password_error">{{ $message }}</p>@enderror
            </div>

            <div class="field">
                <label for="password_confirmation">Confirm password</label>
                <input id="password_confirmation" name="password_confirmation" type="password" minlength="8" autocomplete="new-password" required>
            </div>

            <button class="submit" type="submit" id="registerSubmit">Submit registration</button>
        </form>

        <p class="footer">Already have an account? <a href="{{ route('login.form') }}">Return to login</a></p>
    </main>

    <script>
        (function () {
            var form = document.getElementById('registerForm');
            var button = document.getElementById('registerSubmit');
            if (!form || !button) return;
            var idle = button.textContent;

            form.addEventListener('submit', function (e) {
                if (form.dataset.submitting === '1') { e.preventDefault(); return; }
                if (!form.checkValidity()) return;
                form.dataset.submitting = '1';
                button.disabled = true;
                button.textContent = 'Submitting…';
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