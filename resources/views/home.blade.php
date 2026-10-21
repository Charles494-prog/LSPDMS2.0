@extends('layouts.app')

@section('title', 'Home')

@section('content')
<style>
    @import url('https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;1,400;1,500&family=Jost:wght@300;400;500&display=swap');

    .faith-page {
        --navy: #0b1630; --navy-2: #121f40; --ivory: #f6f2e9; --ink: #141b2d;
        --gold: #b9964f; --gold-soft: #e3cf9c; --muted: #6b7384; --line: rgba(185, 150, 79, .35);
        --ease: cubic-bezier(.2, .8, .2, 1);
        margin-top: 1.2rem; overflow: hidden; background: var(--ivory); color: var(--ink);
        font-family: 'Jost', -apple-system, 'Helvetica Neue', Arial, sans-serif; font-weight: 300;
    }
    .faith-page a { text-decoration: none; color: inherit; }
    .faith-page .serif { font-family: 'Cormorant Garamond', Georgia, serif; }
    .wrap { max-width: 1100px; margin: 0 auto; padding: 0 clamp(1.4rem, 5vw, 3rem); }

    /* Buttons: sharp rectangles */
    .btn {
        display: inline-block; padding: 1.05rem 2.2rem; border: 1px solid var(--gold); border-radius: 0; cursor: pointer;
        font: 400 .74rem/1 'Jost', sans-serif; letter-spacing: .2em; text-transform: uppercase; background: transparent;
        transition: background .35s var(--ease), color .35s var(--ease);
    }
    .btn-gold { background: var(--gold); color: var(--navy); }
    .btn-gold:hover { background: var(--gold-soft); border-color: var(--gold-soft); }
    .btn-light { color: var(--gold-soft); }
    .btn-light:hover { background: var(--gold); color: var(--navy); }
    .btn-dark { color: var(--navy); }
    .btn-dark:hover { background: var(--navy); color: var(--gold-soft); border-color: var(--navy); }

    /* Hero: the verse is the centerpiece */
    .hero {
        position: relative; background: var(--navy); color: #fff; text-align: center;
        padding: 2.4rem 1.4rem 6rem; min-height: 600px; display: flex; flex-direction: column; align-items: center;
    }
    .brand { font-size: 1.5rem; font-weight: 500; letter-spacing: .12em; color: var(--gold-soft); }
    .brand-logo { display: block; width: min(190px, 45vw); max-height: 90px; margin: 0 auto 1rem; object-fit: contain; }
    .brand::after { content: ""; display: block; width: 40px; height: 1px; margin: 1rem auto 0; background: var(--gold); }
    .verse { flex: 1; display: flex; flex-direction: column; justify-content: center; max-width: 920px; padding-top: 3.5rem; transition: opacity .4s ease; }
    .verse.out { opacity: 0; }
    .verse blockquote { margin: 0; }
    .verse p {
        margin: 0 0 1.8rem; font-family: 'Cormorant Garamond', Georgia, serif; font-style: italic; font-weight: 500;
        font-size: clamp(2rem, 5.4vw, 3.9rem); line-height: 1.28; color: #fff;
    }
    .verse.long p { font-size: clamp(1.6rem, 3.8vw, 2.7rem); }
    .verse cite { font-style: normal; font-size: .85rem; letter-spacing: .22em; color: var(--gold-soft); }
    .hero .btn { margin-top: 3rem; }
    .lang-toggle { display: inline-flex; margin-top: 1.6rem; border: 1px solid rgba(227, 207, 156, .4); }
    .lang-toggle button { padding: .55rem 1.1rem; border: 0; background: transparent; color: #aab4c8; cursor: pointer; font: 400 .72rem/1 'Jost', sans-serif; letter-spacing: .18em; text-transform: uppercase; transition: background .3s var(--ease), color .3s var(--ease); }
    .lang-toggle button[aria-pressed="true"] { background: var(--gold); color: var(--navy); }
    .lang-toggle button:focus-visible { outline: 2px solid var(--gold); outline-offset: 2px; }

    /* Sections */
    .sec { padding: 6.5rem 0 0; }
    .sec-head { text-align: center; margin-bottom: 3.5rem; }
    .sec-head h2 { margin: 0; font-family: 'Cormorant Garamond', Georgia, serif; font-weight: 500; font-size: clamp(2rem, 4.4vw, 3rem); line-height: 1.15; }
    .sec-head p { margin: .9rem auto 0; max-width: 46ch; color: var(--muted); line-height: 1.8; }
    .sec-head::after { content: ""; display: block; width: 40px; height: 1px; margin: 1.6rem auto 0; background: var(--gold); }

    /* Featured read: arch cover + text, no card */
    .feature { display: grid; gap: 3rem; align-items: center; justify-items: center; }
    @media (min-width: 820px) { .feature { grid-template-columns: 5fr 6fr; gap: 5rem; justify-items: stretch; } }
    .arch {
        width: min(100%, 340px); aspect-ratio: 3 / 4; justify-self: center; border-radius: 999px 999px 0 0;
        display: grid; place-content: center; text-align: center; color: #fff; position: relative;
        background: var(--navy);
        box-shadow: 0 0 0 8px var(--ivory), 0 0 0 9px var(--gold);
    }
    .arch h3 { margin: 0; font-family: 'Cormorant Garamond', Georgia, serif; font-weight: 500; font-size: 3rem; letter-spacing: .06em; }
    .arch span { display: block; margin-top: .5rem; color: var(--gold-soft); font-size: .85rem; letter-spacing: .24em; }
    .feature-body h3 { margin: 0 0 1.2rem; font-family: 'Cormorant Garamond', Georgia, serif; font-weight: 500; font-size: clamp(1.8rem, 3.4vw, 2.5rem); line-height: 1.2; }
    .feature-body p { margin: 0 0 2.2rem; max-width: 52ch; color: var(--muted); line-height: 1.9; }

    /* Where to go next: open editorial rows, no boxes */
    .go { border-top: 1px solid var(--line); }
    .go a {
        display: grid; grid-template-columns: 1fr; gap: 1.2rem; align-items: center; padding: 2.6rem 1rem;
        border-bottom: 1px solid var(--line); position: relative; isolation: isolate; transition: color .5s var(--ease);
    }
    @media (min-width: 860px) { .go a { grid-template-columns: 1.1fr 1.3fr auto; gap: 3rem; padding: 2.8rem 2rem; } }
    .go a::before { content: ""; position: absolute; inset: 0; z-index: -1; background: var(--navy); transform: scaleY(0); transform-origin: bottom; transition: transform .55s var(--ease); }
    .go a:hover::before, .go a:focus-visible::before { transform: none; }
    .go a:hover, .go a:focus-visible { color: #fff; }
    .go h3 { margin: 0; font-family: 'Cormorant Garamond', Georgia, serif; font-weight: 500; font-size: clamp(1.7rem, 3vw, 2.2rem); line-height: 1.15; }
    .go p { margin: 0; color: var(--muted); line-height: 1.8; font-size: .98rem; transition: color .5s var(--ease); }
    .go a:hover p, .go a:focus-visible p { color: #c3cadb; }
    .go a:hover .btn-dark, .go a:focus-visible .btn-dark { background: var(--gold); border-color: var(--gold); color: var(--navy); }

    /* Official channels: round emblems on navy */
    .official { margin-top: 7rem; padding: 6.5rem 0 5.5rem; background: var(--navy); color: #fff; }
    .official .sec-head p { color: #aab4c8; }
    .official .sec-head h2 { color: #fff; }
    .tabs { display: flex; justify-content: center; flex-wrap: wrap; gap: 1.2rem 2.2rem; margin-bottom: 4rem; }
    .tab { background: none; border: 0; cursor: pointer; display: grid; justify-items: center; gap: .7rem; color: #aab4c8; font: 400 .78rem 'Jost', sans-serif; letter-spacing: .14em; transition: color .3s; }
    .tab .orb { display: grid; place-items: center; width: 64px; height: 64px; border-radius: 50%; border: 1px solid rgba(227, 207, 156, .4); color: var(--gold-soft); transition: background .35s var(--ease), color .35s var(--ease), border-color .35s; }
    .tab svg, .orb svg { fill: none; stroke: currentColor; stroke-width: 1.3; stroke-linecap: round; stroke-linejoin: round; width: 26px; height: 26px; }
    .tab:hover .orb { border-color: var(--gold); }
    .tab.on { color: #fff; }
    .tab.on .orb { background: var(--gold); border-color: var(--gold); color: var(--navy); }
    .tab:focus-visible { outline: 2px solid var(--gold); outline-offset: 6px; }

    .panel { display: none; justify-content: center; flex-wrap: wrap; gap: 2.6rem 1.2rem; }
    .panel.on { display: flex; animation: panelIn .6s var(--ease); }
    @keyframes panelIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: none; } }
    .emblem { width: 140px; display: grid; justify-items: center; gap: 1rem; text-align: center; }
    .emblem .orb {
        display: grid; place-items: center; width: 84px; height: 84px; border-radius: 50%;
        border: 1px solid var(--gold); color: var(--gold-soft); background: rgba(255, 255, 255, .03);
        font-family: 'Cormorant Garamond', Georgia, serif; font-size: 1.4rem; font-weight: 600; letter-spacing: .04em;
        box-shadow: 0 0 0 5px rgba(11, 22, 48, 1), 0 0 0 6px rgba(185, 150, 79, .3);
        transition: background .4s var(--ease), color .4s var(--ease), transform .5s var(--ease);
    }
    .emblem .orb svg { width: 30px; height: 30px; }
    .emblem .name { font-size: .88rem; line-height: 1.4; color: #dbe1ee; overflow-wrap: anywhere; }
    .emblem:hover .orb, .emblem:focus-visible .orb { background: var(--gold); color: var(--navy); transform: translateY(-6px); }
    .emblem:focus-visible { outline: 2px solid var(--gold); outline-offset: 6px; }

    .foot { padding: 2.4rem 1rem; background: var(--deep, #070d1d); background: #070d1d; color: #8f9ab0; text-align: center; font-size: .85rem; }

    .faith-page a:focus-visible, .faith-page button:focus-visible { outline: 2px solid var(--gold); outline-offset: 3px; }
    @media (prefers-reduced-motion: reduce) { .faith-page *, .faith-page *::before { animation: none !important; transition: none !important; } }
</style>

@php
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
@endphp

<div class="faith-page">

    {{-- Hero: the verse takes centre stage --}}
    <section class="hero" id="top" aria-label="Talata mula sa Biblia">
        <div class="brand serif">
            <img class="brand-logo" src="{{ asset('images/LSP_logo_white.png') }}" alt="LSPDMS — LSP's Data Management System">
            The Daily Faith
        </div>
        <div class="verse" id="verse">
            <blockquote lang="tl" id="verse-quote">
                <p id="verse-text">Ang Panginoon ay aking pastor; hindi ako magkukulang.</p>
                <cite id="verse-ref">Awit 23:1 · Ang Dating Biblia</cite>
            </blockquote>
        </div>
        <button type="button" class="btn btn-light" id="verse-next">Another verse</button>
        <div class="lang-toggle" role="group" aria-label="Verse language">
            <button type="button" data-lang="tl" aria-pressed="true">Tagalog</button>
            <button type="button" data-lang="en" aria-pressed="false">English</button>
        </div>
    </section>

    {{-- Featured read --}}
    <section class="sec" id="read">
        <div class="wrap">
            <div class="feature">
                <div class="arch" aria-hidden="true">
                    <div><h3>Pasugo</h3><span>God's Message</span></div>
                </div>
                <div class="feature-body">
                    <h3 class="serif">Read God's Message here</h3>
                    <p>There's something grounding about sitting down with God's word, especially when life gets busy. Pasugo: God's Message has been sharing articles, reflections and stories of faith for years, and it's a wonderful place to start or continue your spiritual reading. Whether you have five minutes or an hour, there's always something worth discovering.</p>
                    <a class="btn btn-dark" href="https://www.pasugo.com.ph/" target="_blank" rel="noopener">Visit Pasugo</a>
                </div>
            </div>
        </div>
    </section>

    {{-- Where to go next --}}
    <section class="sec" id="explore">
        <div class="wrap">
            <div class="sec-head">
                <h2>Where to go next</h2>
                <p>Four places worth a visit. Each opens in a new tab.</p>
            </div>
            <div class="go">
                <a href="https://iglesianicristo.net/directory/" target="_blank" rel="noopener">
                    <h3>Accessible schedule of worship service</h3>
                    <p>Not sure when or where the nearest worship service is? This directory helps you find a congregation near you, along with service times.</p>
                    <span class="btn btn-dark">Find a schedule</span>
                </a>
                <a href="https://iglesianicristo.net/" target="_blank" rel="noopener">
                    <h3>News about God's Church</h3>
                    <p>The official home for announcements, updates and stories from across the Iglesia Ni Cristo community, kept fresh and easy to browse.</p>
                    <span class="btn btn-dark">Catch up on news</span>
                </a>
                <a href="https://incmedia.org/" target="_blank" rel="noopener">
                    <h3>True Christian activities</h3>
                    <p>From heartfelt programs to community outreach, INC Media captures everyday moments of faith in action, wherever you are.</p>
                    <span class="btn btn-dark">See what's happening</span>
                </a>
                <a href="https://incmuseum.net/" target="_blank" rel="noopener">
                    <h3>Know the true Church's history</h3>
                    <p>The Iglesia Ni Cristo Museum walks you through the church's history, a meaningful visit for anyone curious about how it all began.</p>
                    <span class="btn btn-dark">Explore the history</span>
                </a>
            </div>
        </div>
    </section>

    {{-- Official channels --}}
    <section class="official" id="official">
        <div class="wrap">
            <div class="sec-head">
                <h2>Official sites &amp; social media</h2>
                <p>The official Iglesia Ni Cristo websites and pages (2026 update). Choose a platform; each link opens in a new tab.</p>
            </div>

            <div class="tabs" role="tablist" aria-label="Platforms">
                @foreach ($official as $i => $g)
                    <button type="button" class="tab {{ $i === 0 ? 'on' : '' }}" role="tab" aria-selected="{{ $i === 0 ? 'true' : 'false' }}" data-panel="panel-{{ $i }}">
                        <span class="orb">{!! $icons[$g['icon']] !!}</span>
                        <span>{{ $g['name'] }}</span>
                    </button>
                @endforeach
            </div>

            @foreach ($official as $i => $g)
                <div class="panel {{ $i === 0 ? 'on' : '' }}" id="panel-{{ $i }}" role="tabpanel">
                    @foreach ($g['items'] as $item)
                        <a class="emblem" href="{{ $item[1] }}" target="_blank" rel="noopener noreferrer">
                            <span class="orb" aria-hidden="true">
                                @if (!empty($item[2])) {{ $item[2] }} @else {!! $icons[$g['icon']] !!} @endif
                            </span>
                            <span class="name">{{ $item[0] }}</span>
                        </a>
                    @endforeach
                </div>
            @endforeach
        </div>
    </section>

    <footer class="foot">
        &copy; {{ date('Y') }} Iglesia Ni Cristo (Church of Christ)
    </footer>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const page = document.querySelector('.faith-page');
    const still = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

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
    const box = document.getElementById('verse');
    const quote = document.getElementById('verse-quote');
    const vText = document.getElementById('verse-text');
    const vRef = document.getElementById('verse-ref');
    const langButtons = [...page.querySelectorAll('.lang-toggle button')];
    const LANGS = ['tl', 'en'];

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
    const other = from => {
        let n;
        do { n = Math.floor(Math.random() * VERSES.length); } while (n === from && VERSES.length > 1);
        return n;
    };
    let current = -1;
    try { current = Number(localStorage.getItem('faith-verse')); } catch (e) {}
    current = other(current);
    try { localStorage.setItem('faith-verse', String(current)); } catch (e) {}
    paint(current);

    document.getElementById('verse-next').addEventListener('click', () => {
        current = other(current);
        try { localStorage.setItem('faith-verse', String(current)); } catch (e) {}
        if (still) { paint(current); return; }
        box.classList.add('out');
        setTimeout(() => { paint(current); box.classList.remove('out'); }, 400);
    });

    // Same verse, switch language in place
    langButtons.forEach(b => b.addEventListener('click', () => {
        if (b.dataset.lang === lang) return;
        lang = b.dataset.lang;
        try { localStorage.setItem('faith-verse-lang', lang); } catch (e) {}
        paint(current);
    }));

    // Platform tabs
    const tabs = [...page.querySelectorAll('.tab')];
    const panels = [...page.querySelectorAll('.panel')];
    tabs.forEach(t => t.addEventListener('click', () => {
        tabs.forEach(x => {
            x.classList.toggle('on', x === t);
            x.setAttribute('aria-selected', x === t ? 'true' : 'false');
        });
        panels.forEach(p => p.classList.toggle('on', p.id === t.dataset.panel));
    }));
});
</script>
@endpush