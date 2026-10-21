<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Forgot password - {{ config('app.name', 'LSPDMS') }}</title>
    @include('components.brand-meta')
    <style>
        * { box-sizing: border-box; }
        body { display: grid; min-height: 100vh; place-items: center; margin: 0; padding: 1rem; background: #f3f6fa; color: #10233a; font-family: Arial, sans-serif; }
        main { width: min(100%, 440px); padding: 2rem; border: 1px solid #e0e6ee; border-radius: 16px; background: #fff; box-shadow: 0 18px 50px rgba(16,35,58,.1); }
        h1 { margin: 0; font-size: 1.55rem; }
        p { color: #5b6b7c; font-size: .92rem; line-height: 1.55; }
        label { display: block; margin: 1.2rem 0 .4rem; font-size: .85rem; font-weight: 700; }
        input { width: 100%; padding: .75rem; border: 1px solid #cbd5e1; border-radius: 8px; font: inherit; }
        button { width: 100%; margin-top: 1rem; padding: .8rem; border: 0; border-radius: 8px; background: #10233a; color: white; font: inherit; font-weight: 700; cursor: pointer; }
        a { display: block; margin-top: 1rem; color: #1265b5; text-align: center; }
        .message { margin-top: 1rem; color: #146c43; }
        .error { color: #b42318; font-size: .82rem; }
        .confirmation { text-align: center; }
        .confirmation h1 { margin-bottom: .85rem; }
        .confirmation p { margin: .45rem 0; }
        .confirmation button:disabled { opacity: .58; cursor: not-allowed; }
        .countdown { min-height: 1.4em; margin-top: .7rem!important; font-size: .84rem!important; }
    </style>
</head>
<body>
<x-flash-toast :message="session('status')" />
<main>
    @if ($isSentScreen)
    <section class="confirmation" aria-live="polite">
        <h1>Link sent!</h1>
        <p>Please check your email inbox.</p>
        @error('email') <p class="error">{{ $message }}</p> @enderror
        <form method="POST" action="{{ route('password.email.resend') }}">
            @csrf
            <button id="resendButton" type="submit" disabled>Send link again</button>
            <p class="countdown" id="resendCountdown" data-seconds="{{ $resendAvailableIn }}"></p>
        </form>
        <a href="{{ url('/login') }}">Go back to login</a>
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
    <p>Enter the email address for your verified account. If it matches an active account, we’ll send a password reset link.</p>
    <form method="POST" action="{{ route('password.email') }}">
        @csrf
        <label for="email">Email address</label>
        <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="email" autofocus>
        @error('email') <p class="error">{{ $message }}</p> @enderror
        <button type="submit">Send reset link</button>
    </form>
    <a href="{{ url('/login') }}">Back to login</a>
    @endif
</main>
</body>
</html>
