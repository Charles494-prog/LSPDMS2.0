<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <title>{{ config('app.name', 'LSPDMS') }}</title>
        @include('components.brand-meta')
        <script>
            (function () {
                var mq = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)');
                function apply() { document.documentElement.setAttribute('data-theme', mq && mq.matches ? 'dark' : 'light'); }
                apply();
                if (mq && mq.addEventListener) mq.addEventListener('change', apply);
            })();
        </script>

        <style>
            :root {
                color-scheme: light;
                --bg: #fafafa;
                --surface: #ffffff;
                --line: #eaeaea;
                --line-strong: #d4d4d4;
                --ink: #171717;
                --ink-on: #ffffff;
                --muted: #666666;
                --placeholder: #8a8a8a;
                --hover: #f5f5f5;
                --ink-hover: #404040;
                --danger: #cf222e;
                --danger-bg: #fff5f5;
                --logo-filter: brightness(0);
                /* Same token names as the app layout, so shared components (flash toast) follow the theme */
                --text: #171717;
                --text-muted: #666666;
                --border: #eaeaea;
                --accent: #0070f3;
                --accent-hover: #0060d1;
            }

            :root[data-theme="dark"] {
                color-scheme: dark;
                --bg: #000000;
                --surface: #0a0a0a;
                --line: #262626;
                --line-strong: #3a3a3a;
                --ink: #ededed;
                --ink-on: #000000;
                --muted: #a1a1a1;
                --placeholder: #7a7a7a;
                --hover: #1a1a1a;
                --ink-hover: #cfcfcf;
                --danger: #ff6369;
                --danger-bg: #1a0b0c;
                --logo-filter: none;
                --text: #ededed;
                --text-muted: #a1a1a1;
                --border: #262626;
                --accent: #3291ff;
                --accent-hover: #52a8ff;
            }

            @media (prefers-color-scheme: dark) {
                :root:not([data-theme="light"]) {
                    color-scheme: dark;
                    --bg: #000000;
                    --surface: #0a0a0a;
                    --line: #262626;
                    --line-strong: #3a3a3a;
                    --ink: #ededed;
                    --ink-on: #000000;
                    --muted: #a1a1a1;
                    --placeholder: #7a7a7a;
                    --hover: #1a1a1a;
                    --ink-hover: #cfcfcf;
                    --danger: #ff6369;
                    --danger-bg: #1a0b0c;
                    --logo-filter: none;
                    --text: #ededed;
                    --text-muted: #a1a1a1;
                    --border: #262626;
                    --accent: #3291ff;
                    --accent-hover: #52a8ff;
                }
            }

            * { box-sizing: border-box; }

            html, body { margin: 0; }

            body {
                min-height: 100vh;
                min-height: 100dvh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: max(1rem, env(safe-area-inset-top, 0px)) max(1rem, env(safe-area-inset-right, 0px)) max(1rem, env(safe-area-inset-bottom, 0px)) max(1rem, env(safe-area-inset-left, 0px));
                font-family: 'Geist', -apple-system, 'Segoe UI', 'Helvetica Neue', Arial, sans-serif;
                -webkit-font-smoothing: antialiased;
                background: var(--bg);
                color: var(--ink);
            }

            .login-shell { width: min(100%, 400px); }

            .login-card {
                background: var(--surface);
                border: 1px solid var(--line);
                border-radius: 8px;
                padding: 2rem 1.75rem 1.5rem;
            }

            /* ---------- Brand ---------- */
            .brand {
                display: flex;
                flex-direction: column;
                align-items: flex-start;
                gap: 0.6rem;
                margin-bottom: 1.5rem;
                padding-bottom: 1.4rem;
                border-bottom: 1px solid var(--line);
            }
            .brand-logo {
                display: block;
                width: 140px;
                max-width: 100%;
                height: auto;
                object-fit: contain;
                filter: var(--logo-filter);
            }
            .brand p { margin: 0; font-size: 0.85rem; color: var(--muted); }

            /* ---------- Form ---------- */
            .login-form { display: flex; flex-direction: column; gap: 1.1rem; }

            .field { display: flex; flex-direction: column; gap: 0.4rem; }

            label.field-label {
                font-size: 0.85rem;
                font-weight: 600;
                color: var(--ink);
            }

            .input-wrap { position: relative; }

            input[type="email"],
            input[type="password"],
            input[type="text"] {
                width: 100%;
                border: 1px solid var(--line-strong);
                background: transparent;
                color: var(--ink);
                border-radius: 6px;
                padding: 0.7rem 0.8rem;
                font: inherit;
                font-size: 1rem;
                outline: none;
                transition: border-color 0.15s ease;
            }
            input::placeholder { color: var(--placeholder); }
            .password-field input { padding-right: 2.8rem; }
            input:focus { border-color: var(--ink); }
            input:focus-visible { outline: 2px solid var(--ink); outline-offset: 2px; }
            input.input-error,
            input[aria-invalid="true"] { border-color: var(--danger); }

            .toggle-password {
                position: absolute;
                top: 50%;
                right: 0.35rem;
                transform: translateY(-50%);
                width: 36px;
                height: 36px;
                display: flex;
                align-items: center;
                justify-content: center;
                background: none;
                border: 0;
                border-radius: 6px;
                color: var(--muted);
                cursor: pointer;
                padding: 0;
            }
            .toggle-password:hover { color: var(--ink); background: var(--hover); }
            .toggle-password:focus-visible { outline: 2px solid var(--ink); outline-offset: 0; color: var(--ink); }
            .toggle-password svg { width: 18px; height: 18px; }

            .login-error {
                margin: 0;
                display: flex;
                gap: 0.55rem;
                align-items: flex-start;
                padding: 0.65rem 0.75rem;
                border: 1px solid var(--danger);
                border-radius: 6px;
                background: var(--danger-bg);
                color: var(--danger);
                font-size: 0.82rem;
                font-weight: 500;
                line-height: 1.4;
                overflow-wrap: anywhere;
            }
            .login-error[hidden] { display: none; }
            .login-error::before {
                content: "!";
                flex: none;
                display: grid;
                place-items: center;
                width: 16px;
                height: 16px;
                margin-top: 1px;
                border: 1.5px solid currentColor;
                border-radius: 50%;
                font-size: 0.65rem;
                font-weight: 800;
                line-height: 1;
            }

            .row {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 0.8rem;
                flex-wrap: wrap;
            }

            .remember {
                display: inline-flex;
                align-items: center;
                gap: 0.5rem;
                color: var(--muted);
                font-size: 0.85rem;
                cursor: pointer;
            }
            .remember input { width: 16px; height: 16px; margin: 0; accent-color: var(--ink); }
            .remember input:focus-visible { outline: 2px solid var(--ink); outline-offset: 2px; }

            .link {
                color: var(--muted);
                font-size: 0.85rem;
                text-decoration: none;
                border-radius: 2px;
            }
            .link:hover { color: var(--ink); text-decoration: underline; }
            .link:focus-visible { outline: 2px solid var(--ink); outline-offset: 2px; }

            .btn {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 0.55rem;
                min-height: 44px;
                border: 1px solid var(--ink);
                border-radius: 6px;
                background: var(--ink);
                color: var(--ink-on);
                padding: 0.7rem 1.2rem;
                font: inherit;
                font-size: 0.92rem;
                font-weight: 600;
                cursor: pointer;
                transition: background 0.15s ease, border-color 0.15s ease;
            }
            .btn:hover:not([disabled]) { background: var(--ink-hover); border-color: var(--ink-hover); }
            .btn:focus-visible { outline: 2px solid var(--ink); outline-offset: 2px; }
            .btn[disabled] { opacity: 0.6; cursor: not-allowed; }
            .btn .spinner {
                display: none;
                width: 14px;
                height: 14px;
                border: 2px solid currentColor;
                border-right-color: transparent;
                border-radius: 50%;
                animation: spin 0.7s linear infinite;
            }
            .btn[aria-busy="true"] .spinner { display: inline-block; }

            .register-row {
                margin: 0;
                padding-top: 1.1rem;
                border-top: 1px solid var(--line);
                text-align: center;
                font-size: 0.85rem;
                color: var(--muted);
            }
            .register-row a {
                color: var(--ink);
                font-weight: 600;
                text-decoration: none;
                margin-left: 0.3rem;
                border-radius: 2px;
            }
            .register-row a:hover { text-decoration: underline; }
            .register-row a:focus-visible { outline: 2px solid var(--ink); outline-offset: 2px; }

            .sr-only { position: absolute; width: 1px; height: 1px; margin: -1px; padding: 0; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; border: 0; }

            @keyframes spin { to { transform: rotate(360deg); } }

            @media (max-width: 480px) {
                .login-card { padding: 1.6rem 1.2rem 1.3rem; }
            }

            @media (prefers-reduced-motion: reduce) {
                input, .btn, .toggle-password { transition: none; }
                .btn .spinner { animation-duration: 1.6s; }
            }
        </style>
    </head>
    <body>
        <x-flash-toast :message="session('status') ?: (request()->query('access')
            ? (request()->query('access') === 'password_changed'
                ? 'Change of Password Detected, Please Login Again.'
                : 'Your account was signed out. Please log in again.')
            : null)" />
        <main class="login-shell" id="loginShell">
            <div class="login-card">
                <div class="brand">
                    <img class="brand-logo" src="{{ asset('images/LSP_logo_white.png') }}" alt="LSPDMS — LSP's Data Management System">
                    <p>Secure access</p>
                </div>

                <form class="login-form" id="loginForm" method="POST" action="{{ url('/login') }}" data-login-url="{{ route('login.form') }}" data-csrf-refresh-url="{{ route('login.csrf-token') }}" novalidate>
                    @csrf
                    <div class="field">
                        <label class="field-label" for="email">Email address</label>
                        <input id="email" type="email" name="email" inputmode="email" autocomplete="username" autocapitalize="none" spellcheck="false" placeholder="name@example.com" value="{{ old('email') }}" class="{{ $errors->has('login') ? 'input-error' : '' }}" @if ($errors->has('login')) aria-invalid="true" aria-describedby="loginServerError" @endif required autofocus />
                    </div>

                    <div class="field password-field">
                        <label class="field-label" for="password">Password</label>
                        <div class="input-wrap">
                            <input id="password" type="password" name="password" autocomplete="current-password" placeholder="••••••••" class="{{ $errors->has('login') ? 'input-error' : '' }}" @if ($errors->has('login')) aria-invalid="true" aria-describedby="loginServerError" @endif required />
                            <button type="button" class="toggle-password" id="togglePassword" aria-label="Show password" aria-controls="password" aria-pressed="false">
                                <svg id="eyeIcon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/>
                                    <circle cx="12" cy="12" r="3"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    @error('login')
                        <p class="login-error" id="loginServerError">{{ $message }}</p>
                    @enderror
                    <p class="login-error" id="loginRequestError" role="alert" hidden></p>

                    @if (request()->query('expired'))
                        <p class="login-error">Session expired. Please try again.</p>
                    @endif
                    @if (request()->query('access') === 'revoked')
                        <p class="login-error">Your account access was disabled. Contact the head secretary if you need access restored.</p>
                    @endif

                    <div class="row">
                        <label class="remember">
                            <input type="checkbox" name="remember" value="1" />
                            <span>Remember me</span>
                        </label>
                        @if ($allowForgotPassword ?? true)
                            <a href="{{ route('password.request') }}" class="link">Forgot password?</a>
                        @endif
                    </div>

                    <button type="submit" class="btn" id="loginButton">
                        <span class="spinner" aria-hidden="true"></span>
                        <span id="loginButtonLabel">Sign in</span>
                    </button>

                    <p class="register-row">Don't have an account?
                        <a href="{{ url('/register') }}">Register</a>
                    </p>
                </form>
            </div>
        </main>

        <script>
            // Password show/hide toggle
            const togglePassword = document.getElementById('togglePassword');
            const emailInput = document.getElementById('email');
            const passwordInput = document.getElementById('password');
            const eyeIcon = document.getElementById('eyeIcon');
            const loginForm = document.getElementById('loginForm');
            const loginRequestError = document.getElementById('loginRequestError');
            const loginButton = document.getElementById('loginButton');
            const loginButtonLabel = document.getElementById('loginButtonLabel');

            const eyeOpenPaths = `<path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/>`;
            const eyeClosedPaths = `<path d="M17.94 17.94A10.94 10.94 0 0112 19c-7 0-11-7-11-7a21.6 21.6 0 015.06-6.06"/><path d="M9.9 4.24A10.94 10.94 0 0112 4c7 0 11 7 11 7a21.6 21.6 0 01-3.22 4.44"/><path d="M14.12 14.12a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>`;

            togglePassword.addEventListener('click', () => {
                const showing = passwordInput.type === 'text';
                passwordInput.type = showing ? 'password' : 'text';
                eyeIcon.innerHTML = showing ? eyeOpenPaths : eyeClosedPaths;
                togglePassword.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
                togglePassword.setAttribute('aria-pressed', String(!showing));
            });

            let submitting = false;

            function setPending(pending) {
                submitting = pending;
                loginButton.disabled = pending;
                loginButton.setAttribute('aria-busy', String(pending));
                loginButtonLabel.textContent = pending ? 'Signing in…' : 'Sign in';
            }

            function showRequestError(message) {
                loginRequestError.textContent = message;
                loginRequestError.hidden = false;
                [emailInput, passwordInput].forEach((input) => {
                    input.setAttribute('aria-invalid', 'true');
                    input.setAttribute('aria-describedby', 'loginRequestError');
                });
                setPending(false);
                // Keep what the user typed; put focus where a retry usually starts.
                passwordInput.focus();
            }

            function clearRequestError() {
                loginRequestError.hidden = true;
                loginRequestError.textContent = '';
                [emailInput, passwordInput].forEach((input) => {
                    input.classList.remove('input-error');
                    input.removeAttribute('aria-invalid');
                    input.removeAttribute('aria-describedby');
                });
                document.getElementById('loginServerError')?.setAttribute('hidden', '');
            }

            [emailInput, passwordInput].forEach((input) => input.addEventListener('input', clearRequestError));

            // Restoring the page from the back/forward cache must not leave the button stuck.
            window.addEventListener('pageshow', (event) => { if (event.persisted) setPending(false); });

            loginForm.addEventListener('submit', async event => {
                event.preventDefault();
                if (submitting) return;

                if (!loginForm.checkValidity()) {
                    loginForm.reportValidity();
                    return;
                }

                setPending(true);
                loginRequestError.hidden = true;

                const submitLogin = formData => fetch(loginForm.action, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin',
                    headers: { 'Accept': 'text/html' },
                    redirect: 'follow',
                    cache: 'no-store',
                });

                try {
                    const formData = new FormData(loginForm);
                    let response = await submitLogin(formData);
                    let refreshAttempts = 0;

                    while (response.status === 419 && refreshAttempts < 2) {
                        refreshAttempts++;
                        const tokenResponse = await fetch(loginForm.dataset.csrfRefreshUrl, {
                            credentials: 'same-origin',
                            headers: { 'Accept': 'application/json' },
                            cache: 'no-store',
                        });
                        if (!tokenResponse.ok) throw new Error('Unable to refresh your sign-in session. Reload this page and try again.');

                        const { token } = await tokenResponse.json();
                        if (!token) throw new Error('A fresh security token could not be loaded. Reload this page and try again.');
                        loginForm.querySelector('input[name="_token"]').value = token;
                        formData.set('_token', token);
                        response = await submitLogin(formData);
                    }

                    if (response.redirected) {
                        const responseUrl = new URL(response.url);
                        const loginUrl = new URL(loginForm.dataset.loginUrl, window.location.href);
                        if (responseUrl.pathname === loginUrl.pathname) {
                            const responseDocument = new DOMParser().parseFromString(await response.text(), 'text/html');
                            const responseError = responseDocument.querySelector('.login-error:not([hidden])');
                            showRequestError(responseError?.textContent.trim()
                                || 'Invalid username or password.');
                            return;
                        }
                        window.location.assign(response.url);
                        return;
                    }

                    if (response.status === 419) {
                        throw new Error('Your sign-in session changed again. Reload this page and try once more.');
                    }

                    if (response.status === 429) {
                        throw new Error('Too many sign-in attempts. Please wait a moment and try again.');
                    }

                    if (!response.ok) {
                        throw new Error('Unable to sign in right now. Please try again.');
                    }

                    throw new Error('Sign-in could not be completed. Please try again.');
                } catch (error) {
                    // fetch() rejects with a TypeError when the request never reaches the server.
                    const message = error instanceof TypeError
                        ? 'Could not reach the server. Please try again.'
                        : (error.message || 'Unable to sign in right now. Please try again.');
                    showRequestError(message);
                }
            });
        </script>
    </body>
</html>