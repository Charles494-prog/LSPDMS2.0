@extends('layouts.app')

@section('title', 'Access denied')

@section('content')
<style>
    .access-denied-page {
        display: grid;
        min-height: min(68vh, 620px);
        place-items: center;
        padding: clamp(1rem, 4vw, 3rem);
    }
    .access-denied-card {
        display: grid;
        justify-items: center;
        width: min(100%, 620px);
        padding: clamp(2rem, 6vw, 4rem);
        border: 1px solid var(--border);
        border-radius: 20px;
        background: var(--surface);
        box-shadow: 0 18px 55px rgba(16, 24, 40, .1);
        text-align: center;
    }
    .access-denied-icon {
        display: grid;
        width: 72px;
        height: 72px;
        margin-bottom: 1.3rem;
        place-items: center;
        border: 1px solid color-mix(in srgb, var(--accent) 20%, var(--border));
        border-radius: 22px;
        background: color-mix(in srgb, var(--accent) 10%, var(--surface));
        color: var(--accent);
    }
    .access-denied-icon svg { width: 34px; height: 34px; }
    .access-denied-code {
        margin: 0;
        color: var(--accent);
        font-size: clamp(3.6rem, 12vw, 6rem);
        font-weight: 800;
        letter-spacing: -.07em;
        line-height: 1;
    }
    .access-denied-title {
        margin: 1rem 0 .45rem;
        font-size: clamp(1.35rem, 4vw, 1.8rem);
        font-weight: 750;
        letter-spacing: -.035em;
    }
    .access-denied-message {
        max-width: 34rem;
        margin: 0;
        color: var(--text-muted);
        font-size: .98rem;
        line-height: 1.65;
    }
    .access-denied-home {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: .55rem;
        margin-top: 1.7rem;
        padding: .8rem 1.15rem;
        border: 0;
        border-radius: 10px;
        background: var(--accent);
        box-shadow: 0 6px 16px color-mix(in srgb, var(--accent) 28%, transparent);
        color: #fff;
        font-size: .93rem;
        font-weight: 700;
        text-decoration: none;
        transition: transform .16s ease, box-shadow .16s ease;
    }
    .access-denied-home:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 22px color-mix(in srgb, var(--accent) 34%, transparent);
    }
    .access-denied-home svg { width: 18px; height: 18px; }
</style>

<main class="access-denied-page">
    <section class="access-denied-card" aria-labelledby="access-denied-title">
        <div class="access-denied-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 3 3.8 7v5.2c0 4.5 3.4 7.5 8.2 8.8 4.8-1.3 8.2-4.3 8.2-8.8V7L12 3Z"/>
                <path d="M9.5 9.5 14.5 14.5M14.5 9.5l-5 5"/>
            </svg>
        </div>
        <p class="access-denied-code">403</p>
        <h1 class="access-denied-title" id="access-denied-title">Access restricted</h1>
        <p class="access-denied-message">
            {{ $exception->getMessage() ?: 'Your account does not have permission to access this feature.' }}
        </p>
        <a class="access-denied-home" href="{{ url('/home') }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="m10 7-5 5 5 5M5 12h14"/>
            </svg>
            Back to Home
        </a>
    </section>
</main>
@endsection
