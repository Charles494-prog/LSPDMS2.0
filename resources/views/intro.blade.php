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
        <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&display=swap" rel="stylesheet">
        <style>
            :root {
                color-scheme: dark;
                --bg: #000000;
                --ink: #ffffff;
                --ink-soft: #a1a1a1;
                --line: #262626;
                --line-strong: #3a3a3a;
                --surface: #0a0a0a;
                --ease: cubic-bezier(.22, .9, .32, 1);
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

            /* ---------- Moving background (reacts to the camera with parallax) ---------- */
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
                background: linear-gradient(90deg, transparent, #5a5a5a, transparent);
                opacity: .45;
                will-change: transform;
                animation: beam 5.5s linear infinite;
            }

            @keyframes beam {
                from { transform: translate3d(0, -2vh, 0); }
                to { transform: translate3d(0, 102vh, 0); }
            }

            .field { position: fixed; inset: 0; overflow: hidden; pointer-events: none; }

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
                place-items: center;
                width: min(92vw, 720px);
                padding: 2rem 1rem;
                text-align: center;
            }
            /* Every stage shares one cell, so nothing shifts when stages swap. */
            .intro > * { grid-area: 1 / 1; }

            .welcome {
                font-size: clamp(1.5rem, 4.5vw, 2.8rem);
                font-weight: 600;
                letter-spacing: .14em;
                text-transform: uppercase;
                color: var(--ink);
                opacity: 0;
                transform: translate3d(0, 10px, 0);
                transition: opacity .9s ease, transform .9s ease;
            }
            .welcome.show { opacity: 1; transform: translate3d(0, 0, 0); }
            .welcome.hide { opacity: 0; transform: translate3d(0, -10px, 0); }

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
                width: var(--l);
                height: var(--l);
                display: grid;
                place-items: center;
                border: 1px solid var(--line-strong);
                border-radius: 8px;
                background: var(--surface);
                color: var(--ink);
                font-size: calc(var(--l) * .5);
                font-weight: 800;
                opacity: 0;
                transform: translate3d(0, 16px, 0) scale(.9);
                transition: opacity .6s ease, transform .6s var(--ease), border-color .5s ease, box-shadow .5s ease;
            }
            .letter.show { opacity: 1; transform: translate3d(0, 0, 0) scale(1); }

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
            .letters.focusing .row.focus .letter { border-color: var(--ink); }

            /* Pull-out moment: the letters light up one after another, spelling L-S-P-D-M-S on the full screen. */
            .row.flash .letter { border-color: var(--ink); box-shadow: 0 0 26px rgba(255, 255, 255, .16); }

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
                transition: opacity .9s ease;
                pointer-events: none;
            }
            .sentence.out { opacity: 0; }
            /* The six initials close up into the single word LSPDMS. */
            .sentence.merged {
                flex-wrap: nowrap;
                gap: 0;
                font-size: clamp(2rem, 8vw, 4.6rem);
                letter-spacing: .14em;
            }
            .sentence.merged .tail { display: none; }
            .sentence.merged .init.lit { transform: none; text-shadow: 0 0 26px rgba(255, 255, 255, .5); }
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

            .tagline {
                margin: 0;
                font-size: clamp(1.5rem, 5vw, 3.2rem);
                font-weight: 700;
                letter-spacing: .04em;
                line-height: 1.2;
                text-transform: uppercase;
                color: var(--ink);
                opacity: 0;
                transition: opacity .8s ease;
                pointer-events: none;
            }
            .tagline.on { opacity: 1; }

            /* ---------- Skip ---------- */
            .skip-intro {
                position: fixed;
                top: max(1rem, env(safe-area-inset-top, 0px));
                right: max(1rem, env(safe-area-inset-right, 0px));
                z-index: 10;
                padding: .6rem 1rem;
                border: 1px solid var(--line-strong);
                border-radius: 6px;
                background: rgba(0, 0, 0, .6);
                color: var(--ink);
                font: 600 .85rem 'Montserrat', -apple-system, 'Segoe UI', Arial, sans-serif;
                text-decoration: none;
                transition: background .15s ease, border-color .15s ease;
            }
            .skip-intro:hover { background: var(--surface); border-color: var(--ink-soft); }
            .skip-intro:focus-visible { outline: 2px solid var(--ink); outline-offset: 2px; }

            /* ---------- Reduced motion: the finished composition at once, no movement ---------- */
            .intro.reduced { grid-auto-flow: row; gap: 2rem; }
            .intro.reduced > * { grid-area: auto; }
            .intro.reduced .welcome,
            .intro.reduced .sentence { display: none; }
            .intro.reduced .tagline { opacity: 1; }

            @media (prefers-reduced-motion: reduce) {
                .grid, .beam, .shape, .typing .typed::after { animation: none; }
                .shape { opacity: .9; }
                .welcome, .letters, .letter, .word, .sentence, .sentence .w, .sentence .init, .tagline, .skip-intro { transition: none; }
            }
        </style>
    </head>
    <body>
        <a class="skip-intro" id="skipIntro" href="{{ url('/login') }}">Skip intro</a>

        <div class="backdrop" aria-hidden="true"><div class="bgcam" id="bgA"><div class="grid"></div></div></div>
        <div class="field" aria-hidden="true"><div class="bgcam" id="bgB"></div></div>
        <div class="beam" aria-hidden="true"></div>

        <div class="world" id="world">
            <main class="intro" id="intro">
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
                <p id="tagline" class="tagline">Record Keeping made easier!</p>
            </main>
        </div>

        <script>
            (() => {
                const loginUrl = '{{ url('/login') }}';

                const world = document.getElementById('world');
                const bgA = document.getElementById('bgA');
                const bgB = document.getElementById('bgB');
                const intro = document.getElementById('intro');
                const welcome = document.getElementById('welcome');
                const letters = document.getElementById('letters');
                const sentence = document.getElementById('sentence');
                const tagline = document.getElementById('tagline');
                const skip = document.getElementById('skipIntro');
                const letterEls = [...letters.querySelectorAll('.letter')];
                const rows = [...letters.querySelectorAll('.row')];
                const words = [...letters.querySelectorAll('.word')];
                const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

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
                window.addEventListener('pagehide', stop);

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

                /* ---------- Reduced motion: finished composition, no movement ---------- */
                if (reduceMotion) {
                    intro.classList.add('reduced');
                    letters.classList.add('stacked');
                    letterEls.forEach(el => el.classList.add('show'));
                    words.forEach(fill);
                    fill(tagline);
                    sleep(5000).then(go);
                    return;
                }

                /* ---------- Animated sequence (paced slowly so each moment can be read) ---------- */
                async function run() {
                    // 1. Welcome
                    await sleep(300);
                    welcome.classList.add('show');
                    await sleep(1500);
                    welcome.classList.add('hide');
                    await sleep(600);

                    // 2. L S P D M S appear in a row
                    for (const el of letterEls) {
                        el.classList.add('show');
                        await sleep(140);
                    }
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
                        await sleep(150);
                    }

                    // 6. Hover out: the camera pulls back slowly so the whole list is on screen again,
                    //    then the six letters light up one after another and the full list is held.
                    letters.classList.remove('focusing');
                    rows.forEach(row => row.classList.remove('focus'));
                    lookAt(listBox, fit(listBox), 1500);
                    await sleep(1700);
                    for (const row of rows) {
                        row.classList.add('flash');
                        await sleep(280);
                    }
                    await sleep(1800);

                    // 7. Retype horizontally: the camera settles, then every word peels off its row and flies
                    //    into one line (the letter boxes dissolve behind them).
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
                    await sleep(2200);

                    // 9. Sentence fades out
                    sentence.classList.add('out');
                    await sleep(1100);

                    // 10. Tagline: typed slowly, held, then the whole screen fades before the login page opens
                    tagline.classList.add('on');
                    await type(tagline, 75);
                    await sleep(3000);
                    world.style.transition = 'opacity 1100ms ease';
                    world.style.opacity = '0';
                    await sleep(1300);

                    go();
                }

                run();
            })();
        </script>
    </body>
</html>