<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'LSPDMS') }}</title>
        @include('components.brand-meta')

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

        <style>
            :root {
                --page-bg: #ffffff;
                --page-bg-deep: #f4f9ff;
                --panel: #ffffff;
                --panel-border: rgba(25, 81, 145, 0.10);
                --text: #0d2339;
                --muted: #6b8299;
                --primary: #0a6bd6;
                --primary-soft: #eaf3ff;
                --primary-deep: #0a4a8a;
                --line: rgba(12, 60, 110, 0.12);
                --shadow: rgba(15, 76, 129, 0.10);
            }

            * { box-sizing: border-box; }

            html, body {
                margin: 0;
                min-height: 100%;
                font-family: 'Montserrat', sans-serif;
                background: var(--page-bg);
                color: var(--text);
            }

            body {
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                overflow: hidden;
            }

            /* ---------- Intro ---------- */

            .intro-screen {
                position: fixed;
                inset: 0;
                display: flex;
                align-items: center;
                justify-content: center;
                background: #ffffff;
                z-index: 20;
                transition: opacity 0.9s ease, visibility 0.9s ease;
            }

            .intro-screen.fade-out {
                opacity: 0;
                visibility: hidden;
            }

            .intro-content {
                text-align: center;
                width: min(90%, 640px);
                padding: 2rem;
            }

            .welcome-text {
                font-size: clamp(1.3rem, 2.2vw, 2rem);
                font-weight: 500;
                letter-spacing: 0.14em;
                text-transform: uppercase;
                color: var(--primary-deep);
                opacity: 0;
                transform: translateY(10px);
                transition: opacity 0.8s ease, transform 0.8s ease;
            }

            .welcome-text.show {
                opacity: 1;
                transform: translateY(0);
            }

            .welcome-text.fade-out {
                opacity: 0;
                transform: translateY(-10px);
            }

            /* Letters row -> column */

            .letters-wrap {
                display: flex;
                flex-direction: row;
                justify-content: center;
                align-items: center;
                gap: 0.55rem;
                min-height: 6rem;
                margin: 1.6rem auto;
                transition: opacity 0.5s ease;
            }

            .letters-wrap.fade-out-list {
                opacity: 0;
                pointer-events: none;
            }

            .letters-wrap.stacked {
                display: grid;
                grid-template-columns: auto 1fr;
                column-gap: 0;
                row-gap: 0.65rem;
                justify-content: center;
                min-height: auto;
                margin: 0.5rem auto 0;
            }

            /* display:contents lets each row's children fall straight into the grid */
            .letters-wrap.stacked .letter-row {
                display: contents;
            }

            .letter {
                width: 4rem;
                height: 4rem;
                display: flex;
                align-items: center;
                justify-content: center;
                border-radius: 16px;
                background: var(--primary-soft);
                border: 1px solid rgba(10, 107, 214, 0.18);
                color: var(--primary);
                font-size: 2rem;
                font-weight: 800;
                opacity: 0;
                transform: translateY(18px) scale(0.85);
                transition: opacity 0.45s ease, transform 0.45s ease;
                justify-self: start;
            }

            .letter.show {
                opacity: 1;
                transform: translateY(0) scale(1);
            }

            .word {
                opacity: 0;
                max-width: 0;
                overflow: hidden;
                white-space: nowrap;
                font-size: clamp(0.95rem, 1.6vw, 1.3rem);
                font-weight: 700;
                letter-spacing: 0.08em;
                text-transform: uppercase;
                color: var(--primary-deep);
                justify-self: start;
                align-self: center;
                margin-left: 0;
            }

            .letter-row.active .word {
                opacity: 1;
                max-width: 480px;
                margin-left: 0.9rem;
                transition: opacity 0.25s ease, margin-left 0.25s ease;
            }

            .tagline {
                position: fixed;
                top: 50%;
                left: 50%;
                width: calc(100vw - 2rem);
                min-height: 2.2rem;
                margin: 0;
                font-size: clamp(2rem, 5vw, 4.5rem);
                font-weight: 600;
                letter-spacing: 0.02em;
                color: var(--primary-deep);
                opacity: 0;
                text-align: center;
                transform: translate(-50%, -50%);
                transition: opacity 0.6s ease;
            }

            .tagline.show {
                opacity: 1;
            }

            /* ---------- Login card ---------- */

            .login-shell {
                position: relative;
                width: min(100%, 420px);
                opacity: 0;
                transform: translateY(18px);
                transition: opacity 0.8s ease, transform 0.8s ease;
                pointer-events: none;
            }

            .login-shell.show {
                opacity: 1;
                transform: translateY(0);
                pointer-events: auto;
            }

            .login-card {
                background: var(--panel);
                border: 1px solid var(--panel-border);
                box-shadow: 0 24px 48px var(--shadow);
                border-radius: 20px;
                padding: 2.2rem 1.9rem 1.8rem;
            }

            .brand {
                display: flex;
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
                margin-bottom: 1.7rem;
                padding-bottom: 1.3rem;
                border-bottom: 1px solid var(--line);
            }

            .brand-badge {
                width: 38px;
                height: 38px;
                border-radius: 50%;
                border: 1.5px solid var(--primary);
                display: grid;
                place-items: center;
                font-size: 0.95rem;
                font-weight: 800;
                color: var(--primary);
            }

            .brand h1 {
                margin: 0;
                font-size: 1.6rem;
                font-weight: 800;
                letter-spacing: 0.14em;
                text-transform: uppercase;
                color: var(--primary-deep);
            }
            .brand-logo { display: block; width: 152px; max-width: 100%; height: auto; object-fit: contain; }

            .brand p {
                margin: 0;
                font-size: 0.85rem;
                color: var(--muted);
                letter-spacing: 0.16em;
                text-transform: uppercase;
            }

            .login-form {
                display: flex;
                flex-direction: column;
                gap: 1.1rem;
            }

            .field {
                display: flex;
                flex-direction: column;
                gap: 0.45rem;
                position: relative;
            }

            label {
                font-size: 0.7rem;
                letter-spacing: 0.12em;
                text-transform: uppercase;
                color: var(--muted);
            }

            input {
                width: 100%;
                border: 1px solid rgba(13, 35, 57, 0.1);
                background: #f7fbff;
                color: var(--text);
                border-radius: 10px;
                padding: 0.85rem 0.95rem;
                font-size: 0.95rem;
                outline: none;
                transition: border-color 0.2s ease, box-shadow 0.2s ease;
            }

            .field.password-field input {
                padding-right: 2.6rem;
            }

            input:focus {
                border-color: rgba(10, 107, 214, 0.6);
                box-shadow: 0 0 0 4px rgba(10, 107, 214, 0.08);
            }

            input.input-error {
                border-color: #dc2626;
                background: #fff7f7;
                box-shadow: 0 0 0 4px rgba(220, 38, 38, 0.08);
            }

            .login-error {
                margin: -0.55rem 0 0;
                color: #dc2626;
                font-size: 0.78rem;
                font-weight: 600;
                text-align: center;
            }

            .toggle-password {
                position: absolute;
                right: 0.6rem;
                bottom: 0.55rem;
                width: 30px;
                height: 30px;
                display: flex;
                align-items: center;
                justify-content: center;
                background: none;
                border: 0;
                color: var(--muted);
                cursor: pointer;
                padding: 0;
            }

            .toggle-password:hover {
                color: var(--primary);
            }

            .toggle-password svg {
                width: 18px;
                height: 18px;
            }

            .row {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 0.8rem;
                margin-top: -0.2rem;
            }

            .remember {
                display: inline-flex;
                align-items: center;
                gap: 0.5rem;
                color: var(--muted);
                font-size: 0.8rem;
            }

            .remember input {
                width: 15px;
                height: 15px;
                accent-color: var(--primary);
            }

            .link {
                color: var(--primary-deep);
                font-size: 0.78rem;
                text-decoration: none;
            }

            .btn {
                border: 0;
                border-radius: 10px;
                background: var(--primary);
                color: white;
                padding: 0.9rem 1.2rem;
                font-size: 0.85rem;
                font-weight: 700;
                letter-spacing: 0.1em;
                text-transform: uppercase;
                cursor: pointer;
                box-shadow: 0 14px 26px rgba(10, 107, 214, 0.2);
                transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
            }

            .btn:hover {
                transform: translateY(-1px);
                background: var(--primary-deep);
                box-shadow: 0 16px 28px rgba(10, 107, 214, 0.24);
            }

            .register-row {
                text-align: center;
                font-size: 0.8rem;
                color: var(--muted);
                margin-top: 0.2rem;
            }

            .register-row a {
                color: var(--primary);
                font-weight: 700;
                text-decoration: none;
                margin-left: 0.3rem;
            }

            .register-row a:hover {
                text-decoration: underline;
            }

            @media (max-width: 480px) {
                .login-card { padding: 1.7rem 1.3rem 1.4rem; }
                .letter { width: 3.2rem; height: 3.2rem; font-size: 1.6rem; }
            }
        </style>
    </head>
    <body>
        <x-flash-toast :message="session('status') ?: (request()->query('access')
            ? (request()->query('access') === 'password_changed'
                ? 'Change of Password Detected, Please Login Again.'
                : 'Your account was signed out. Please log in again.')
            : null)" />
        <main class="login-shell show" id="loginShell">
            <div class="login-card">
                <div class="brand">
                    <img class="brand-logo" src="{{ asset('images/LSP_logo_black.png') }}" alt="LSPDMS — LSP's Data Management System">
                    <p>Secure Access</p>
                </div>

                <form class="login-form" id="loginForm" method="POST" action="{{ url('/login') }}" data-login-url="{{ route('login.form') }}" data-csrf-refresh-url="{{ route('login.csrf-token') }}">
                    @csrf
                    <div class="field">
                        <label for="email">Email address</label>
                        <input id="email" type="email" name="email" placeholder="name@example.com" value="{{ old('email') }}" class="{{ $errors->has('login') ? 'input-error' : '' }}" required autofocus />
                    </div>

                    <div class="field password-field">
                        <label for="password">Password</label>
                        <input id="password" type="password" name="password" placeholder="••••••••" class="{{ $errors->has('login') ? 'input-error' : '' }}" required />
                        <button type="button" class="toggle-password" id="togglePassword" aria-label="Show password">
                            <svg id="eyeIcon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                        </button>
                    </div>

                    @error('login')
                        <p class="login-error">{{ $message }}</p>
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

                    <button type="submit" class="btn">Login</button>

                    <p class="register-row">Don't have an account?
                        <a href="{{ url('/register') }}">Register</a>
                    </p>
                </form>
            </div>
        </main>

        <script>
            // Password show/hide toggle
            const togglePassword = document.getElementById('togglePassword');
            const passwordInput = document.getElementById('password');
            const eyeIcon = document.getElementById('eyeIcon');
            const loginForm = document.getElementById('loginForm');
            const loginRequestError = document.getElementById('loginRequestError');
            const loginButton = loginForm.querySelector('button[type="submit"]');

            const eyeOpenPaths = `<path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/>`;
            const eyeClosedPaths = `<path d="M17.94 17.94A10.94 10.94 0 0112 19c-7 0-11-7-11-7a21.6 21.6 0 015.06-6.06"/><path d="M9.9 4.24A10.94 10.94 0 0112 4c7 0 11 7 11 7a21.6 21.6 0 01-3.22 4.44"/><path d="M14.12 14.12a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>`;

            togglePassword.addEventListener('click', () => {
                const showing = passwordInput.type === 'text';
                passwordInput.type = showing ? 'password' : 'text';
                eyeIcon.innerHTML = showing ? eyeOpenPaths : eyeClosedPaths;
                togglePassword.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
            });

            loginForm.addEventListener('submit', async event => {
                event.preventDefault();
                loginButton.disabled = true;
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
                            loginRequestError.textContent = responseError?.textContent.trim()
                                || 'Invalid username or password.';
                            loginRequestError.hidden = false;
                            loginButton.disabled = false;
                            return;
                        }
                        window.location.assign(response.url);
                        return;
                    }

                    if (response.status === 419) {
                        throw new Error('Your sign-in session changed again. Reload this page and try once more.');
                    }

                    if (!response.ok) {
                        throw new Error('Unable to sign in right now. Please try again.');
                    }

                    throw new Error('Sign-in could not be completed. Please try again.');
                } catch (error) {
                    loginRequestError.textContent = error.message || 'Unable to sign in right now. Please try again.';
                    loginRequestError.hidden = false;
                    loginButton.disabled = false;
                }
            });
        </script>
    </body>
</html>