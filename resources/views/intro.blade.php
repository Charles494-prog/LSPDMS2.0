<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="color-scheme" content="dark">
        <meta name="theme-color" content="#000000">
        <title>{{ config('app.name', 'LSPDMS') }}</title>
        @include('components.brand-meta')
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
        <style>
            :root {
                color-scheme: dark;
                --bg: #000000;
                --ink: #ffffff;
                --ink-soft: #a1a1a1;
                --ink-dim: #6f6f6f;
                --line: #1f1f1f;
                --line-strong: #3a3a3a;
                --surface: #0a0a0a;
                --accent: #3291ff;
                --ease: cubic-bezier(.22, .9, .32, 1);
                --px: 0;
                --py: 0;
            }

            * { box-sizing: border-box; }

            html, body { margin: 0; height: 100%; }

            body {
                min-height: 100vh;
                min-height: 100dvh;
                overflow: hidden;
                background: var(--bg);
                color: var(--ink);
                font-family: 'Montserrat', -apple-system, 'Segoe UI', 'Helvetica Neue', Arial, sans-serif;
                -webkit-font-smoothing: antialiased;
            }

            /* ---------- Aurora: slow, soft light drifting behind everything ---------- */
            .aurora {
                position: fixed;
                inset: -20%;
                pointer-events: none;
                filter: blur(70px);
                opacity: 0;
                transform: translate3d(calc(var(--px) * -26px), calc(var(--py) * -26px), 0);
                transition: opacity 2.4s ease, transform 1.2s var(--ease);
            }
            .aurora.on { opacity: 1; }
            .aurora i {
                position: absolute;
                width: 46vmax;
                height: 46vmax;
                border-radius: 50%;
                mix-blend-mode: screen;
                will-change: transform;
            }
            .aurora i:nth-child(1) {
                left: 8%; top: 12%;
                background: radial-gradient(circle, rgba(50, 145, 255, .32), transparent 62%);
                animation: drift-a 22s ease-in-out infinite alternate;
            }
            .aurora i:nth-child(2) {
                right: 6%; top: 30%;
                background: radial-gradient(circle, rgba(140, 110, 255, .24), transparent 62%);
                animation: drift-b 26s ease-in-out infinite alternate;
            }
            .aurora i:nth-child(3) {
                left: 30%; bottom: 0;
                background: radial-gradient(circle, rgba(255, 255, 255, .08), transparent 60%);
                animation: drift-c 30s ease-in-out infinite alternate;
            }
            @keyframes drift-a { to { transform: translate3d(18vw, 10vh, 0) scale(1.15); } }
            @keyframes drift-b { to { transform: translate3d(-16vw, -12vh, 0) scale(.9); } }
            @keyframes drift-c { to { transform: translate3d(10vw, -16vh, 0) scale(1.2); } }

            /* ---------- Constellation: particles that drift and lean toward the pointer ---------- */
            #constellation {
                position: fixed;
                inset: 0;
                width: 100%;
                height: 100%;
                pointer-events: none;
                opacity: 0;
                transition: opacity 2s ease;
            }
            #constellation.on { opacity: .85; }

            /* ---------- Moving grid (reacts to the camera with parallax) ---------- */
            .backdrop {
                position: fixed;
                inset: 0;
                overflow: hidden;
                pointer-events: none;
                -webkit-mask-image: radial-gradient(ellipse at center, #000 15%, transparent 78%);
                mask-image: radial-gradient(ellipse at center, #000 15%, transparent 78%);
            }

            .bgcam { position: absolute; inset: 0; transform-origin: 50% 50%; }

            .grid {
                position: absolute;
                inset: -25%;
                background-image:
                    linear-gradient(var(--line) 1px, transparent 1px),
                    linear-gradient(90deg, var(--line) 1px, transparent 1px);
                background-size: 64px 64px;
                will-change: transform;
                animation: grid-drift 6s linear infinite;
            }

            @keyframes grid-drift {
                from { transform: translate3d(0, 0, 0); }
                to { transform: translate3d(64px, 64px, 0); }
            }

            .beam {
                position: fixed;
                left: 0;
                right: 0;
                top: 0;
                height: 1px;
                pointer-events: none;
                background: linear-gradient(90deg, transparent, rgba(50, 145, 255, .55), #ffffff, rgba(50, 145, 255, .55), transparent);
                opacity: .35;
                will-change: transform;
                animation: beam 5.5s linear infinite;
            }

            @keyframes beam {
                from { transform: translate3d(0, -2vh, 0); }
                to { transform: translate3d(0, 102vh, 0); }
            }

            .field {
                position: fixed;
                inset: 0;
                overflow: hidden;
                pointer-events: none;
                transform: translate3d(calc(var(--px) * 14px), calc(var(--py) * 14px), 0);
                transition: transform 1.2s var(--ease);
            }

            .shape {
                position: absolute;
                left: var(--x);
                top: var(--y);
                width: var(--s);
                height: var(--s);
                border: 1px solid var(--line-strong);
                opacity: 0;
                will-change: transform;
                animation:
                    shape-in 1.2s ease forwards,
                    float var(--d) ease-in-out var(--delay) infinite alternate;
            }
            .shape.ring { border-radius: 50%; }
            .shape.square { border-radius: 2px; }
            .shape.dot { border: 0; border-radius: 50%; background: #4a4a4a; }
            .shape.line { height: 1px; border: 0; background: var(--line-strong); }
            .shape.plus { border: 0; }
            .shape.plus::before,
            .shape.plus::after { content: ""; position: absolute; background: var(--line-strong); }
            .shape.plus::before { left: 0; right: 0; top: 50%; height: 1px; }
            .shape.plus::after { top: 0; bottom: 0; left: 50%; width: 1px; }

            @keyframes shape-in { to { opacity: .9; } }

            @keyframes float {
                from { transform: translate3d(0, 0, 0) rotate(0deg); }
                to { transform: translate3d(var(--dx), var(--dy), 0) rotate(var(--r)); }
            }

            /* Vignette keeps the eye on the centre. */
            .vignette {
                position: fixed;
                inset: 0;
                pointer-events: none;
                background: radial-gradient(ellipse at center, transparent 45%, rgba(0, 0, 0, .75) 100%);
            }

            /* ---------- Camera: the whole composition moves and zooms inside the screen ---------- */
            .world {
                position: fixed;
                inset: 0;
                z-index: 1;
                display: grid;
                place-items: center;
                transform-origin: 50% 50%;
            }

            .intro {
                display: grid;
                grid-template-columns: minmax(0, 1fr); /* never wider than the screen */
                place-items: center;
                width: min(92vw, 720px);
                padding: 2rem 1rem;
                text-align: center;
            }
            /* Every stage shares one cell, so nothing shifts when stages swap. */
            .intro > * { grid-area: 1 / 1; }

            /* ---------- Opening: the logo arrives with a pulse ---------- */
            .logo-stage {
                position: relative;
                display: grid;
                place-items: center;
                width: clamp(110px, 22vw, 150px);
                aspect-ratio: 1;
                opacity: 0;
                transform: scale(.82);
                transition: opacity 1s ease, transform 1.2s var(--ease);
            }
            .logo-stage.show { opacity: 1; transform: scale(1); }
            .logo-stage.hide { opacity: 0; transform: scale(1.12); filter: blur(6px); transition: opacity .8s ease, transform .9s ease, filter .9s ease; }
            .logo-stage img { width: 62%; height: auto; filter: drop-shadow(0 0 22px rgba(255, 255, 255, .28)); }
            .logo-stage .pulse {
                position: absolute;
                inset: 0;
                border: 1px solid rgba(255, 255, 255, .5);
                border-radius: 50%;
                opacity: 0;
            }
            .logo-stage.show .pulse { animation: pulse 2.4s var(--ease) infinite; }
            .logo-stage.show .pulse:nth-child(2) { animation-delay: .8s; }
            .logo-stage.show .pulse:nth-child(3) { animation-delay: 1.6s; }
            @keyframes pulse {
                from { opacity: .7; transform: scale(.7); }
                to { opacity: 0; transform: scale(1.6); }
            }

            .welcome {
                font-size: clamp(1.5rem, 4.5vw, 2.8rem);
                font-weight: 600;
                letter-spacing: .14em;
                text-transform: uppercase;
                color: var(--ink);
                opacity: 0;
                filter: blur(8px);
                transform: translate3d(0, 10px, 0);
                transition: opacity .9s ease, transform .9s ease, filter .9s ease, letter-spacing 1.6s var(--ease);
            }
            .welcome.show { opacity: 1; filter: blur(0); transform: translate3d(0, 0, 0); letter-spacing: .22em; }
            .welcome.hide { opacity: 0; filter: blur(8px); transform: translate3d(0, -10px, 0); }

            .letters {
                display: flex;
                justify-content: center;
                gap: .5rem;
                transition: opacity .9s ease;
            }
            .letters.stacked {
                display: grid;
                grid-template-columns: auto 1fr;
                justify-content: start;
                row-gap: .7rem;
                column-gap: 1rem;
                width: fit-content;
                text-align: left;
            }
            .letters.stacked .row { display: contents; }
            .letters.fade { opacity: 0; pointer-events: none; }

            .letter {
                --l: clamp(2.6rem, 11vw, 4rem);
                position: relative;
                width: var(--l);
                height: var(--l);
                display: grid;
                place-items: center;
                border: 1px solid var(--line-strong);
                border-radius: 10px;
                background: linear-gradient(160deg, #141414, var(--surface) 60%);
                color: var(--ink);
                font-size: calc(var(--l) * .5);
                font-weight: 800;
                opacity: 0;
                transform: translate3d(0, 22px, 0) scale(.86) rotateX(45deg);
                transition: opacity .6s ease, transform .7s var(--ease), border-color .5s ease, box-shadow .5s ease;
            }
            .letter.show { opacity: 1; transform: translate3d(0, 0, 0) scale(1) rotateX(0); }
            /* A light sweep across each tile. */
            .letter::after {
                content: "";
                position: absolute;
                inset: 0;
                border-radius: inherit;
                background: linear-gradient(115deg, transparent 30%, rgba(255, 255, 255, .22) 48%, transparent 66%);
                background-size: 250% 100%;
                background-position: 120% 0;
                pointer-events: none;
            }
            .letter.show::after { animation: tile-sweep 1.2s ease .1s 1 both; }
            @keyframes tile-sweep { to { background-position: -40% 0; } }

            .word {
                display: none;
                align-self: center;
                justify-self: start;
                white-space: nowrap;
                font-size: clamp(.9rem, 3.6vw, 1.3rem);
                font-weight: 700;
                letter-spacing: .08em;
                text-transform: uppercase;
                color: var(--ink);
                transition: opacity .5s ease;
            }
            .letters.stacked .word { display: block; }

            /* While the camera is on one row, the others step back. */
            .letters.focusing .row:not(.focus) .letter { opacity: .3; }
            .letters.focusing .row:not(.focus) .word { opacity: .35; }
            .letters.focusing .row.focus .letter {
                border-color: var(--ink);
                box-shadow: 0 0 0 1px rgba(255, 255, 255, .1), 0 0 30px rgba(50, 145, 255, .28);
            }

            /* Pull-out moment: the letters light up one after another, spelling L-S-P-D-M-S on the full screen. */
            .row.flash .letter {
                border-color: var(--ink);
                box-shadow: 0 0 26px rgba(255, 255, 255, .18), 0 0 46px rgba(50, 145, 255, .22);
                animation: tile-pop .5s var(--ease);
            }
            .row.flash .letter::after { animation: tile-sweep 1s ease 1 both; }
            @keyframes tile-pop { 50% { transform: scale(1.08); } }

            /* Typewriter: the remaining text keeps its space but stays invisible, so nothing jumps while typing. */
            .rest { visibility: hidden; }
            .typing .typed::after {
                content: "";
                display: inline-block;
                width: 2px;
                height: 1em;
                margin-left: 3px;
                vertical-align: -.12em;
                background: currentColor;
                animation: blink 1s steps(1) infinite;
            }
            @keyframes blink { 50% { opacity: 0; } }

            /* ---------- Horizontal sentence: the words fly out of the column and line up, then the initials spell LSPDMS ---------- */
            .sentence {
                position: absolute;
                left: 50%;
                top: 50%;
                width: min(96vw, 1200px);
                transform: translate(-50%, -50%);
                display: flex;
                flex-wrap: wrap;
                justify-content: center;
                gap: .45em 1.1em;
                font-size: clamp(.8rem, 2.3vw, 1.9rem);
                font-weight: 700;
                letter-spacing: .08em;
                line-height: 1.4;
                text-transform: uppercase;
                color: var(--ink);
                visibility: hidden;
                opacity: 1;
                transition: opacity .9s ease, filter .9s ease;
                pointer-events: none;
            }
            .sentence.out { opacity: 0; filter: blur(10px); }
            /* The six initials close up into the single word LSPDMS. */
            .sentence.merged {
                flex-wrap: nowrap;
                gap: 0;
                font-size: clamp(2rem, 8vw, 4.6rem);
                letter-spacing: .14em;
            }
            .sentence.merged .tail { display: none; }
            .sentence.merged .init.lit { transform: none; text-shadow: 0 0 26px rgba(255, 255, 255, .5), 0 0 60px rgba(50, 145, 255, .35); }
            .sentence .w { display: inline-block; white-space: nowrap; transition: color .6s ease; will-change: transform; }
            .sentence .init { display: inline-block; transition: transform .6s var(--ease), color .6s ease, text-shadow .6s ease; }
            .sentence .tail { display: inline-block; transition: opacity .9s ease; }
            .sentence.dim .tail { opacity: 0; }
            .sentence.spell .w { color: #6f6f6f; }
            .sentence.spell .init.lit {
                color: var(--ink);
                transform: translateY(-4px) scale(1.2);
                text-shadow: 0 0 20px rgba(255, 255, 255, .6);
            }

            /* ---------- Finale: logo, shining wordmark, tagline and a clear way in ---------- */
            .hero {
                display: grid;
                width: 100%;
                min-width: 0;
                justify-items: center;
                gap: clamp(.8rem, 2.4vh, 1.3rem);
                opacity: 0;
                transform: translate3d(0, 18px, 0) scale(.98);
                pointer-events: none;
                transition: opacity 1s ease, transform 1.1s var(--ease);
            }
            .hero.on { opacity: 1; transform: none; pointer-events: auto; }
            .hero-logo {
                width: clamp(58px, 11vw, 84px);
                height: auto;
                filter: drop-shadow(0 0 20px rgba(255, 255, 255, .25));
                animation: bob 6s ease-in-out infinite;
            }
            @keyframes bob { 50% { transform: translateY(-6px); } }
            .wordmark {
                margin: 0;
                font-size: clamp(2.6rem, 10vw, 5.4rem);
                font-weight: 800;
                letter-spacing: .16em;
                line-height: 1;
                padding-left: .16em; /* balances the trailing letter-spacing */
                background: linear-gradient(110deg, #7a7a7a 0%, #ffffff 35%, #ffffff 50%, #9fc6ff 58%, #7a7a7a 75%);
                background-size: 250% 100%;
                -webkit-background-clip: text;
                background-clip: text;
                color: transparent;
                animation: shine 4s ease-in-out infinite;
            }
            @keyframes shine {
                from { background-position: 100% 0; }
                to { background-position: -50% 0; }
            }
            .hero-sub {
                margin: 0;
                color: var(--ink-soft);
                font-size: clamp(.68rem, 2vw, .82rem);
                font-weight: 600;
                letter-spacing: .32em;
                text-transform: uppercase;
            }
            .tagline {
                margin: .2rem 0 0;
                min-height: 1.2em;
                font-size: clamp(1.1rem, 3.6vw, 2rem);
                font-weight: 700;
                letter-spacing: .04em;
                line-height: 1.2;
                text-transform: uppercase;
                color: var(--ink);
            }
            .cta {
                position: relative;
                display: inline-flex;
                align-items: center;
                gap: .7rem;
                margin-top: .6rem;
                padding: .9rem 1.6rem;
                border: 0;
                border-radius: 999px;
                background: var(--ink);
                color: #000;
                font: 700 .92rem 'Montserrat', -apple-system, 'Segoe UI', Arial, sans-serif;
                letter-spacing: .04em;
                text-decoration: none;
                box-shadow: 0 0 0 1px rgba(255, 255, 255, .2), 0 10px 40px rgba(50, 145, 255, .25);
                opacity: 0;
                transform: translate3d(0, 10px, 0);
                transition: opacity .8s ease, transform .8s var(--ease), box-shadow .3s ease;
            }
            .cta.on { opacity: 1; transform: none; }
            .cta:hover { box-shadow: 0 0 0 1px rgba(255, 255, 255, .4), 0 14px 50px rgba(50, 145, 255, .45); }
            .cta:focus-visible { outline: 2px solid var(--accent); outline-offset: 4px; }
            .cta svg { width: 18px; height: 18px; transition: transform .3s var(--ease); }
            .cta:hover svg { transform: translateX(4px); }
            .cta::before {
                content: "";
                position: absolute;
                inset: -6px;
                border-radius: inherit;
                border: 1px solid rgba(255, 255, 255, .35);
                opacity: 0;
                animation: cta-ring 2.6s ease-out 1s infinite;
            }
            @keyframes cta-ring {
                from { opacity: .8; transform: scale(.96); }
                to { opacity: 0; transform: scale(1.18); }
            }
            @media (max-width: 480px) {
                .wordmark { font-size: clamp(2.1rem, 13vw, 3rem); letter-spacing: .1em; padding-left: .1em; }
                .hero-sub { letter-spacing: .18em; line-height: 1.6; }
            }
            .auto-note {
                margin: 0;
                min-height: 1.2em;
                color: var(--ink-dim);
                font-size: .78rem;
                font-weight: 500;
                letter-spacing: .04em;
            }

            /* ---------- Chrome: skip, keyboard hint, progress ---------- */
            .skip-intro {
                position: fixed;
                top: max(1rem, env(safe-area-inset-top, 0px));
                right: max(1rem, env(safe-area-inset-right, 0px));
                z-index: 10;
                padding: .6rem 1rem;
                border: 1px solid var(--line-strong);
                border-radius: 999px;
                background: rgba(0, 0, 0, .55);
                -webkit-backdrop-filter: blur(8px);
                backdrop-filter: blur(8px);
                color: var(--ink);
                font: 600 .85rem 'Montserrat', -apple-system, 'Segoe UI', Arial, sans-serif;
                text-decoration: none;
                transition: background .15s ease, border-color .15s ease;
            }
            .skip-intro:hover { background: var(--surface); border-color: var(--ink-soft); }
            .skip-intro:focus-visible { outline: 2px solid var(--ink); outline-offset: 2px; }

            .hint {
                position: fixed;
                left: 50%;
                bottom: calc(max(1.2rem, env(safe-area-inset-bottom, 0px)) + 10px);
                z-index: 10;
                transform: translateX(-50%);
                margin: 0;
                color: var(--ink-dim);
                font-size: .74rem;
                font-weight: 600;
                letter-spacing: .14em;
                text-transform: uppercase;
                white-space: nowrap;
                opacity: 0;
                transition: opacity 1s ease;
            }
            .hint.on { opacity: 1; }
            .hint kbd {
                padding: .1rem .4rem;
                border: 1px solid var(--line-strong);
                border-radius: 4px;
                color: var(--ink-soft);
                font: inherit;
            }
            @media (hover: none) { .hint .keys { display: none; } }

            .progress {
                position: fixed;
                left: 0;
                right: 0;
                bottom: 0;
                z-index: 10;
                height: 2px;
                background: rgba(255, 255, 255, .06);
            }
            .progress span {
                display: block;
                width: 100%;
                height: 100%;
                transform: scaleX(var(--p, 0));
                transform-origin: left center;
                background: linear-gradient(90deg, rgba(50, 145, 255, .4), #ffffff);
                transition: transform 1.2s var(--ease);
            }

            /* ---------- Reduced motion: the finished composition at once, no movement ---------- */
            .intro.reduced { grid-auto-flow: row; gap: 2rem; }
            .intro.reduced > * { grid-area: auto; }
            .intro.reduced .welcome,
            .intro.reduced .logo-stage,
            .intro.reduced .sentence { display: none; }
            .intro.reduced .hero,
            .intro.reduced .cta { opacity: 1; transform: none; pointer-events: auto; }

            @media (prefers-reduced-motion: reduce) {
                .grid, .beam, .shape, .aurora i, .typing .typed::after, .wordmark, .hero-logo, .cta::before,
                .letter::after, .logo-stage .pulse { animation: none !important; }
                .shape { opacity: .9; }
                .aurora, .field { transform: none; }
                .welcome, .letters, .letter, .word, .sentence, .sentence .w, .sentence .init, .hero, .cta, .skip-intro,
                .logo-stage, .progress span { transition: none; }
            }
        </style>
    </head>
    <body>
        <a class="skip-intro" id="skipIntro" href="{{ url('/login') }}">Skip intro</a>

        <div class="aurora" id="aurora" aria-hidden="true"><i></i><i></i><i></i></div>
        <div class="backdrop" aria-hidden="true"><div class="bgcam" id="bgA"><div class="grid"></div></div></div>
        <canvas id="constellation" aria-hidden="true"></canvas>
        <div class="field" aria-hidden="true"><div class="bgcam" id="bgB"></div></div>
        <div class="beam" aria-hidden="true"></div>
        <div class="vignette" aria-hidden="true"></div>

        <div class="world" id="world">
            <main class="intro" id="intro">
                <div id="logoStage" class="logo-stage" aria-hidden="true">
                    <span class="pulse"></span><span class="pulse"></span><span class="pulse"></span>
                    <img src="{{ asset('images/LSP_logo_white.png') }}" alt="">
                </div>
                <div id="welcome" class="welcome">Welcome to</div>
                <div id="letters" class="letters" role="img" aria-label="LSPDMS">
                    <div class="row"><span class="letter">L</span><span class="word" data-text="LUPON"></span></div>
                    <div class="row"><span class="letter">S</span><span class="word" data-text="SA"></span></div>
                    <div class="row"><span class="letter">P</span><span class="word" data-text="PAGPAPATIBAY"></span></div>
                    <div class="row"><span class="letter">D</span><span class="word" data-text="DATA"></span></div>
                    <div class="row"><span class="letter">M</span><span class="word" data-text="MANAGEMENT"></span></div>
                    <div class="row"><span class="letter">S</span><span class="word" data-text="SYSTEM"></span></div>
                </div>
                <div id="sentence" class="sentence" aria-hidden="true"></div>
                <section id="hero" class="hero" aria-labelledby="heroTitle">
                    <img class="hero-logo" src="{{ asset('images/LSP_logo_white.png') }}" alt="LSP logo">
                    <h1 class="wordmark" id="heroTitle">LSPDMS</h1>
                    <p class="hero-sub">Lupon sa Pagpapatibay Data Management System</p>
                    <p id="tagline" class="tagline">Record Keeping made easier!</p>
                    <a id="cta" class="cta" href="{{ url('/login') }}">
                        Continue to sign in
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                    </a>
                    <p id="autoNote" class="auto-note" aria-live="polite"></p>
                </section>
            </main>
        </div>

        <p class="hint" id="hint"><span class="keys">Press <kbd>Enter</kbd> to skip</span></p>
        <div class="progress" aria-hidden="true"><span id="progress"></span></div>

        <script>
            (() => {
                const loginUrl = '{{ url('/login') }}';

                const root = document.documentElement;
                const world = document.getElementById('world');
                const bgA = document.getElementById('bgA');
                const bgB = document.getElementById('bgB');
                const aurora = document.getElementById('aurora');
                const canvas = document.getElementById('constellation');
                const intro = document.getElementById('intro');
                const logoStage = document.getElementById('logoStage');
                const welcome = document.getElementById('welcome');
                const letters = document.getElementById('letters');
                const sentence = document.getElementById('sentence');
                const hero = document.getElementById('hero');
                const tagline = document.getElementById('tagline');
                const cta = document.getElementById('cta');
                const autoNote = document.getElementById('autoNote');
                const hint = document.getElementById('hint');
                const progress = document.getElementById('progress');
                const skip = document.getElementById('skipIntro');
                const letterEls = [...letters.querySelectorAll('.letter')];
                const rows = [...letters.querySelectorAll('.row')];
                const words = [...letters.querySelectorAll('.word')];
                const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

                const setProgress = value => progress.style.setProperty('--p', String(Math.max(0, Math.min(1, value))));

                /* ---------- Floating shapes (type, x%, y%, size px, duration s, delay s, dx, dy, rotation) ---------- */
                const shapes = [
                    ['ring', 8, 16, 64, 14, 0, 40, -30, 40],
                    ['square', 84, 12, 44, 17, 1, -50, 36, -60],
                    ['plus', 18, 72, 30, 12, 2, 36, -44, 90],
                    ['ring', 76, 70, 88, 19, 0, -34, -40, 30],
                    ['dot', 30, 30, 8, 9, 1, 24, 30, 0],
                    ['square', 62, 82, 36, 15, 3, 40, -34, 80],
                    ['plus', 90, 46, 26, 13, 0, -30, 38, -90],
                    ['dot', 12, 48, 6, 8, 2, 30, -24, 0],
                    ['line', 44, 8, 90, 16, 1, 60, 20, 12],
                    ['ring', 50, 90, 40, 11, 2, -44, -30, 50],
                    ['dot', 70, 24, 7, 10, 0, -26, 34, 0],
                    ['line', 6, 86, 70, 18, 3, 50, -26, -14],
                    ['square', 36, 58, 22, 12, 1, -30, 40, 70],
                    ['plus', 56, 18, 22, 14, 2, 30, 30, 60],
                ];
                const fragment = document.createDocumentFragment();
                shapes.forEach(([type, x, y, size, dur, delay, dx, dy, rot]) => {
                    const el = document.createElement('span');
                    el.className = 'shape ' + type;
                    el.style.cssText = `--x:${x}%;--y:${y}%;--s:${size}px;--d:${dur}s;--delay:${delay}s;--dx:${dx}px;--dy:${dy}px;--r:${rot}deg`;
                    fragment.appendChild(el);
                });
                bgB.appendChild(fragment);

                /* ---------- Constellation: drifting points joined by faint lines, leaning toward the pointer ---------- */
                const context = canvas.getContext('2d');
                const pointer = { x: -9999, y: -9999, active: false };
                let points = [];
                let frame = 0;
                let canvasWidth = 0;
                let canvasHeight = 0;

                function sizeCanvas() {
                    const ratio = Math.min(window.devicePixelRatio || 1, 2);
                    canvasWidth = window.innerWidth;
                    canvasHeight = window.innerHeight;
                    canvas.width = Math.round(canvasWidth * ratio);
                    canvas.height = Math.round(canvasHeight * ratio);
                    context.setTransform(ratio, 0, 0, ratio, 0, 0);
                    const count = Math.max(24, Math.min(80, Math.round((canvasWidth * canvasHeight) / 17000)));
                    points = Array.from({ length: count }, (_, i) => ({
                        x: ((i * 97) % 100) / 100 * canvasWidth + Math.random() * 40,
                        y: ((i * 53) % 100) / 100 * canvasHeight + Math.random() * 40,
                        vx: (Math.random() - .5) * .25,
                        vy: (Math.random() - .5) * .25,
                        r: Math.random() * 1.3 + .4,
                    }));
                }

                function drawConstellation(animate) {
                    context.clearRect(0, 0, canvasWidth, canvasHeight);
                    const link = Math.min(150, canvasWidth / 7);
                    for (const p of points) {
                        if (animate) {
                            if (pointer.active) {
                                const dx = pointer.x - p.x;
                                const dy = pointer.y - p.y;
                                const distance = Math.hypot(dx, dy);
                                if (distance < 180 && distance > 1) {
                                    p.vx += (dx / distance) * .012;
                                    p.vy += (dy / distance) * .012;
                                }
                            }
                            p.vx *= .985;
                            p.vy *= .985;
                            p.vx += (Math.random() - .5) * .01;
                            p.vy += (Math.random() - .5) * .01;
                            p.x += p.vx;
                            p.y += p.vy;
                            if (p.x < -20) p.x = canvasWidth + 20;
                            if (p.x > canvasWidth + 20) p.x = -20;
                            if (p.y < -20) p.y = canvasHeight + 20;
                            if (p.y > canvasHeight + 20) p.y = -20;
                        }
                    }
                    for (let i = 0; i < points.length; i++) {
                        const a = points[i];
                        for (let j = i + 1; j < points.length; j++) {
                            const b = points[j];
                            const distance = Math.hypot(a.x - b.x, a.y - b.y);
                            if (distance < link) {
                                context.strokeStyle = `rgba(150, 190, 255, ${(1 - distance / link) * .22})`;
                                context.lineWidth = 1;
                                context.beginPath();
                                context.moveTo(a.x, a.y);
                                context.lineTo(b.x, b.y);
                                context.stroke();
                            }
                        }
                        context.fillStyle = 'rgba(255, 255, 255, .75)';
                        context.beginPath();
                        context.arc(a.x, a.y, a.r, 0, Math.PI * 2);
                        context.fill();
                    }
                }

                function tick() {
                    drawConstellation(true);
                    frame = requestAnimationFrame(tick);
                }

                sizeCanvas();
                window.addEventListener('resize', () => {
                    sizeCanvas();
                    if (reduceMotion) drawConstellation(false);
                });

                // Pointer parallax for the background layers, plus the constellation's pull.
                window.addEventListener('pointermove', event => {
                    pointer.x = event.clientX;
                    pointer.y = event.clientY;
                    pointer.active = true;
                    if (reduceMotion) return;
                    root.style.setProperty('--px', ((event.clientX / window.innerWidth) - .5).toFixed(3));
                    root.style.setProperty('--py', ((event.clientY / window.innerHeight) - .5).toFixed(3));
                }, { passive: true });
                window.addEventListener('pointerleave', () => { pointer.active = false; });

                // Nothing animates in a hidden tab.
                document.addEventListener('visibilitychange', () => {
                    if (reduceMotion) return;
                    cancelAnimationFrame(frame);
                    if (!document.hidden) frame = requestAnimationFrame(tick);
                });

                /* ---------- Typewriter helpers: typed text + invisible remainder (keeps layout steady) ---------- */
                function prep(el, text) {
                    const typed = document.createElement('span');
                    typed.className = 'typed';
                    const rest = document.createElement('span');
                    rest.className = 'rest';
                    rest.textContent = text;
                    el.textContent = '';
                    el.append(typed, rest);
                    el._typed = typed;
                    el._rest = rest;
                    el._text = text;
                }

                function fill(el) {
                    el._typed.textContent = el._text;
                    el._rest.textContent = '';
                }

                words.forEach(word => prep(word, word.dataset.text));
                prep(tagline, tagline.textContent.trim());

                /* The horizontal sentence: one span per word, the first letter wrapped so it can light up later. */
                const sentenceWords = words.map(word => {
                    const text = word.dataset.text;
                    const span = document.createElement('span');
                    span.className = 'w';
                    const initial = document.createElement('b');
                    initial.className = 'init';
                    initial.style.fontWeight = 'inherit';
                    initial.textContent = text[0];
                    const tail = document.createElement('span');
                    tail.className = 'tail';
                    tail.textContent = text.slice(1);
                    span.append(initial, tail);
                    sentence.appendChild(span);
                    return span;
                });

                /* ---------- Sequence control: one run, one cleanup, one navigation ---------- */
                const timers = [];
                const animations = [];
                let stopped = false;
                let navigated = false;

                // Pending sleeps are cleared on stop, so no stale step can ever run after a skip.
                const sleep = ms => new Promise(resolve => timers.push(setTimeout(resolve, ms)));

                function stop() {
                    stopped = true;
                    timers.forEach(clearTimeout);
                    timers.length = 0;
                    animations.forEach(animation => animation.cancel());
                    animations.length = 0;
                }

                function go() {
                    if (navigated || stopped) return;
                    navigated = true;
                    stop();
                    window.location.href = loginUrl;
                }

                // Skip: cancel every pending step, then let the link navigate normally.
                skip.addEventListener('click', () => { navigated = true; stop(); });
                cta.addEventListener('click', () => { navigated = true; stop(); });
                window.addEventListener('pagehide', stop);
                window.addEventListener('keydown', event => {
                    if (['Enter', ' ', 'Escape'].includes(event.key) && !event.repeat) {
                        if (document.activeElement === skip || document.activeElement === cta) return;
                        event.preventDefault();
                        go();
                    }
                });

                async function type(el, speed) {
                    el.classList.add('typing');
                    for (let i = 1; i <= el._text.length; i++) {
                        el._typed.textContent = el._text.slice(0, i);
                        el._rest.textContent = el._text.slice(i);
                        await sleep(speed);
                    }
                    el.classList.remove('typing');
                }

                /* ---------- Camera: translate + scale the world, with a lighter parallax on the background ---------- */
                function setCamera(x, y, s, ms) {
                    const transition = `transform ${ms}ms cubic-bezier(.45, 0, .2, 1)`;
                    world.style.transition = transition;
                    world.style.transform = `translate3d(${x}px, ${y}px, 0) scale(${s})`;
                    bgA.style.transition = transition;
                    bgA.style.transform = `translate3d(${x * .25}px, ${y * .25}px, 0) scale(${1 + (s - 1) * .25})`;
                    bgB.style.transition = transition;
                    bgB.style.transform = `translate3d(${x * .55}px, ${y * .55}px, 0) scale(${1 + (s - 1) * .5})`;
                }

                // Centre a box (measured with the camera at rest) on screen at the given zoom.
                function lookAt(box, s, ms) {
                    const dx = box.left + box.width / 2 - window.innerWidth / 2;
                    const dy = box.top + box.height / 2 - window.innerHeight / 2;
                    setCamera(-dx * s, -dy * s, s, ms);
                }

                function union(rects) {
                    const left = Math.min(...rects.map(r => r.left));
                    const top = Math.min(...rects.map(r => r.top));
                    const right = Math.max(...rects.map(r => r.right));
                    const bottom = Math.max(...rects.map(r => r.bottom));
                    return { left, top, width: right - left, height: bottom - top };
                }

                // Finale: a clear button in, with a short countdown before continuing on its own.
                async function finale(seconds) {
                    cta.classList.add('on');
                    for (let left = seconds; left > 0; left--) {
                        autoNote.textContent = `Continuing automatically in ${left}s`;
                        setProgress(1 - (left - 1) / seconds * .08);
                        await sleep(1000);
                    }
                    autoNote.textContent = '';
                    world.style.transition = 'opacity 900ms ease';
                    world.style.opacity = '0';
                    await sleep(950);
                    go();
                }

                /* ---------- Reduced motion: finished composition, no movement ---------- */
                if (reduceMotion) {
                    intro.classList.add('reduced');
                    letters.classList.add('stacked');
                    letterEls.forEach(el => el.classList.add('show'));
                    words.forEach(fill);
                    fill(tagline);
                    hero.classList.add('on');
                    aurora.classList.add('on');
                    canvas.classList.add('on');
                    drawConstellation(false);
                    autoNote.textContent = 'Continuing automatically in a few seconds';
                    setProgress(1);
                    sleep(7000).then(go);
                    return;
                }

                frame = requestAnimationFrame(tick);

                /* ---------- Animated sequence (paced so each moment can be read) ---------- */
                async function run() {
                    // 0. The scene lights up and the logo arrives
                    await sleep(150);
                    aurora.classList.add('on');
                    canvas.classList.add('on');
                    logoStage.classList.add('show');
                    setProgress(.04);
                    await sleep(700);
                    hint.classList.add('on');
                    await sleep(1300);
                    logoStage.classList.add('hide');
                    await sleep(600);

                    // 1. Welcome
                    welcome.classList.add('show');
                    setProgress(.1);
                    await sleep(1500);
                    welcome.classList.add('hide');
                    await sleep(600);

                    // 2. L S P D M S appear in a row
                    for (const el of letterEls) {
                        el.classList.add('show');
                        await sleep(140);
                    }
                    setProgress(.18);
                    await sleep(700);

                    // 3. Row -> column (transform only)
                    const first = letterEls.map(el => el.getBoundingClientRect());
                    letters.classList.add('stacked');
                    letterEls.forEach((el, i) => {
                        const last = el.getBoundingClientRect();
                        animations.push(el.animate(
                            [{ transform: `translate(${first[i].left - last.left}px, ${first[i].top - last.top}px)` }, { transform: 'translate(0, 0)' }],
                            { duration: 900, easing: 'cubic-bezier(.22, .9, .32, 1)' }
                        ));
                    });
                    await sleep(1000);

                    // Measure once, with the camera at rest.
                    const vw = window.innerWidth;
                    const vh = window.innerHeight;
                    const columnBox = union(letterEls.map(el => el.getBoundingClientRect()));
                    const rowBoxes = rows.map(row => union([
                        row.querySelector('.letter').getBoundingClientRect(),
                        row.querySelector('.word').getBoundingClientRect(),
                    ]));
                    const listBox = union(rowBoxes);
                    const fit = box => Math.min(1, (vh * .82) / box.height, (vw * .9) / box.width);
                    const widest = Math.max(...rowBoxes.map(box => box.width));
                    const zoom = Math.max(1.2, Math.min(2.2, (vw * .82) / widest));

                    // 4. Camera frames the column L S P D M S
                    lookAt(columnBox, fit(columnBox), 1000);
                    await sleep(1500);

                    // 5. Camera closes in on each letter, then follows down the column while its word types
                    letters.classList.add('focusing');
                    for (let i = 0; i < rows.length; i++) {
                        rows.forEach((row, j) => row.classList.toggle('focus', j === i));
                        lookAt(rowBoxes[i], zoom, i === 0 ? 900 : 500);
                        await sleep(i === 0 ? 950 : 550);
                        await type(words[i], 28);
                        setProgress(.22 + (i + 1) * .06);
                        await sleep(150);
                    }

                    // 6. Hover out: the camera pulls back so the whole list is on screen again,
                    //    then the six letters light up one after another and the full list is held.
                    letters.classList.remove('focusing');
                    rows.forEach(row => row.classList.remove('focus'));
                    lookAt(listBox, fit(listBox), 1500);
                    await sleep(1700);
                    for (const row of rows) {
                        row.classList.add('flash');
                        await sleep(280);
                    }
                    setProgress(.64);
                    await sleep(1600);

                    // 7. Retype horizontally: every word peels off its row and flies into one line.
                    setCamera(0, 0, 1, 1000);
                    await sleep(1050);

                    const from = words.map(word => word.getBoundingClientRect());
                    const to = sentenceWords.map(span => span.getBoundingClientRect());
                    sentenceWords.forEach((span, i) => {
                        const dx = from[i].left + from[i].width / 2 - (to[i].left + to[i].width / 2);
                        const dy = from[i].top + from[i].height / 2 - (to[i].top + to[i].height / 2);
                        const scale = to[i].width ? from[i].width / to[i].width : 1;
                        animations.push(span.animate(
                            [{ transform: `translate(${dx}px, ${dy}px) scale(${scale})` }, { transform: 'translate(0, 0) scale(1)' }],
                            { duration: 1400, delay: i * 120, easing: 'cubic-bezier(.22, .9, .32, 1)', fill: 'backwards' }
                        ));
                    });
                    sentence.style.visibility = 'visible';
                    letters.classList.add('fade');
                    await sleep(1400 + 120 * (sentenceWords.length - 1) + 300);
                    setProgress(.74);

                    // 8. The initials light up one by one and spell L S P D M S inside the sentence
                    sentence.classList.add('spell');
                    for (const span of sentenceWords) {
                        span.querySelector('.init').classList.add('lit');
                        await sleep(300);
                    }
                    await sleep(900);

                    // 8b. The other letters dim out; only L S P D M S remain, then they close up into one word
                    sentence.classList.add('dim');
                    await sleep(1100);
                    const initCenters = sentenceWords.map(span => {
                        const r = span.querySelector('.init').getBoundingClientRect();
                        return { x: r.left + r.width / 2, y: r.top + r.height / 2 };
                    });
                    const sizeBefore = parseFloat(getComputedStyle(sentence).fontSize);
                    sentence.classList.add('merged');
                    const sizeAfter = parseFloat(getComputedStyle(sentence).fontSize);
                    sentenceWords.forEach((span, i) => {
                        const r = span.getBoundingClientRect();
                        const dx = initCenters[i].x - (r.left + r.width / 2);
                        const dy = initCenters[i].y - (r.top + r.height / 2);
                        animations.push(span.animate(
                            [{ transform: `translate(${dx}px, ${dy}px) scale(${sizeBefore / sizeAfter})` }, { transform: 'translate(0, 0) scale(1)' }],
                            { duration: 1200, delay: i * 60, easing: 'cubic-bezier(.22, .9, .32, 1)', fill: 'backwards' }
                        ));
                    });
                    await sleep(1200 + 60 * (sentenceWords.length - 1) + 200);
                    setProgress(.84);
                    await sleep(1800);

                    // 9. The single word gives way to the finale
                    sentence.classList.add('out');
                    hint.classList.remove('on');
                    await sleep(900);

                    // 10. Finale: logo, shining wordmark, typed tagline, then a clear way in
                    hero.classList.add('on');
                    await sleep(700);
                    await type(tagline, 60);
                    setProgress(.92);
                    await sleep(400);
                    await finale(6);
                }

                run();
            })();
        </script>
    </body>
</html>
