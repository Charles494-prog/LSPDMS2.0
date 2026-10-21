<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Link expired - {{ config('app.name', 'LSPDMS') }}</title>
    @include('components.brand-meta')
    <style>
        * { box-sizing: border-box; }
        body { display: grid; min-height: 100vh; place-items: center; margin: 0; padding: 1rem; background: #f3f6fa; color: #10233a; font-family: Arial, sans-serif; }
        main { width: min(100%, 440px); padding: 2rem; border: 1px solid #e0e6ee; border-radius: 16px; background: #fff; box-shadow: 0 18px 50px rgba(16,35,58,.1); text-align: center; }
        h1 { margin: 0; font-size: 1.55rem; }
        p { color: #5b6b7c; font-size: .92rem; line-height: 1.55; }
        a { display: block; margin-top: 1rem; color: #1265b5; }
        .primary { padding: .8rem; border-radius: 8px; background: #10233a; color: white; font-weight: 700; text-decoration: none; }
    </style>
</head>
<body>
<x-flash-toast :message="session('status')" />
<main>
    <h1>Link expired</h1>
    <p>This password reset link has expired. Request a new one to continue.</p>
    <a class="primary" href="{{ route('password.request') }}">Request a new link</a>
    <a href="{{ url('/login') }}">Go back to login</a>
</main>
</body>
</html>
