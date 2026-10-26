import Lenis from 'lenis';
import '../css/motion.css';
// Idle sign-out warning + activity heartbeat (every signed-in page).
import './idle-session';

/*
 * LSPDMS motion layer (loaded on every signed-in page by layouts/app.blade.php):
 * smooth inertial scrolling, scroll-in/out reveals, 3D tilt + spotlight on cards,
 * a 3D backdrop, a scroll progress bar and a back-to-top button.
 */
const root = document.documentElement;
const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const finePointer = window.matchMedia('(hover: hover) and (pointer: fine)').matches;
const messagesPage = document.body.classList.contains('has-messages-page');

/* ---------- Scroll progress + back to top ---------- */
const progress = document.createElement('div');
progress.className = 'motion-progress';
progress.setAttribute('aria-hidden', 'true');
document.body.append(progress);

const toTop = document.createElement('button');
toTop.type = 'button';
toTop.className = 'motion-top';
toTop.setAttribute('aria-label', 'Back to top');
toTop.innerHTML = '<svg viewBox="0 0 44 44" aria-hidden="true"><circle cx="22" cy="22" r="20"></circle></svg>'
    + '<svg class="arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 19V5M5 12l7-7 7 7"/></svg>';
document.body.append(toTop);

function onScroll() {
    const max = Math.max(1, document.documentElement.scrollHeight - window.innerHeight);
    const ratio = Math.min(1, Math.max(0, window.scrollY / max));
    root.style.setProperty('--scroll', ratio.toFixed(4));
    toTop.classList.toggle('on', window.scrollY > window.innerHeight * .6);
}
window.addEventListener('scroll', onScroll, { passive: true });
window.addEventListener('resize', onScroll, { passive: true });
onScroll();

/* ---------- Smooth inertial scrolling (desktop mouse/trackpad only) ---------- */
let lenis = null;
const scrollsInside = node => {
    // Let anything that scrolls on its own (tables, lists, panels, dialogs) keep native scrolling.
    for (let el = node; el && el !== document.body; el = el.parentElement) {
        if (el.matches?.('dialog, [data-lenis-prevent], textarea, select, .chat-widget, .chat-window')) return true;
        const style = getComputedStyle(el);
        const scrollableY = /(auto|scroll)/.test(style.overflowY) && el.scrollHeight > el.clientHeight + 1;
        const scrollableX = /(auto|scroll)/.test(style.overflowX) && el.scrollWidth > el.clientWidth + 1;
        if (scrollableY || scrollableX) return true;
    }
    return false;
};

if (!reduceMotion && finePointer && !messagesPage) {
    lenis = new Lenis({
        duration: 1.05,
        easing: t => 1 - Math.pow(1 - t, 4),
        smoothWheel: true,
        wheelMultiplier: 1,
        prevent: scrollsInside,
    });
    lenis.on('scroll', onScroll);
    const raf = time => { lenis.raf(time); requestAnimationFrame(raf); };
    requestAnimationFrame(raf);

    // Never scroll the page behind an open dialog.
    const syncDialogs = () => (document.querySelector('dialog[open]') ? lenis.stop() : lenis.start());
    new MutationObserver(syncDialogs).observe(document.body, { subtree: true, attributes: true, attributeFilter: ['open'] });

    // In-page links glide instead of jumping.
    document.addEventListener('click', event => {
        const link = event.target.closest?.('a[href^="#"]');
        if (!link || link.getAttribute('href') === '#') return;
        const target = document.querySelector(link.getAttribute('href'));
        if (!target) return;
        event.preventDefault();
        lenis.scrollTo(target, { offset: -72 });
    });
}

toTop.addEventListener('click', () => {
    if (lenis) lenis.scrollTo(0);
    else window.scrollTo({ top: 0, behavior: reduceMotion ? 'auto' : 'smooth' });
});

if (!reduceMotion) {
    /* ---------- 3D backdrop ---------- */
    const backdrop = document.createElement('div');
    backdrop.className = 'motion-backdrop';
    backdrop.setAttribute('aria-hidden', 'true');
    backdrop.innerHTML = '<div class="floor"></div>';
    document.body.prepend(backdrop);
    const floor = backdrop.firstElementChild;
    // The floor drifts slightly with the scroll for depth.
    const shiftFloor = () => floor.style.setProperty('--floor-shift', `${Math.min(window.scrollY * .08, 60)}px`);
    window.addEventListener('scroll', shiftFloor, { passive: true });
    lenis?.on('scroll', shiftFloor);

    /* ---------- Scroll in / out reveals ---------- */
    const content = document.querySelector('main.content');
    const CANDIDATES = [
        'section', 'article', 'figure', 'form > fieldset', 'table',
        '[class*="card"]', '[class*="panel"]', '[class*="tile"]', '[data-reveal]',
    ].join(',');
    if (content && !messagesPage) {
        const picked = [];
        content.querySelectorAll(CANDIDATES).forEach(el => {
            if (el.closest('dialog, .modal, [data-no-reveal], .chat-widget')) return;
            if (picked.some(parent => parent.contains(el))) return; // animate the outermost block only
            const style = getComputedStyle(el);
            if (style.position === 'fixed' || style.position === 'sticky' || el.offsetHeight < 40) return;
            picked.push(el);
        });
        // Keep it to a sensible number of top-level blocks.
        picked.slice(0, 80).forEach((el, i) => {
            el.classList.add('reveal');
            el.style.setProperty('--reveal-delay', `${Math.min(i % 4, 3) * 70}ms`);
        });

        const observer = new IntersectionObserver(entries => {
            entries.forEach(entry => {
                const el = entry.target;
                if (entry.isIntersecting) {
                    el.classList.add('is-in');
                    el.classList.remove('is-out-top');
                } else {
                    el.classList.remove('is-in');
                    el.classList.toggle('is-out-top', entry.boundingClientRect.top < 0);
                }
            });
        }, { threshold: 0.08, rootMargin: '0px 0px -6% 0px' });
        picked.slice(0, 80).forEach(el => observer.observe(el));

        // Anything already on screen shows at once (no flash of hidden content on load).
        requestAnimationFrame(() => picked.forEach(el => {
            const box = el.getBoundingClientRect();
            if (box.top < window.innerHeight && box.bottom > 0) el.classList.add('is-in');
        }));
    }

    /* ---------- 3D tilt + spotlight on cards (mouse only) ---------- */
    if (finePointer && content && !messagesPage) {
        const cards = [...content.querySelectorAll('[class*="card"], [class*="panel"], [class*="tile"]')]
            .filter(el => !el.closest('dialog, .modal, [data-no-tilt]') && el.offsetWidth > 120 && el.offsetHeight > 60)
            .filter((el, _i, all) => !all.some(other => other !== el && other.contains(el)));

        cards.forEach(card => {
            const style = getComputedStyle(card);
            // Only add the spotlight where it cannot hide an existing background image.
            if (style.backgroundImage === 'none') card.classList.add('spotlight');
            // Big blocks (tables, long panels) get the spotlight only; tilting them would feel heavy.
            const canTilt = card.offsetWidth <= 640 && card.offsetHeight <= 520;
            if (canTilt) card.classList.add('tilt');

            let frame = 0;
            card.addEventListener('pointermove', event => {
                cancelAnimationFrame(frame);
                frame = requestAnimationFrame(() => {
                    const box = card.getBoundingClientRect();
                    const x = (event.clientX - box.left) / box.width;
                    const y = (event.clientY - box.top) / box.height;
                    card.style.setProperty('--spot-x', `${(x * 100).toFixed(1)}%`);
                    card.style.setProperty('--spot-y', `${(y * 100).toFixed(1)}%`);
                    if (canTilt && !event.target.closest('input, textarea, select, button, a, canvas, table')) {
                        card.classList.add('is-tilting');
                        card.style.setProperty('--tilt-x', `${((.5 - y) * 6).toFixed(2)}deg`);
                        card.style.setProperty('--tilt-y', `${((x - .5) * 7).toFixed(2)}deg`);
                    } else {
                        card.classList.remove('is-tilting');
                    }
                });
            });
            card.addEventListener('pointerleave', () => {
                cancelAnimationFrame(frame);
                card.classList.remove('is-tilting');
                card.style.removeProperty('--tilt-x');
                card.style.removeProperty('--tilt-y');
            });
        });
    }
}
