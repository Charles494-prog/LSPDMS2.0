<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Create an account · {{ config('app.name', 'LSPDMS') }}</title>
    @include('components.brand-meta')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --blue:#0a6bd6; --deep:#0a4a8a; --ink:#0d2339; --muted:#6b8299; --line:rgba(12,60,110,.14); --soft:#eaf3ff; }
        * { box-sizing:border-box; }
        body { min-height:100vh; margin:0; display:grid; place-items:center; padding:1.5rem; color:var(--ink); background:#f4f7fb; font-family:Montserrat, sans-serif; }
        .card { width:min(100%,480px); padding:2rem; border:1px solid var(--line); border-radius:20px; background:#fff; box-shadow:0 24px 48px rgba(15,76,129,.1); }
        h1 { margin:0; color:var(--deep); font-size:1.35rem; }
        .intro { margin:.45rem 0 1.5rem; color:var(--muted); font-size:.9rem; line-height:1.55; }
        .fields { display:grid; grid-template-columns:1fr 1fr; gap:1rem; }
        .field { margin-bottom:1rem; }
        label { display:block; margin-bottom:.4rem; font-size:.82rem; font-weight:700; }
        input { width:100%; min-height:44px; padding:.7rem .8rem; border:1px solid var(--line); border-radius:9px; color:var(--ink); font:inherit; font-size:.9rem; }
        input:focus { outline:3px solid rgba(10,107,214,.14); border-color:var(--blue); }
        .error { margin:.35rem 0 0; color:#b42318; font-size:.78rem; }
        .notice { margin-bottom:1rem; padding:.75rem .85rem; border-radius:9px; color:#8a3412; background:#fff4e5; font-size:.83rem; line-height:1.5; }
        .submit { width:100%; margin-top:.3rem; padding:.85rem 1rem; border:0; border-radius:9px; color:#fff; background:var(--blue); font:700 .9rem Montserrat,sans-serif; cursor:pointer; }
        .submit:hover { background:var(--deep); }
        .footer { margin:1.1rem 0 0; color:var(--muted); font-size:.83rem; text-align:center; }
        a { color:var(--blue); font-weight:700; text-decoration:none; }
        a:hover { text-decoration:underline; }
        @media(max-width:480px) { .card { padding:1.5rem 1.2rem; } .fields { grid-template-columns:1fr; gap:0; } }
    </style>
</head>
<body>
    <x-flash-toast :message="session('status')" />
    <main class="card">
        <h1>Create your LSPDMS account</h1>
        <p class="intro">Submit your details to request an account. The Head Secretary must grant access before you can sign in.</p>

        @if ($errors->any())
            <div class="notice" role="alert">Please correct the highlighted fields and submit again.</div>
        @endif

        <form method="POST" action="{{ route('register.store') }}">
            @csrf
            <div class="fields">
                <div class="field">
                    <label for="first_name">First name</label>
                    <input id="first_name" name="first_name" type="text" value="{{ old('first_name') }}" maxlength="100" autocomplete="given-name" required autofocus>
                    @error('first_name')<p class="error">{{ $message }}</p>@enderror
                </div>
                <div class="field">
                    <label for="last_name">Last name</label>
                    <input id="last_name" name="last_name" type="text" value="{{ old('last_name') }}" maxlength="100" autocomplete="family-name" required>
                    @error('last_name')<p class="error">{{ $message }}</p>@enderror
                </div>
            </div>
            <div class="field">
                <label for="email">Email address</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" maxlength="255" autocomplete="email" required>
                @error('email')<p class="error">{{ $message }}</p>@enderror
            </div>
            <div class="field">
                <label for="password">Strong password</label>
                <input id="password" name="password" type="password" minlength="8" autocomplete="new-password" required>
                @include('components.password-strength', ['inputId' => 'password'])
                @error('password')<p class="error">{{ $message }}</p>@enderror
            </div>
            <div class="field">
                <label for="password_confirmation">Confirm password</label>
                <input id="password_confirmation" name="password_confirmation" type="password" minlength="8" autocomplete="new-password" required>
            </div>
            <button class="submit" type="submit">Submit registration</button>
        </form>

        <p class="footer">Already have an account? <a href="{{ route('login.form') }}">Return to login</a></p>
    </main>
</body>
</html>
