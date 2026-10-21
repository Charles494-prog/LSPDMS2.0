@props(['message' => null])

@if (filled($message))
    <style>
        .app-flash-toast {
            position: fixed;
            z-index: 100200;
            top: max(1rem, env(safe-area-inset-top));
            right: max(1rem, env(safe-area-inset-right));
            display: flex;
            align-items: flex-start;
            gap: .75rem;
            width: min(440px, calc(100vw - 2rem));
            padding: .9rem 1rem;
            border: 1px solid var(--border, #d8e2ed);
            border-left: 4px solid var(--accent, #16835d);
            border-radius: 10px;
            background: var(--surface, #fff);
            color: var(--text, #172b43);
            box-shadow: 0 12px 32px rgba(16, 24, 40, .18);
            font: 600 .9rem/1.45 -apple-system, 'Helvetica Neue', Arial, sans-serif;
            overflow-wrap: anywhere;
            animation: app-flash-in .2s ease-out both;
        }
        .app-flash-toast[hidden] { display: none; }
        .app-flash-toast-icon {
            display: grid;
            width: 1.35rem;
            height: 1.35rem;
            flex: 0 0 1.35rem;
            place-items: center;
            border-radius: 50%;
            background: color-mix(in srgb, var(--accent, #16835d) 12%, transparent);
            color: var(--accent, #16835d);
        }
        .app-flash-toast-message { flex: 1; min-width: 0; }
        .app-flash-toast-close {
            display: grid;
            width: 1.75rem;
            height: 1.75rem;
            flex: 0 0 1.75rem;
            place-items: center;
            margin: -.2rem -.25rem 0 0;
            border: 0;
            border-radius: 6px;
            background: transparent;
            color: inherit;
            font: inherit;
            font-size: 1.25rem;
            cursor: pointer;
        }
        .app-flash-toast-close:hover { background: color-mix(in srgb, currentColor 8%, transparent); }
        @keyframes app-flash-in {
            from { opacity: 0; transform: translateY(-8px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @media (max-width: 600px) {
            .app-flash-toast {
                top: max(.65rem, env(safe-area-inset-top));
                right: max(.65rem, env(safe-area-inset-right));
                left: max(.65rem, env(safe-area-inset-left));
                width: auto;
                max-width: none;
                padding: .8rem .85rem;
                font-size: .86rem;
            }
        }
        @media (prefers-reduced-motion: reduce) {
            .app-flash-toast { animation: none; }
        }
    </style>
    <div class="app-flash-toast" id="appFlashToast" role="status" aria-live="polite">
        <span class="app-flash-toast-icon" aria-hidden="true">✓</span>
        <span class="app-flash-toast-message">{{ $message }}</span>
        <button class="app-flash-toast-close" type="button" aria-label="Dismiss notification">&times;</button>
    </div>
    <script>
        (() => {
            const toast = document.getElementById('appFlashToast');
            if (!toast) return;
            const closeButton = toast.querySelector('.app-flash-toast-close');
            let timer;
            const dismiss = () => {
                window.clearTimeout(timer);
                toast.hidden = true;
            };
            closeButton.addEventListener('click', dismiss);
            timer = window.setTimeout(dismiss, 7000);
        })();
    </script>
@endif
