@extends('layouts.app')

@section('title', 'Access denied')

@section('content')
<style>
    .ad-page {
        display: grid;
        min-height: min(72vh, 640px);
        place-items: center;
        padding: clamp(1rem, 4vw, 3rem);
        color: var(--text);
    }
    .ad-card {
        width: min(100%, 560px);
        border: 1px solid var(--border);
        border-radius: 14px;
        background: var(--surface);
        overflow: hidden;
    }

    /* Top: icon, code, title, message */
    .ad-top {
        display: grid;
        justify-items: center;
        padding: clamp(2rem, 6vw, 3.2rem) clamp(1.4rem, 5vw, 2.8rem) clamp(1.6rem, 4vw, 2.2rem);
        text-align: center;
    }
    .ad-icon {
        display: grid;
        width: 64px;
        height: 64px;
        margin-bottom: 1.2rem;
        place-items: center;
        border: 1px solid var(--border);
        border-radius: 50%;
        background: var(--bg);
        color: var(--text);
    }
    .ad-icon svg { width: 28px; height: 28px; }
    .ad-code {
        margin: 0 0 .5rem;
        color: var(--text-muted);
        font-size: .8rem;
        font-weight: 700;
        letter-spacing: .22em;
        text-transform: uppercase;
    }
    .ad-title {
        margin: 0 0 .6rem;
        font-size: clamp(1.5rem, 4.5vw, 2rem);
        font-weight: 700;
        letter-spacing: -.03em;
        line-height: 1.1;
    }
    .ad-message {
        max-width: 30rem;
        margin: 0;
        color: var(--text-muted);
        font-size: .96rem;
        line-height: 1.6;
        overflow-wrap: anywhere;
    }

    /* Middle: what to do */
    .ad-help {
        padding: 1.2rem clamp(1.4rem, 5vw, 2.8rem);
        border-top: 1px solid var(--border);
        background: var(--bg);
    }
    .ad-help h2 {
        margin: 0 0 .5rem;
        font-size: .82rem;
        font-weight: 700;
        letter-spacing: .06em;
        text-transform: uppercase;
        color: var(--text-muted);
    }
    .ad-help ul { margin: 0; padding: 0; list-style: none; }
    .ad-help li {
        display: flex;
        gap: .65rem;
        padding: .45rem 0;
        font-size: .9rem;
        line-height: 1.5;
    }
    .ad-help li::before {
        content: "";
        flex: none;
        width: 6px;
        height: 6px;
        margin-top: .5rem;
        border-radius: 50%;
        background: var(--text-muted);
    }

    /* Bottom: actions */
    .ad-actions {
        display: flex;
        flex-wrap: wrap;
        gap: .7rem;
        padding: 1.2rem clamp(1.4rem, 5vw, 2.8rem);
        border-top: 1px solid var(--border);
    }
    .ad-btn {
        display: inline-flex;
        flex: 1 1 160px;
        align-items: center;
        justify-content: center;
        gap: .5rem;
        padding: .72rem 1.1rem;
        border: 1px solid var(--accent);
        border-radius: 8px;
        font: inherit;
        font-size: .92rem;
        font-weight: 600;
        text-decoration: none;
        cursor: pointer;
        transition: opacity .15s ease, background .15s ease, border-color .15s ease, transform .15s ease;
    }
    .ad-btn svg { width: 17px; height: 17px; }
    .ad-btn:active { transform: scale(.98); }
    .ad-btn-primary { background: var(--accent); color: var(--on-accent, var(--bg)); }
    .ad-btn-primary:hover { opacity: .85; }
    .ad-btn-ghost { background: transparent; color: var(--text); border-color: var(--border); }
    .ad-btn-ghost:hover { border-color: var(--text-muted); background: var(--bg); }
    .ad-btn:focus-visible { outline: 2px solid var(--text-muted); outline-offset: 2px; }

    @media (prefers-reduced-motion: reduce) {
        .ad-btn { transition: none; }
    }
</style>

<div class="ad-page">
    <section class="ad-card" aria-labelledby="ad-title">
        <div class="ad-top">
            <div class="ad-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="5" y="11" width="14" height="9" rx="2"/>
                    <path d="M8 11V8a4 4 0 0 1 8 0v3"/>
                    <circle cx="12" cy="15.5" r="1" fill="currentColor" stroke="none"/>
                </svg>
            </div>
            <p class="ad-code">Error 403</p>
            <h1 class="ad-title" id="ad-title">Access restricted</h1>
            <p class="ad-message">
                {{ $exception->getMessage() ?: 'Your account does not have permission to access this feature.' }}
            </p>
        </div>

        <div class="ad-help">
            <h2>What you can do</h2>
            <ul>
                <li>Ask your Head Secretary to enable the access you need for your account.</li>
                <li>Check that you opened the right page from the main menu.</li>
                <li>Sign out and back in if your access was changed recently.</li>
            </ul>
        </div>

        <div class="ad-actions">
            <button type="button" class="ad-btn ad-btn-ghost" onclick="if (history.length > 1) { history.back(); } else { location.href = '{{ url('/home') }}'; }">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m10 7-5 5 5 5M5 12h14"/></svg>
                Go back
            </button>
            <a class="ad-btn ad-btn-primary" href="{{ url('/home') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 11 12 4l8 7v8a1 1 0 0 1-1 1h-4v-6H9v6H5a1 1 0 0 1-1-1v-8Z"/></svg>
                Back to Home
            </a>
        </div>
    </section>
</div>
@endsection