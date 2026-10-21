<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Choose a new password - {{ config('app.name', 'LSPDMS') }}</title>
    @include('components.brand-meta')
    <style>
        * { box-sizing: border-box; }
        body { display: grid; min-height: 100vh; place-items: center; margin: 0; padding: 1rem; background: #f3f6fa; color: #10233a; font-family: Arial, sans-serif; }
        main { width: min(100%, 440px); padding: 2rem; border: 1px solid #e0e6ee; border-radius: 16px; background: #fff; box-shadow: 0 18px 50px rgba(16,35,58,.1); }
        h1 { margin: 0; font-size: 1.55rem; }
        p { color: #5b6b7c; font-size: .9rem; line-height: 1.5; }
        label { display: block; margin: 1rem 0 .4rem; font-size: .85rem; font-weight: 700; }
        input { width: 100%; padding: .75rem; border: 1px solid #cbd5e1; border-radius: 8px; font: inherit; }
        button { width: 100%; margin-top: 1.2rem; padding: .8rem; border: 0; border-radius: 8px; background: #10233a; color: white; font: inherit; font-weight: 700; cursor: pointer; }
        .error { color: #b42318; font-size: .82rem; }
    </style>
</head>
<body>
<x-flash-toast :message="session('status')" />
<main>
    <h1>Choose a new password</h1>
    <p>Choose a strong password. Reset links expire after one hour.</p>
    <form method="POST" action="{{ route('password.reset') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <input type="hidden" name="email" value="{{ $email }}">
        <label for="password">New password</label>
        <input id="password" type="password" name="password" required autocomplete="new-password" minlength="8" autofocus>
        @include('components.password-strength', ['inputId' => 'password'])
        @error('password') <p class="error">{{ $message }}</p> @enderror
        <label for="password_confirmation">Confirm new password</label>
        <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" minlength="8">
        <button type="submit">Reset password</button>
    </form>
</main>
</body>
</html>
