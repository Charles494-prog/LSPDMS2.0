@extends('layouts.app')

@section('title', 'Home')

@php
    // Pasugo section picture (public/images/pgmHomepage-001.jpeg). Change the path here if yours lives elsewhere, e.g. 'img/pgmHomepage-001.jpeg'.
    $pasugoImage = asset('images/pgmHomepage-001.jpeg');

    $icons = [
        'globe'     => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.8 3 2.8 15 0 18M12 3c-2.8 3-2.8 15 0 18"/></svg>',
        'facebook'  => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14.5 21v-8h2.7l.4-3.2h-3.1V7.9c0-.9.3-1.5 1.6-1.5h1.7V3.5c-.3 0-1.3-.2-2.4-.2-2.4 0-4 1.4-4 4.1v2.4H8.7V13h2.7v8z"/></svg>',
        'youtube'   => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="2.5" y="5.5" width="19" height="13" rx="4"/><path d="M10 9.2v5.6l5-2.8z"/></svg>',
        'tiktok'    => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 3.5v11.2a3.6 3.6 0 1 1-3.6-3.6"/><path d="M14 3.5c.3 2.4 1.9 4 4.5 4.2"/></svg>',
        'instagram' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3.5" y="3.5" width="17" height="17" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17" cy="7" r=".7" fill="currentColor"/></svg>',
        'x'         => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4.5 4h4l11 16h-4z"/><path d="M19 4l-6.2 6.8M5 20l6.2-6.8"/></svg>',
    ];

    // [label, url, optional monogram for websites]
    $official = [
        ['name' => 'Websites', 'icon' => 'globe', 'items' => [
            ['Iglesia Ni Cristo', 'https://iglesianicristo.net', 'INC'],
            ['INC Media', 'https://incmedia.org', 'IM'],
            ['Pasugo: God\'s Message', 'https://pasugo.com.ph', 'P'],
            ['INC Giving', 'https://incgiving.org/', 'IG'],
        ]],
        ['name' => 'Facebook', 'icon' => 'facebook', 'items' => [
            ['INC News and Updates', 'https://www.facebook.com/INCNewsandUpdates'],
            ['INCNU Australia-Oceania', 'https://www.facebook.com/share/1E9XSvxtS1/'],
            ['INCNU Japan', 'https://www.facebook.com/share/1CToaW2ZRQ/'],
            ['INCNU North America', 'https://www.facebook.com/share/1Avxncjh6x/'],
            ['INCNU Europe', 'https://www.facebook.com/share/1Q1xHLzmbr/'],
            ['INCNU Africa', 'https://www.facebook.com/share/1EoY2g1iE2/'],
            ['INC Media', 'https://www.facebook.com/incmedia.org'],
            ['INC Giving Project', 'https://www.facebook.com/INCGivingProject'],
            ['Christian Family Organizations', 'https://www.facebook.com/IglesiaNiCristoChristianFamilyOrganizations'],
            ['INC Production Company', 'https://www.facebook.com/iglesianicristoproductioncompany'],
            ['CFO CBI', 'https://www.facebook.com/CFO.CBI'],
            ['SCAN International', 'https://www.facebook.com/scaninternational.org'],
            ['INC Museum', 'https://www.facebook.com/incmuseum'],
            ['Light Life INC', 'https://www.facebook.com/share/165mAG7irjg/'],
            ['INC Radio', 'https://www.facebook.com/share/1WbggKCkwX/'],
            ['INC CFO Magazine', 'https://www.facebook.com/share/185x8PC8eA/'],
        ]],
        ['name' => 'YouTube', 'icon' => 'youtube', 'items' => [
            ['INC News and Updates', 'https://www.youtube.com/c/IglesiaNiCristoNewsandUpdatesChannel'],
            ['INC Media', 'https://www.youtube.com/c/IncmediaOrgOFFICIAL'],
            ['Evangelical Mission', 'https://www.youtube.com/c/IglesiaNiCristoEVangelicalMission'],
            ['Christian Family Organizations', 'https://www.youtube.com/c/IglesiaNiCristoChristianFamilyOrganizations'],
            ['INC Production Company', 'https://www.youtube.com/c/IglesiaNiCristoProductionCompany'],
            ['INC Kids', 'https://youtube.com/c/INCKids'],
            ['INC Original Music', 'https://youtube.com/c/INCOriginalMusic'],
            ['INC TV', 'https://youtube.com/c/INCTVChannel'],
            ['INC Radio DZEM 954', 'https://youtube.com/c/INCRadioDZEM954TheVoiceofTruth'],
            ['INC Museum', 'https://youtube.com/c/INCMuseum'],
            ['Light Life INC', 'https://youtube.com/@lightlife_inc'],
        ]],
        ['name' => 'TikTok', 'icon' => 'tiktok', 'items' => [
            ['@incnauofficial', 'https://www.tiktok.com/@incnauofficial'],
            ['@inctvcebsiofficial', 'https://www.tiktok.com/@inctvcebsiofficial'],
            ['@lightlife_inc', 'https://www.tiktok.com/@lightlife_inc'],
        ]],
        ['name' => 'Instagram', 'icon' => 'instagram', 'items' => [
            ['incnewsandupdates', 'https://www.instagram.com/incnewsandupdates'],
            ['inc_cfo', 'https://www.instagram.com/inc_cfo'],
            ['incmedianews', 'https://www.instagram.com/incmedianews'],
            ['incgivingproject', 'https://www.instagram.com/incgivingproject'],
            ['weare.incpc', 'https://www.instagram.com/weare.incpc'],
            ['cfo.csd', 'https://www.instagram.com/cfo.csd'],
            ['incmuseum', 'https://www.instagram.com/incmuseum'],
            ['incradioofficial', 'https://www.instagram.com/incradioofficial/'],
            ['lightlife_inc', 'https://www.instagram.com/lightlife_inc/'],
            ['cfo_mag', 'https://www.instagram.com/cfo_mag/'],
        ]],
        ['name' => 'X (Twitter)', 'icon' => 'x', 'items' => [
            ['@incnews_updates', 'https://twitter.com/incnews_updates'],
            ['@inc_media', 'https://twitter.com/inc_media'],
            ['@inc_cfo', 'https://twitter.com/inc_cfo'],
            ['@CFO_CSD', 'https://twitter.com/CFO_CSD'],
            ['@INCGivingShow', 'https://twitter.com/INCGivingShow'],
        ]],
    ];

    $destinations = [
        ['Accessible schedule of worship service', 'Not sure when or where the nearest worship service is? This directory helps you find a congregation near you, along with service times.', 'https://iglesianicristo.net/directory/', 'Find a schedule'],
        ['News about God\'s Church', 'The official home for announcements, updates and stories from across the Iglesia Ni Cristo community, kept fresh and easy to browse.', 'https://iglesianicristo.net/', 'Catch up on news'],
        ['True Christian activities', 'From heartfelt programs to community outreach, INC Media captures everyday moments of faith in action, wherever you are.', 'https://incmedia.org/', 'See what\'s happening'],
        ['Know the true Church\'s history', 'The Iglesia Ni Cristo Museum walks you through the church\'s history, a meaningful visit for anyone curious about how it all began.', 'https://incmuseum.net/', 'Explore the history'],
    ];
@endphp

@section('content')
<div class="faith-page">

    {{-- Verse of the day: picked once per page load. Without JavaScript the default verse below stays visible.
         The canvas behind the verse draws the drifting green and red dust (see the script at the bottom). --}}
    <section class="hm-hero" id="top" aria-label="Talata mula sa Biblia">
        <canvas class="hm-paint" id="hm-paint" aria-hidden="true"></canvas>
        <p class="hm-eyebrow">The Daily Faith</p>
        <div class="hm-verse" id="verse">
            <div class="hm-verse-sk" aria-hidden="true">
                <span class="sk" style="height:2.4rem;width:100%"></span>
                <span class="sk" style="height:2.4rem;width:82%"></span>
                <span class="sk" style="height:.9rem;width:9rem;margin-top:.9rem"></span>
            </div>
            <blockquote lang="tl" id="verse-quote">
                <p id="verse-text">Ang Panginoon ay aking pastor; hindi ako magkukulang.</p>
                <cite id="verse-ref">Awit 23:1 · Ang Dating Biblia</cite>
            </blockquote>
        </div>
        <div class="hm-seg" role="group" aria-label="Verse language">
            <button type="button" data-lang="tl" aria-pressed="true">Tagalog</button>
            <button type="button" data-lang="en" aria-pressed="false">English</button>
        </div>
    </section>
    <noscript><style>.hm-verse-sk { display: none !important; } .hm-verse:not([data-ready]) blockquote { display: block !important; }</style></noscript>

    {{-- Featured read --}}
    <section class="hm-sec" id="read" aria-labelledby="read-title">
        <div class="hm-feature">
            <div class="hm-feature-media" id="feature-media">
                <span class="sk hm-img-sk" aria-hidden="true"></span>
                <img src="{{ $pasugoImage }}" alt="Pasugo: God's Message" loading="lazy" decoding="async">
                <div class="hm-img-error" role="alert">
                    <p>The image couldn't load.</p>
                    <button type="button" class="hm-retry" id="img-retry">Retry</button>
                </div>
            </div>
            <div class="hm-feature-body">
                <p class="hm-kicker">Featured read</p>
                <h2 id="read-title">Read God's Message here</h2>
                <p>There's something grounding about sitting down with God's word, especially when life gets busy. Pasugo: God's Message has been sharing articles, reflections and stories of faith for years, and it's a wonderful place to start or continue your spiritual reading. Whether you have five minutes or an hour, there's always something worth discovering.</p>
                <a class="hm-btn" href="https://www.pasugo.com.ph/" target="_blank" rel="noopener">Visit Pasugo <span aria-hidden="true">↗</span><span class="sr-only">(opens in a new tab)</span></a>
            </div>
        </div>
    </section>

    {{-- Where to go next --}}
    <section class="hm-sec hm-deferred" id="explore" aria-labelledby="explore-title">
        <div class="hm-head">
            <h2 id="explore-title">Where to go next</h2>
            <p>Four places worth a visit. Each opens in a new tab.</p>
        </div>
        <div class="hm-go">
            @foreach ($destinations as $n => $d)
                <a href="{{ $d[2] }}" target="_blank" rel="noopener" aria-label="{{ $d[0] }}: {{ $d[3] }} (opens in a new tab)">
                    <span class="hm-num">{{ str_pad($n + 1, 2, '0', STR_PAD_LEFT) }}</span>
                    <h3>{{ $d[0] }}</h3>
                    <p>{{ $d[1] }}</p>
                    <span class="hm-arrow" aria-hidden="true">↗</span>
                </a>
            @endforeach
        </div>
    </section>

    {{-- Official channels --}}
    <section class="hm-sec hm-deferred" id="official" aria-labelledby="official-title">
        <div class="hm-head">
            <h2 id="official-title">Official sites &amp; social media</h2>
            <p>The official Iglesia Ni Cristo websites and pages (2026 update). Choose a platform; each link opens in a new tab.</p>
        </div>

        <div class="hm-channels">
            <div class="hm-tabs" role="tablist" aria-label="Platforms">
                @foreach ($official as $i => $g)
                    <button type="button" class="hm-tab" role="tab" id="tab-{{ $i }}" tabindex="{{ $i === 0 ? '0' : '-1' }}" aria-selected="{{ $i === 0 ? 'true' : 'false' }}" aria-controls="panel-{{ $i }}" data-panel="panel-{{ $i }}">
                        {!! $icons[$g['icon']] !!}
                        <span>{{ $g['name'] }}</span>
                    </button>
                @endforeach
            </div>

            @foreach ($official as $i => $g)
                <div class="hm-links" id="panel-{{ $i }}" role="tabpanel" aria-labelledby="tab-{{ $i }}" @if ($i !== 0) hidden @endif>
                    @foreach ($g['items'] as $item)
                        <a class="hm-link" href="{{ $item[1] }}" target="_blank" rel="noopener noreferrer">
                            <span class="hm-ico" aria-hidden="true">
                                @if (!empty($item[2])) {{ $item[2] }} @else {!! $icons[$g['icon']] !!} @endif
                            </span>
                            <span class="hm-name">{{ $item[0] }}<span class="sr-only"> (opens in a new tab)</span></span>
                            <span class="hm-out" aria-hidden="true">↗</span>
                        </a>
                    @endforeach
                </div>
            @endforeach
        </div>
    </section>

    <footer class="hm-foot">
        &copy; {{ date('Y') }} Iglesia Ni Cristo (Church of Christ)
    </footer>
</div>
@endsection

@push('styles')
{{-- Loaded as a normal stylesheet (not @import) so it doesn't block rendering in series; only the verse uses it. --}}
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;1,500&display=swap">
<style>
    /* Theme: inherits --bg / --surface / --border / --text / --text-muted / --accent from the layout,
       so it follows the navbar's light/dark switch (html[data-theme]). Black and white only;
       --mark (the layout accent) is used sparingly to emphasize. */
    .faith-page {
        --card: var(--surface); --ink: var(--text); --muted: var(--text-muted); --line: var(--border);
        --soft: #f5f5f5; --mark: var(--accent, #16a34a);
        max-width: 1500px; margin: 0 auto; padding: 0 0 1rem;
        color: var(--ink); background: none; font-variant-numeric: tabular-nums;
    }
    html[data-theme="dark"] .faith-page { --soft: #171717; }
    .faith-page h2, .faith-page h3, .faith-page p, .faith-page blockquote { margin: 0; padding: 0; }
    .faith-page a { color: inherit; text-decoration: none; }
    .sr-only { position: absolute; width: 1px; height: 1px; margin: -1px; padding: 0; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; border: 0; }

    /* Skeleton (only used while the verse is being chosen and while the image loads) */
    .sk { display: block; border-radius: 6px; background: linear-gradient(90deg, var(--soft) 25%, var(--line) 37%, var(--soft) 63%); background-size: 400% 100%; animation: hm-shimmer 1.4s ease infinite; }
    @keyframes hm-shimmer { 0% { background-position: 100% 50%; } 100% { background-position: 0 50%; } }

    /* Hero */
    .hm-hero {
        position: relative; overflow: hidden; isolation: isolate;
        display: grid; justify-items: center; text-align: center;
        padding: clamp(2.6rem, 7vw, 5rem) clamp(1.2rem, 4vw, 3rem) clamp(2rem, 5vw, 3rem);
        border: 1px solid var(--line); border-radius: 10px; background: var(--card);
    }
    /* Green / red dust canvas sits behind all hero content */
    .hm-paint {
        position: absolute; inset: 0; width: 100%; height: 100%;
        z-index: 0; pointer-events: none;
    }
    .hm-hero > :not(.hm-paint) { position: relative; z-index: 1; }

    .hm-eyebrow { display: inline-flex; align-items: center; gap: .8rem; color: var(--muted); font-size: .74rem; font-weight: 600; letter-spacing: .24em; text-transform: uppercase; }
    .hm-eyebrow::before, .hm-eyebrow::after { content: ""; width: 28px; height: 1px; background: var(--line); }
    .hm-verse { width: 100%; max-width: 920px; margin-top: 2.2rem; }
    .hm-verse-sk { display: grid; justify-items: center; gap: .6rem; min-height: 9rem; padding-top: .4rem; }
    .hm-verse[data-ready] .hm-verse-sk { display: none; }
    .hm-verse:not([data-ready]) blockquote { display: none; }
    .hm-verse p {
        margin-bottom: 1.6rem; font-family: 'Cormorant Garamond', Georgia, serif; font-style: italic; font-weight: 500;
        font-size: clamp(1.9rem, 4.6vw, 3.4rem); line-height: 1.25; letter-spacing: -.01em;
    }
    .hm-verse.long p { font-size: clamp(1.5rem, 3.2vw, 2.3rem); }
    .hm-verse cite { display: inline-block; color: var(--mark); font-size: .78rem; font-style: normal; font-weight: 700; letter-spacing: .16em; text-transform: uppercase; }

    /* Segmented control (same look as the dashboard's filters) */
    .hm-seg { display: inline-flex; gap: 3px; margin-top: 2rem; padding: 3px; border: 1px solid var(--line); border-radius: 8px; background: var(--soft); }
    .hm-seg button { min-width: 88px; padding: .42rem .9rem; border: 0; border-radius: 6px; background: transparent; color: var(--muted); font: inherit; font-size: .78rem; font-weight: 600; cursor: pointer; transition: background .18s ease, color .18s ease; }
    .hm-seg button:hover { color: var(--ink); background: var(--card); }
    .hm-seg button[aria-pressed="true"] { background: var(--ink); color: var(--card); }

    /* Buttons: primary is solid, ghost is outlined */
    .faith-page .hm-btn { display: inline-flex; align-items: center; gap: .6rem; height: 40px; padding: 0 1.1rem; border: 1px solid var(--ink); border-radius: 6px; background: var(--ink); color: var(--card); font-size: .85rem; font-weight: 600; transition: opacity .15s ease, background .15s ease, color .15s ease; }
    .faith-page .hm-btn:hover { opacity: .85; }
    .faith-page .hm-btn-ghost { background: transparent; color: var(--ink); border-color: var(--line); }
    .faith-page .hm-btn-ghost:hover { opacity: 1; border-color: var(--ink); background: var(--soft); }

    /* Sections. Below-the-fold sections skip rendering work until they are near the viewport. */
    .hm-sec { margin-top: clamp(2.2rem, 5vw, 3.2rem); }
    .hm-deferred { content-visibility: auto; contain-intrinsic-size: auto 520px; }
    .hm-head { display: flex; justify-content: space-between; align-items: flex-end; gap: .6rem 2rem; flex-wrap: wrap; margin-bottom: 1.1rem; }
    .hm-head h2 { font-size: 1.35rem; font-weight: 700; letter-spacing: -.02em; }
    .hm-head p { max-width: 52ch; color: var(--muted); font-size: .88rem; line-height: 1.6; }

    /* Featured read */
    .hm-feature { display: grid; grid-template-columns: minmax(0, 5fr) minmax(0, 6fr); overflow: hidden; border: 1px solid var(--line); border-radius: 10px; background: var(--card); }
    .hm-feature-media { position: relative; display: grid; place-items: center; min-height: 320px; padding: clamp(1.4rem, 3vw, 2.4rem); background: #fff; border-right: 1px solid var(--line); }
    .hm-feature-media img { display: block; width: 100%; max-width: 420px; max-height: 340px; object-fit: contain; filter: grayscale(1) contrast(1.05); }
    /* The image's own background is near-white (~#f8f8f8). This dimming turns it into ~#eaeaea in dark mode, so the container uses #eaeaea there. */
    html[data-theme="dark"] .hm-feature-media { background: #eaeaea; }
    html[data-theme="dark"] .hm-feature-media img { filter: grayscale(1) contrast(1.05) brightness(.92); }
    .hm-img-sk, .hm-img-error { display: none; }
    .hm-feature-media.is-loading .hm-img-sk { display: block; position: absolute; inset: clamp(1.4rem, 3vw, 2.4rem); background-color: #e0e0e0; }
    .hm-feature-media.is-loading img { opacity: 0; }
    .hm-feature-media.is-error img { display: none; }
    .hm-feature-media.is-error .hm-img-error { display: grid; justify-items: center; gap: .6rem; color: #525252; font-size: .85rem; text-align: center; }
    .hm-retry { padding: .35rem .8rem; border: 1px solid #171717; border-radius: 6px; background: transparent; color: #171717; font: inherit; font-size: .8rem; font-weight: 600; cursor: pointer; }
    .hm-retry:hover { background: #171717; color: #fff; }
    .hm-feature-body { display: grid; align-content: center; justify-items: start; gap: 1rem; padding: clamp(1.6rem, 4vw, 3rem); }
    .hm-kicker { color: var(--muted); font-size: .72rem; font-weight: 700; letter-spacing: .2em; text-transform: uppercase; }
    .hm-feature-body h2 { font-size: clamp(1.6rem, 3vw, 2.2rem); font-weight: 700; letter-spacing: -.03em; line-height: 1.15; }
    .hm-feature-body p:not(.hm-kicker) { max-width: 54ch; margin-bottom: .6rem; color: var(--muted); font-size: .95rem; line-height: 1.85; }

    /* Where to go next: clean editorial rows */
    .hm-go { overflow: hidden; border: 1px solid var(--line); border-radius: 10px; background: var(--card); }
    .hm-go a { display: grid; grid-template-columns: 2.6rem minmax(0, 1.1fr) minmax(0, 1.5fr) auto; align-items: center; gap: 1.5rem; padding: 1.4rem 1.6rem; border-bottom: 1px solid var(--line); transition: background .15s ease; }
    .hm-go a:last-child { border-bottom: 0; }
    .hm-go a:hover, .hm-go a:focus-visible { background: var(--soft); }
    .hm-num { color: var(--muted); font-size: .8rem; font-weight: 700; transition: color .15s ease; }
    .hm-go a:hover .hm-num { color: var(--mark); }
    .hm-go h3 { font-size: 1.05rem; font-weight: 650; letter-spacing: -.01em; line-height: 1.3; }
    .hm-go p { color: var(--muted); font-size: .88rem; line-height: 1.65; }
    .hm-arrow { display: grid; place-items: center; width: 36px; height: 36px; border: 1px solid var(--line); border-radius: 50%; font-size: .9rem; transition: background .15s ease, color .15s ease, border-color .15s ease; }
    .hm-go a:hover .hm-arrow, .hm-go a:focus-visible .hm-arrow { border-color: var(--ink); background: var(--ink); color: var(--card); }

    /* Official channels */
    .hm-channels { padding: 1.2rem; border: 1px solid var(--line); border-radius: 10px; background: var(--card); }
    .hm-tabs { display: inline-flex; flex-wrap: wrap; gap: 3px; max-width: 100%; margin-bottom: 1.1rem; padding: 3px; border: 1px solid var(--line); border-radius: 8px; background: var(--soft); }
    .hm-tab { display: inline-flex; align-items: center; gap: .45rem; padding: .45rem .8rem; border: 0; border-radius: 6px; background: transparent; color: var(--muted); font: inherit; font-size: .8rem; font-weight: 600; cursor: pointer; transition: background .18s ease, color .18s ease, box-shadow .18s ease; }
    .hm-tab svg, .hm-ico svg { fill: none; stroke: currentColor; stroke-width: 1.5; stroke-linecap: round; stroke-linejoin: round; }
    .hm-tab svg { width: 16px; height: 16px; }
    .hm-tab:hover { color: var(--ink); background: var(--card); }
    .hm-tab[aria-selected="true"] { background: var(--card); color: var(--ink); box-shadow: 0 0 0 1px var(--line); }

    .hm-links { grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)); gap: .6rem; }
    .hm-links:not([hidden]) { display: grid; }
    .hm-link { display: flex; align-items: center; gap: .8rem; min-width: 0; padding: .75rem .85rem; border: 1px solid var(--line); border-radius: 8px; background: transparent; color: var(--ink); transition: background .15s ease, color .15s ease, border-color .15s ease; }
    .hm-link:hover, .hm-link:focus-visible { border-color: var(--ink); background: var(--ink); color: var(--card); }
    .hm-ico { flex: none; display: grid; place-items: center; width: 36px; height: 36px; border: 1px solid var(--line); border-radius: 6px; font-size: .7rem; font-weight: 700; letter-spacing: .02em; transition: border-color .15s ease; }
    .hm-ico svg { width: 18px; height: 18px; }
    .hm-link:hover .hm-ico, .hm-link:focus-visible .hm-ico { border-color: currentColor; }
    .hm-name { flex: 1; min-width: 0; font-size: .86rem; font-weight: 600; line-height: 1.35; overflow-wrap: anywhere; }
    .hm-out { flex: none; color: var(--muted); font-size: .8rem; transition: color .15s ease; }
    .hm-link:hover .hm-out, .hm-link:focus-visible .hm-out { color: inherit; }

    .hm-foot { margin-top: clamp(2.2rem, 5vw, 3.2rem); padding: 1.4rem 0 .4rem; border-top: 1px solid var(--line); color: var(--muted); font-size: .8rem; text-align: center; }

    .faith-page a:focus-visible, .faith-page button:focus-visible { outline: 2px solid var(--mark); outline-offset: 2px; }
    @media (prefers-reduced-motion: reduce) { .faith-page *, .faith-page *::before { transition: none !important; animation: none !important; } }

    @media (max-width: 860px) {
        .hm-feature { grid-template-columns: 1fr; }
        .hm-feature-media { min-height: 240px; border-right: 0; border-bottom: 1px solid var(--line); }
        .hm-go a { grid-template-columns: 2rem minmax(0, 1fr) auto; gap: .35rem 1rem; padding: 1.2rem 1.1rem; }
        .hm-go p { grid-column: 2; grid-row: 2; }
        .hm-arrow { grid-column: 3; grid-row: 1 / span 2; }
    }
    @media (max-width: 560px) {
        .hm-channels { padding: .9rem; }
        .hm-tabs { display: flex; }
        .hm-tab { flex: 1 1 auto; justify-content: center; }
        .hm-links { grid-template-columns: 1fr; }
    }
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const page = document.querySelector('.faith-page');

    // [Tagalog (Ang Dating Biblia), English (King James Version), reference key]
    const VERSES = [
        ['Ang Panginoon ay aking pastor; hindi ako magkukulang.', 'The LORD is my shepherd; I shall not want.', ['Awit 23:1', 'Psalm 23:1']],
        ["Sapagka't nalalaman ko ang mga pagiisip na iniisip ko tungkol sa inyo, sabi ng Panginoon, mga pagiisip ng kapayapaan, at hindi ng kasamaan, upang bigyan kayo ng pagasa sa inyong huling wakas.", 'For I know the thoughts that I think toward you, saith the LORD, thoughts of peace, and not of evil, to give you an expected end.', ['Jeremias 29:11', 'Jeremiah 29:11']],
        ["Huwag kang matakot; sapagka't ako'y sumasaiyo: huwag kang manglupaypay; sapagka't ako ang iyong Dios: aking palalakasin ka; oo, aking tutulungan ka.", 'Fear thou not; for I am with thee: be not dismayed; for I am thy God: I will strengthen thee; yea, I will help thee.', ['Isaias 41:10', 'Isaiah 41:10']],
        ['Magagawa ko ang lahat ng mga bagay sa kaniya na nagpapalakas sa akin.', 'I can do all things through Christ which strengtheneth me.', ['Filipos 4:13', 'Philippians 4:13']],
        ["Magsiparito sa akin, ang lahat ng nangapapagal at nangabibigatang lubha, at kayo'y aking pagpapahingahin.", 'Come unto me, all ye that labour and are heavy laden, and I will give you rest.', ['Mateo 11:28', 'Matthew 11:28']],
        ['Ang Dios ay aming kanlungan at kalakasan, isang katulong na laging masusumpungan sa kabagabagan.', 'God is our refuge and strength, a very present help in trouble.', ['Awit 46:1', 'Psalm 46:1']],
        ["Ang salita mo'y ilawan sa aking mga paa, at liwanag sa aking landas.", 'Thy word is a lamp unto my feet, and a light unto my path.', ['Awit 119:105', 'Psalm 119:105']],
        ["Nguni't silang naghihintay sa Panginoon ay magbabagong lakas; sila'y sasampa na may mga pakpak na gaya ng mga agila; sila'y magsisitakbo, at hindi manganganyayat.", 'But they that wait upon the LORD shall renew their strength; they shall mount up with wings as eagles; they shall run, and not be weary; and they shall walk, and not faint.', ['Isaias 40:31', 'Isaiah 40:31']],
        ['Ang Panginoon ay aking liwanag at aking kaligtasan; kanino ako matatakot? Ang Panginoon ay katibayan ng aking buhay; kanino ako mangingilag?', 'The LORD is my light and my salvation; whom shall I fear? the LORD is the strength of my life; of whom shall I be afraid?', ['Awit 27:1', 'Psalm 27:1']],
        ['Magalak ka naman sa Panginoon; at ibibigay niya sa iyo ang nasa ng iyong puso.', 'Delight thyself also in the LORD; and he shall give thee the desires of thine heart.', ['Awit 37:4', 'Psalm 37:4']],
        ['Ipagkatiwala mo ang iyong pasanin sa Panginoon, at aalalayan ka niya.', 'Cast thy burden upon the LORD, and he shall sustain thee.', ['Awit 55:22', 'Psalm 55:22']],
        ['Siyang tumatahan sa lihim na dako ng Kataastaasan ay tatahan sa lilim ng Makapangyarihan sa lahat.', 'He that dwelleth in the secret place of the most High shall abide under the shadow of the Almighty.', ['Awit 91:1', 'Psalm 91:1']],
        ["Ito ang araw na ginawa ng Panginoon; tayo'y matutuwa at magagalak diyan.", 'This is the day which the LORD hath made; we will rejoice and be glad in it.', ['Awit 118:24', 'Psalm 118:24']],
        ['Kaniyang pinagagaling ang mga bagbag na puso, at tinatalian ang kanilang mga sugat.', 'He healeth the broken in heart, and bindeth up their wounds.', ['Awit 147:3', 'Psalm 147:3']],
        ["Ang pagtangis ay magtatagal nang magdamag, nguni't ang kagalakan ay dumarating sa kinaumagahan.", 'Weeping may endure for a night, but joy cometh in the morning.', ['Awit 30:5', 'Psalm 30:5']],
        ['Magtiwala ka sa Panginoon ng buong puso mo, at huwag kang manalig sa iyong sariling kaunawa.', 'Trust in the LORD with all thine heart; and lean not unto thine own understanding.', ['Kawikaan 3:5', 'Proverbs 3:5']],
        ['Ipagkatiwala mo sa Panginoon ang iyong mga gawa, at ang iyong mga pagiisip ay matatatag.', 'Commit thy works unto the LORD, and thy thoughts shall be established.', ['Kawikaan 16:3', 'Proverbs 16:3']],
        ["Ang pangalan ng Panginoon ay matibay na moog: ang matuwid ay tumatakbo roon, at ligtas.", 'The name of the LORD is a strong tower: the righteous runneth into it, and is safe.', ['Kawikaan 18:10', 'Proverbs 18:10']],
        ["Iyong iingatan siya sa sakdal na kapayapaan, na ang pagiisip ay nananatili sa iyo: sapagka't siya'y tumitiwala sa iyo.", 'Thou wilt keep him in perfect peace, whose mind is stayed on thee: because he trusteth in thee.', ['Isaias 26:3', 'Isaiah 26:3']],
        ["Pagka ikaw ay dumaraan sa mga tubig, ako'y sasaiyo; at sa mga ilog, hindi ka nila aapawan.", 'When thou passest through the waters, I will be with thee; and through the rivers, they shall not overflow thee.', ['Isaias 43:2', 'Isaiah 43:2']],
        ["Datapuwa't hanapin ninyo muna ang kaniyang kaharian, at ang kaniyang katuwiran; at ang lahat ng mga bagay na ito'y idaragdag sa inyo.", 'But seek ye first the kingdom of God, and his righteousness; and all these things shall be added unto you.', ['Mateo 6:33', 'Matthew 6:33']],
        ["Magsihingi kayo, at kayo'y bibigyan; magsihanap kayo, at kayo'y makasusumpong; magsituktok kayo, at kayo'y pagbubuksan.", 'Ask, and it shall be given you; seek, and ye shall find; knock, and it shall be opened unto you.', ['Mateo 7:7', 'Matthew 7:7']],
        ['Kapayapaan ang iniiwan ko sa inyo, aking kapayapaan ang ibinibigay ko sa inyo. Huwag mabagabag ang inyong puso, ni mangilabot man.', 'Peace I leave with you, my peace I give unto you: not as the world giveth, give I unto you. Let not your heart be troubled, neither let it be afraid.', ['Juan 14:27', 'John 14:27']],
        ["Ngayon ang Dios ng pagasa ay puspusin nawa kayo ng buong kagalakan at kapayapaan sa pananampalataya, upang kayo'y managana sa pagasa.", 'Now the God of hope fill you with all joy and peace in believing, that ye may abound in hope.', ['Roma 15:13', 'Romans 15:13']],
        ["Sapagka't nagsisilakad kami sa pamamagitan ng pananampalataya, hindi sa pamamagitan ng nakikita.", 'For we walk by faith, not by sight.', ['2 Corinto 5:7', '2 Corinthians 5:7']],
        ["At huwag tayong mangapagod sa paggawa ng mabuti: sapagka't sa kapanahunan ay mangagsisiani tayo, kung tayo'y hindi manghihimagod.", 'And let us not be weary in well doing: for in due season we shall reap, if we faint not.', ['Galacia 6:9', 'Galatians 6:9']],
        ['Ang pananampalataya nga ay siyang katunayan ng mga bagay na inaasahan, ang katibayan ng mga bagay na hindi nakikita.', 'Now faith is the substance of things hoped for, the evidence of things not seen.', ['Hebreo 11:1', 'Hebrews 11:1']],
        ["Sa bawa't bagay ay may kapanahunan, at panahon sa bawa't panukala sa silong ng langit.", 'To every thing there is a season, and a time to every purpose under the heaven.', ['Eclesiastes 3:1', 'Ecclesiastes 3:1']],
        ["Bago sa bawa't umaga: dakila ang iyong pagtatapat.", 'They are new every morning: great is thy faithfulness.', ['Panaghoy 3:23', 'Lamentations 3:23']],
        ["Kayo'y magpakalakas at magpakatapang, huwag kayong matakot; hindi ka niya iiwan, ni pababayaan ka man.", 'Be strong and of a good courage, fear not, nor be afraid of them: for the LORD thy God, he it is that doth go with thee; he will not fail thee, nor forsake thee.', ['Deuteronomio 31:6', 'Deuteronomy 31:6']],
    ];
    const VERSIONS = { tl: 'Ang Dating Biblia', en: 'King James Version' };
    const LANGS = ['tl', 'en'];
    const box = document.getElementById('verse');
    const quote = document.getElementById('verse-quote');
    const vText = document.getElementById('verse-text');
    const vRef = document.getElementById('verse-ref');
    const langButtons = [...page.querySelectorAll('.hm-seg button')];

    let lang = 'tl';
    try { const saved = localStorage.getItem('faith-verse-lang'); if (LANGS.includes(saved)) lang = saved; } catch (e) {}

    const paint = i => {
        const li = LANGS.indexOf(lang);
        const text = VERSES[i][li];
        vText.textContent = text;
        vRef.textContent = VERSES[i][2][li] + ' · ' + VERSIONS[lang];
        quote.lang = lang;
        box.classList.toggle('long', text.length > 110);
        langButtons.forEach(b => b.setAttribute('aria-pressed', b.dataset.lang === lang ? 'true' : 'false'));
    };

    // A new verse is chosen only when the page loads (never the same one twice in a row).
    let last = -1;
    try { const stored = localStorage.getItem('faith-verse'); last = stored === null ? -1 : Number(stored); } catch (e) {}
    let current;
    do { current = Math.floor(Math.random() * VERSES.length); } while (current === last && VERSES.length > 1);
    try { localStorage.setItem('faith-verse', String(current)); } catch (e) {}
    paint(current);
    box.dataset.ready = '';   // reveal the verse; the skeleton hides (prevents a flash of the default verse)

    // Same verse, switch language in place; announce the change to screen readers only after a user action
    langButtons.forEach(b => b.addEventListener('click', () => {
        if (b.dataset.lang === lang) return;
        lang = b.dataset.lang;
        try { localStorage.setItem('faith-verse-lang', lang); } catch (e) {}
        quote.setAttribute('aria-live', 'polite');
        paint(current);
    }));

    /* ---------- Green and red dust over the Bible quote ----------
       Specks drift across the card and off its edges (new ones drift in), and now and then a soft
       green or red flare sweeps in and fades out, like a camera catching coloured flashlights.
       Runs on its own (no hover needed); pauses off-screen / in a hidden tab; off for reduced motion. */
    (() => {
        const hero = document.getElementById('top');
        const canvas = document.getElementById('hm-paint');
        if (!hero || !canvas) return;
        if (matchMedia('(prefers-reduced-motion: reduce)').matches) {
            canvas.remove();
            return;
        }

        const ctx = canvas.getContext('2d');
        const COLORS = ['22,163,74', '220,38,38']; // green, red
        const rand = (a, b) => a + Math.random() * (b - a);
        const isDark = () => document.documentElement.dataset.theme === 'dark';
        let w = 0, h = 0, dust = [], flares = [], nextFlare = 0, running = false, visible = true, last = 0;

        // A speck enters from a random edge (or anywhere, on first fill) and drifts slowly across.
        const speck = scatter => {
            const p = { r: rand(.7, 2.4), c: COLORS[Math.random() < .5 ? 0 : 1], a: rand(.35, .9), tw: rand(0, Math.PI * 2), ts: rand(.6, 1.8) };
            const angle = rand(0, Math.PI * 2), speed = rand(6, 22); // px per second
            p.vx = Math.cos(angle) * speed; p.vy = Math.sin(angle) * speed - 4; // slight upward lift
            if (scatter) { p.x = rand(0, w); p.y = rand(0, h); }
            else {
                // start just outside the edge it is moving away from
                p.x = p.vx > 0 ? -6 : w + 6; p.y = rand(0, h);
                if (Math.abs(p.vy) > Math.abs(p.vx)) { p.x = rand(0, w); p.y = p.vy > 0 ? -6 : h + 6; }
            }
            return p;
        };
        const flare = now => {
            const fromLeft = Math.random() < .5;
            return {
                c: COLORS[Math.random() < .5 ? 0 : 1], born: now, life: rand(1800, 2800),
                x: fromLeft ? rand(-.15, .25) * w : rand(.75, 1.15) * w, y: rand(.1, .9) * h,
                vx: (fromLeft ? 1 : -1) * rand(.04, .09) * w / 1000, radius: rand(.35, .6) * Math.max(w, h),
            };
        };

        const resize = () => {
            const dpr = Math.min(window.devicePixelRatio || 1, 2);
            w = hero.clientWidth; h = hero.clientHeight;
            canvas.width = Math.max(1, w * dpr); canvas.height = Math.max(1, h * dpr);
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
            const count = Math.min(90, Math.round(w * h / 9000));
            dust = Array.from({ length: count }, () => speck(true));
        };

        const frame = now => {
            if (!running) return;
            const dt = Math.min(64, now - (last || now)) / 1000;
            last = now;
            const dark = isDark();
            ctx.clearRect(0, 0, w, h);
            ctx.globalCompositeOperation = dark ? 'lighter' : 'source-over';

            // Flares: quick rise, slow fade, gentle sideways sweep
            if (now >= nextFlare) { flares.push(flare(now)); nextFlare = now + rand(1600, 3600); }
            flares = flares.filter(f => {
                const t = (now - f.born) / f.life;
                if (t >= 1) return false;
                f.x += f.vx * dt * 1000;
                const env = t < .25 ? t / .25 : 1 - (t - .25) / .75;
                const g = ctx.createRadialGradient(f.x, f.y, 0, f.x, f.y, f.radius);
                g.addColorStop(0, `rgba(${f.c},${(dark ? .36 : .16) * env})`);
                g.addColorStop(1, `rgba(${f.c},0)`);
                ctx.fillStyle = g;
                ctx.fillRect(0, 0, w, h);
                return true;
            });

            // Dust: drift, twinkle, and replace any speck that has left the card
            for (let i = 0; i < dust.length; i++) {
                const p = dust[i];
                p.x += p.vx * dt; p.y += p.vy * dt; p.tw += p.ts * dt;
                if (p.x < -10 || p.x > w + 10 || p.y < -10 || p.y > h + 10) { dust[i] = speck(false); continue; }
                const alpha = p.a * (.55 + .45 * Math.sin(p.tw)) * (dark ? 1 : .8);
                ctx.fillStyle = `rgba(${p.c},${alpha})`;
                ctx.beginPath(); ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2); ctx.fill();
                if (p.r > 1.8) { // the bigger specks get a faint halo
                    ctx.fillStyle = `rgba(${p.c},${alpha * .18})`;
                    ctx.beginPath(); ctx.arc(p.x, p.y, p.r * 3.2, 0, Math.PI * 2); ctx.fill();
                }
            }
            ctx.globalCompositeOperation = 'source-over';
            requestAnimationFrame(frame);
        };
        const sync = () => {
            const shouldRun = visible && !document.hidden;
            if (shouldRun && !running) { running = true; last = 0; requestAnimationFrame(frame); }
            else if (!shouldRun) running = false;
        };

        resize();
        if ('ResizeObserver' in window) new ResizeObserver(resize).observe(hero);
        else window.addEventListener('resize', resize);
        if ('IntersectionObserver' in window) {
            new IntersectionObserver(([entry]) => { visible = entry.isIntersecting; sync(); }).observe(hero);
        }
        document.addEventListener('visibilitychange', sync);
        sync();
    })();

    // Featured image: skeleton while loading, an error state with retry if it fails
    const media = document.getElementById('feature-media');
    const img = media.querySelector('img');
    if (!img.complete) media.classList.add('is-loading');
    else if (img.naturalWidth === 0) media.classList.add('is-error');
    img.addEventListener('load', () => media.classList.remove('is-loading', 'is-error'));
    img.addEventListener('error', () => { media.classList.remove('is-loading'); media.classList.add('is-error'); });
    document.getElementById('img-retry').addEventListener('click', () => {
        media.classList.remove('is-error');
        media.classList.add('is-loading');
        const url = new URL(img.src, window.location.href);
        url.searchParams.set('retry', String(Date.now()));
        img.src = url.toString();
    });

    // Platform tabs: arrow keys / Home / End, roving tabindex, remembers the last platform
    const tabs = [...page.querySelectorAll('.hm-tab')];
    const panels = [...page.querySelectorAll('.hm-links')];
    const selectTab = (tab, focus) => {
        tabs.forEach(x => { const on = x === tab; x.setAttribute('aria-selected', String(on)); x.tabIndex = on ? 0 : -1; });
        panels.forEach(p => { p.hidden = p.id !== tab.dataset.panel; });
        if (focus) tab.focus();
        try { localStorage.setItem('faith-platform', tab.dataset.panel); } catch (e) {}
    };
    tabs.forEach((t, i) => {
        t.addEventListener('click', () => selectTab(t, false));
        t.addEventListener('keydown', e => {
            const keys = { ArrowRight: (i + 1) % tabs.length, ArrowLeft: (i - 1 + tabs.length) % tabs.length, Home: 0, End: tabs.length - 1 };
            if (!(e.key in keys)) return;
            e.preventDefault();
            selectTab(tabs[keys[e.key]], true);
        });
    });
    try {
        const savedPanel = localStorage.getItem('faith-platform');
        const savedTab = tabs.find(t => t.dataset.panel === savedPanel);
        if (savedTab) selectTab(savedTab, false);
    } catch (e) {}
});
</script>
@endpush