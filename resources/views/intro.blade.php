<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'LSPDMS') }}</title>
        @include('components.brand-meta')
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&display=swap" rel="stylesheet">
        <style>
            :root { --blue: #0a4a8a; --soft-blue: #eaf3ff; }
            * { box-sizing: border-box; }
            html, body { margin: 0; min-height: 100%; }
            body { min-height: 100vh; overflow: hidden; display: grid; place-items: center; background: #fff; color: var(--blue); font-family: 'Montserrat', sans-serif; }
            .intro { width: min(90vw, 700px); padding: 2rem; text-align: center; }
            .welcome { font-size: clamp(1.8rem, 4vw, 3rem); font-weight: 600; letter-spacing: .12em; text-transform: uppercase; opacity: 0; transform: translateY(10px); transition: opacity .8s ease, transform .8s ease; }
            .welcome.show { opacity: 1; transform: translateY(0); }
            .welcome.hide { opacity: 0; transform: translateY(-10px); }
            .letters { display: flex; justify-content: center; gap: .65rem; margin: 2rem auto; min-height: 4.5rem; transition: opacity .5s ease; }
            .letters.stacked { display: grid; grid-template-columns: auto 1fr; justify-content: start; row-gap: .65rem; column-gap: .9rem; width: fit-content; margin-left: 0; margin-right: auto; text-align: left; }
            .letters.stacked .row { display: contents; }
            .letter { width: 4rem; height: 4rem; display: grid; place-items: center; border: 1px solid rgba(10, 107, 214, .18); border-radius: 16px; background: var(--soft-blue); color: #0a6bd6; font-size: 2rem; font-weight: 800; opacity: 0; transform: translateY(18px) scale(.85); transition: opacity .45s ease, transform .45s ease; }
            .letter.show { opacity: 1; transform: translateY(0) scale(1); }
            .word { align-self: center; overflow: hidden; max-width: 0; white-space: nowrap; opacity: 0; font-size: clamp(1rem, 2vw, 1.35rem); font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
            .row.active .word { max-width: 480px; opacity: 1; }
            .tagline { position: fixed; top: 50%; left: 50%; width: calc(100vw - 2rem); transform: translate(-50%, -50%); font-size: clamp(2rem, 5vw, 4.5rem); font-weight: 600; letter-spacing: .02em; opacity: 0; transition: opacity .6s ease; }
            .tagline.show { opacity: 1; }
            .fade { opacity: 0; pointer-events: none; }
            .skip-intro { position:fixed; top:1rem; right:1rem; z-index:10; padding:.65rem 1rem; border:1px solid rgba(10,107,214,.25); border-radius:999px; background:#fff; color:#0a4a8a; font:600 .9rem 'Montserrat',sans-serif; text-decoration:none; box-shadow:0 5px 18px rgba(10,74,138,.12); }
            .skip-intro:hover { background:var(--soft-blue); }
            @media (max-width: 480px) { .letter { width: 3.2rem; height: 3.2rem; font-size: 1.6rem; } }
        </style>
    </head>
    <body>
        <a class="skip-intro" href="{{ url('/login') }}">Skip intro</a>
        <main class="intro">
            <div id="welcome" class="welcome">Welcome to</div>
            <div id="letters" class="letters" aria-label="LSPDMS">
                <div class="row" data-word="LUPON"><span class="letter">L</span><span class="word"></span></div>
                <div class="row" data-word="SA"><span class="letter">S</span><span class="word"></span></div>
                <div class="row" data-word="PAGPAPATIBAY"><span class="letter">P</span><span class="word"></span></div>
                <div class="row" data-word="DATA"><span class="letter">D</span><span class="word"></span></div>
                <div class="row" data-word="MANAGEMENT"><span class="letter">M</span><span class="word"></span></div>
                <div class="row" data-word="SYSTEM"><span class="letter">S</span><span class="word"></span></div>
            </div>
            <div id="tagline" class="tagline"></div>
        </main>
        <script>
            const wait = (ms) => new Promise(resolve => setTimeout(resolve, ms));
            const typeText = (element, text, speed) => new Promise(resolve => {
                let index = 0;
                const timer = setInterval(() => {
                    element.textContent += text[index++] ?? '';
                    if (index >= text.length) { clearInterval(timer); resolve(); }
                }, speed);
            });
            async function runIntro() {
                const welcome = document.getElementById('welcome');
                const letters = document.getElementById('letters');
                const letterEls = [...document.querySelectorAll('.letter')];
                const rows = [...document.querySelectorAll('.row')];
                const tagline = document.getElementById('tagline');
                await wait(300); welcome.classList.add('show'); await wait(1100); welcome.classList.add('hide'); await wait(700); welcome.remove();
                for (const letter of letterEls) { letter.classList.add('show'); await wait(170); }
                await wait(500);
                const firstRects = letterEls.map(element => element.getBoundingClientRect());
                letters.classList.add('stacked');
                const secondRects = letterEls.map(element => element.getBoundingClientRect());
                letterEls.forEach((element, index) => { element.style.transition = 'none'; element.style.transform = `translate(${firstRects[index].left - secondRects[index].left}px, ${firstRects[index].top - secondRects[index].top}px)`; });
                void letters.offsetWidth;
                letterEls.forEach(element => { element.style.transition = 'transform .75s cubic-bezier(.22,.9,.32,1)'; element.style.transform = 'translate(0, 0)'; });
                await wait(850);
                for (const row of rows) { row.classList.add('active'); await typeText(row.querySelector('.word'), row.dataset.word, 45); await wait(220); }
                await wait(700); letters.classList.add('fade'); await wait(550);
                tagline.classList.add('show'); await typeText(tagline, 'Record Keeping made easier!', 55); await wait(1100);
                window.location.href = '{{ url('/login') }}';
            }
            runIntro();
        </script>
    </body>
</html>
