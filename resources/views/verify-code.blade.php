<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Verify Code - {{ config('app.name', 'LSPDMS') }}</title>
        @include('components.brand-meta')
        <style>
            :root { --blue: #0a6bd6; --deep-blue: #0a4a8a; --muted: #6b8299; }
            * { box-sizing: border-box; }
            body { min-height: 100vh; margin: 0; display: grid; place-items: center; padding: 1rem; background: #f4f7fb; color: #0d2339; font-family: Montserrat, Arial, sans-serif; }
            .card { width: min(100%, 420px); padding: 2.2rem 1.9rem; background: #fff; border: 1px solid rgba(25, 81, 145, .1); border-radius: 20px; box-shadow: 0 24px 48px rgba(15, 76, 129, .1); }
            h1 { margin: 0 0 .6rem; color: var(--deep-blue); font-size: 1.6rem; }
            p { color: var(--muted); font-size: .9rem; line-height: 1.6; }
            label { display: block; margin-top: 1.4rem; color: var(--muted); font-size: .7rem; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; }
            .code-inputs { display: flex; justify-content: center; gap: .55rem; margin-top: .45rem; }
            .code-input { width: 3rem; height: 3.4rem; margin: 0; padding: 0; border: 1px solid rgba(13, 35, 57, .15); border-radius: 10px; color: #0d2339; font-size: 1.5rem; font-weight: 700; text-align: center; }
            .code-input:focus { outline: none; border-color: var(--blue); box-shadow: 0 0 0 4px rgba(10, 107, 214, .1); }
            .error { color: #dc2626; font-size: .8rem; font-weight: 600; }
            button { width: 100%; margin-top: 1.2rem; padding: .9rem; border: 0; border-radius: 10px; background: var(--blue); color: #fff; cursor: pointer; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; }
            button:hover { background: var(--deep-blue); }
            button:disabled { cursor: not-allowed; opacity: .5; }
            .timer { margin: 1rem 0 0; text-align: center; color: var(--deep-blue); font-size: .85rem; font-weight: 700; }
            .resend-form { margin-top: .8rem; }
            .resend { margin-top: 0; background: transparent; color: var(--blue); box-shadow: none; }
            .resend:hover { background: transparent; color: var(--deep-blue); }
            @media (max-width: 420px) { .code-inputs { gap: .35rem; } .code-input { width: 2.65rem; } }
        </style>
    </head>
    <body>
        <x-flash-toast :message="session('status')" />
        <main class="card">
            <h1>Verify your login</h1>
            <p>Enter the six-digit verification code sent to your Gmail address.</p>
            @error('code')
                <p class="error">{{ $message }}</p>
            @enderror
            <form method="POST" action="{{ url('/verify-code') }}">
                @csrf
                <label for="code-0">Verification code</label>
                <div class="code-inputs" id="codeInputs">
                    @for ($index = 0; $index < 6; $index++)
                        <input class="code-input" id="code-{{ $index }}" type="text" inputmode="numeric" maxlength="1" autocomplete="one-time-code" aria-label="Verification digit {{ $index + 1 }}">
                    @endfor
                </div>
                <input id="code" name="code" type="hidden" required>
                <p class="timer" id="timer" role="status"></p>
                <button type="submit">Verify</button>
            </form>
            <form class="resend-form" method="POST" action="{{ url('/verify-code/resend') }}">
                @csrf
                <button class="resend" id="resendButton" type="submit">Send code again</button>
            </form>
        </main>
        <script>
            const codeInputs = [...document.querySelectorAll('.code-input')];
            const codeField = document.getElementById('code');
            const timer = document.getElementById('timer');
            const resendButton = document.getElementById('resendButton');
            const expiresAt = @json($expiresAt);
            const resendAvailableAt = @json($resendAvailableAt);

            function updateCode() {
                codeField.value = codeInputs.map(input => input.value).join('');
            }

            codeInputs.forEach((input, index) => {
                input.addEventListener('input', () => {
                    input.value = input.value.replace(/\D/g, '').slice(-1);
                    updateCode();
                    if (input.value && codeInputs[index + 1]) codeInputs[index + 1].focus();
                });

                input.addEventListener('keydown', event => {
                    if (event.key === 'Backspace' && !input.value && codeInputs[index - 1]) {
                        codeInputs[index - 1].focus();
                    }
                });

                input.addEventListener('paste', event => {
                    event.preventDefault();
                    const pasted = (event.clipboardData.getData('text') || '').replace(/\D/g, '').slice(0, 6);
                    pasted.split('').forEach((digit, digitIndex) => {
                        if (codeInputs[digitIndex]) codeInputs[digitIndex].value = digit;
                    });
                    updateCode();
                    codeInputs[Math.min(pasted.length, 5)].focus();
                });
            });

            function updateTimer() {
                const secondsLeft = Math.max(0, expiresAt - Math.floor(Date.now() / 1000));
                const minutes = Math.floor(secondsLeft / 60);
                const seconds = String(secondsLeft % 60).padStart(2, '0');
                timer.textContent = secondsLeft ? `Code expires in ${minutes}:${seconds}` : 'Session expired. Please try again.';

                const resendSeconds = Math.max(0, resendAvailableAt - Math.floor(Date.now() / 1000));
                resendButton.disabled = resendSeconds > 0;
                resendButton.textContent = resendSeconds ? `Send code again (${resendSeconds}s)` : 'Send code again';

                if (!secondsLeft) {
                    window.setTimeout(() => { window.location.href = '{{ url('/login?expired=1') }}'; }, 1200);
                    return;
                }
                window.setTimeout(updateTimer, 1000);
            }

            updateTimer();
            codeInputs[0].focus();
        </script>
    </body>
</html>
