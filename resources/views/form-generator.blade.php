@extends('layouts.app')

@section('title', 'Form Generator')

@section('content')
@php
    $icon = fn (string $paths): string => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$paths.'</svg>';
    $doc = '<path d="M7 3h7l5 5v13H5V5a2 2 0 0 1 2-2Z"/><path d="M14 3v5h5M8 13h8M8 17h8"/>';

    // Generate Forms: the weekly and monthly reports. Deliberation is its own section below.
    $generateGroups = [
        ['id' => 'generate-weekly', 'label' => 'Weekly Forms', 'text' => 'Weekly reporting forms and summaries.', 'cards' => [
            ['url' => route('forms.weekly.r4-02'), 'icon' => $doc, 'title' => 'R4-02 Generator', 'text' => 'Filter R4-01 members, assign configured Hakbang codes, and save a printable R4-02.', 'tag' => 'Open R4-02 Maker'],
            ['url' => route('forms.weekly.r4-03'), 'icon' => '<path d="M7 3h7l5 5v13H5V5a2 2 0 0 1 2-2Z"/><path d="M14 3v5h5M8 13h8M8 17h5"/>', 'title' => 'R4-03 Generator', 'text' => 'Summarize saved R4-02 forms, grouped only by Puroks with generated reports.', 'tag' => 'Open R4-03 Maker'],
        ]],
        ['id' => 'generate-monthly', 'label' => 'Monthly Forms', 'text' => 'This month\'s Resumen and R4-01, and the saved snapshots.', 'cards' => [
            ['url' => route('forms.monthly.generators'), 'icon' => '<path d="M4 19V5m0 14h16M8 15l3-4 3 2 5-6"/>', 'title' => 'Resumen & R4-01 Generator (This Month)', 'text' => 'Open both monthly generators together, with Resumen first and R4-01 below.', 'tag' => 'Open Monthly Generators'],
            ['url' => route('forms.monthly.r4-01.snapshots'), 'icon' => '<path d="M4 5h16v14H4z"/><path d="M8 9h8M8 13h8M8 17h5"/>', 'title' => 'R4-01 Snapshots', 'text' => 'Browse and re-download saved Resumen and R4-01 reports.', 'tag' => 'Open Report Snapshots'],
        ]],
    ];
    $deliberationCards = [
        ['url' => route('forms.monthly.deliberation'), 'icon' => '<path d="M12 5v14m-7-7h14"/>', 'title' => 'Add', 'text' => 'Add members to the monthly deliberation workflow.', 'tag' => 'Open Add Form'],
        ['url' => route('forms.monthly.deliberation', ['mode' => 'subtract']), 'icon' => '<path d="M5 12h14"/>', 'title' => 'Subtract', 'text' => 'Search for an existing member and mark an eligible record inactive.', 'tag' => 'Open Subtract Form'],
        ['url' => route('forms.monthly.modify'), 'icon' => '<path d="M4 20h4l10.5-10.5a2.8 2.8 0 0 0-4-4L4 16v4Z"/><path d="m13.5 7.5 4 4"/>', 'title' => 'Modify', 'text' => 'Move a member to another Purok or Grupo.', 'tag' => 'Open Modify Form'],
    ];
    $go = '<svg class="generator-go" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 17 17 7M9 7h8v8"/></svg>';
@endphp
<style>
    /* Flat and monochrome. Inherits --bg / --surface / --border / --text / --text-muted from the layout,
       so it follows the light/dark switch. */
    .form-hub { max-width: 1180px; margin: 0 auto; color: var(--text); --r: 8px; }
    .form-hub h1, .form-hub h2, .form-hub h3, .form-hub p { margin: 0; }

    /* Jump links sit under the heading: the floating monthly-session bar uses the top-right corner. */
    .form-hero { display: flex; flex-direction: column; align-items: flex-start; gap: 1rem; margin-bottom: 1.75rem; }
    .form-hero h1 { font-size: clamp(1.6rem, 3.2vw, 2.1rem); font-weight: 650; letter-spacing: -.035em; line-height: 1.05; }
    .form-hero p { max-width: 38rem; margin-top: .4rem; color: var(--text-muted); font-size: .9rem; line-height: 1.55; }

    /* Jump links to the two sections */
    .form-jump { display: inline-flex; flex-wrap: wrap; gap: 2px; max-width: 100%; padding: 3px; border: 1px solid var(--border); border-radius: var(--r); background: var(--bg); }
    .form-jump a { display: flex; align-items: center; gap: .5rem; padding: .5rem .9rem; border-radius: 6px; color: var(--text-muted); font-size: .85rem; font-weight: 600; text-decoration: none; transition: background .15s ease, color .15s ease; }
    .form-jump a:hover { color: var(--text); background: var(--surface); }
    .form-jump svg { width: 16px; height: 16px; flex: none; }

    /* Sections: a numbered step, a heading, then the groups */
    .form-section { scroll-margin-top: calc(var(--bar-height, 56px) + 1rem); }
    .form-section + .form-section { margin-top: 2.25rem; padding-top: 2rem; border-top: 1px solid var(--border); }
    .form-section-head { display: flex; align-items: flex-start; gap: .85rem; margin-bottom: 1.1rem; }
    .form-step { display: grid; place-items: center; flex: none; width: 2rem; height: 2rem; border: 1px solid var(--border); border-radius: 50%; color: var(--text); font-size: .8rem; font-weight: 700; }
    .form-section-head h2 { font-size: 1.15rem; font-weight: 650; letter-spacing: -.02em; }
    .form-section-head p { margin-top: .2rem; color: var(--text-muted); font-size: .85rem; }

    .form-group { scroll-margin-top: calc(var(--bar-height, 56px) + 1rem); }
    .form-group + .form-group { margin-top: 1.4rem; }
    .form-group-head { display: flex; align-items: baseline; gap: .6rem; margin-bottom: .65rem; }
    .form-group-head h3 { font-size: .8rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; }
    .form-group-head p { color: var(--text-muted); font-size: .8rem; }
    .form-section.is-focus .form-group-head h3, .form-group.is-focus .form-group-head h3 { text-decoration: underline; text-underline-offset: 4px; }

    /* Generators: tiles in a grid */
    .generator-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(270px, 1fr)); gap: .75rem; }
    .generator-card { display: flex; flex-direction: column; gap: .35rem; min-height: 168px; padding: 1.1rem 1.15rem; border: 1px solid var(--border); border-radius: var(--r); background: var(--surface); color: inherit; text-decoration: none; transition: border-color .15s ease; }
    .generator-card:hover { border-color: var(--text); }
    .generator-card-top { display: flex; align-items: center; justify-content: space-between; margin-bottom: .75rem; }
    .generator-icon { display: grid; width: 36px; height: 36px; place-items: center; border: 1px solid var(--border); border-radius: 6px; color: var(--text); }
    .generator-icon svg { width: 18px; height: 18px; }
    .generator-go { width: 16px; height: 16px; color: var(--text-muted); transition: color .15s ease, transform .15s ease; }
    .generator-card:hover .generator-go { color: var(--text); transform: translate(2px, -2px); }
    .generator-card h3 { font-size: .95rem; font-weight: 650; letter-spacing: -.01em; }
    .generator-card p { color: var(--text-muted); font-size: .83rem; line-height: 1.5; }
    .generator-tag { margin-top: auto; padding-top: .75rem; color: var(--text); font-size: .8rem; font-weight: 600; }
    .form-hub a:focus-visible { outline: 2px solid var(--text); outline-offset: 2px; }

    @media (prefers-reduced-motion: reduce) { .form-hub * { transition: none !important; } }
    @media (max-width: 680px) {
        .form-jump { width: 100%; }
        .form-jump a { flex: 1 1 auto; justify-content: center; padding: .5rem .6rem; }
        .generator-grid { grid-template-columns: 1fr; }
        .generator-card { min-height: 0; }
    }
</style>

<div class="form-hub">
    <header class="form-hero">
        <div>
            <h1>Form Generator</h1>
            <p>Generate the weekly and monthly forms, or record this month's deliberation (Add, Subtract, Modify).</p>
        </div>
        <nav class="form-jump" aria-label="Form Generator sections">
            <a href="#generate-forms">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 3h7l5 5v13H5V5a2 2 0 0 1 2-2Z"/><path d="M14 3v5h5M8 13h8M8 17h8"/></svg>
                Generate Forms
            </a>
            <a href="#deliberation">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 4h14v16H5zM8 8h8M8 12h8M8 16h4"/><path d="m16 16 1.5 1.5L21 14"/></svg>
                Deliberation Form
            </a>
        </nav>
    </header>

    <section class="form-section" id="generate-forms" aria-labelledby="generate-forms-title">
        <div class="form-section-head">
            <span class="form-step" aria-hidden="true">1</span>
            <div>
                <h2 id="generate-forms-title">Generate Forms</h2>
                <p>Weekly and monthly reports, made from the current records.</p>
            </div>
        </div>
        @foreach ($generateGroups as $group)
            <div class="form-group {{ $focusSection === $group['id'] ? 'is-focus' : '' }}" id="{{ $group['id'] }}">
                <div class="form-group-head"><h3>{{ $group['label'] }}</h3><p>{{ $group['text'] }}</p></div>
                <div class="generator-grid">
                    @foreach ($group['cards'] as $card)
                        <a class="generator-card" href="{{ $card['url'] }}">
                            <div class="generator-card-top"><span class="generator-icon">{!! $icon($card['icon']) !!}</span>{!! $go !!}</div>
                            <h3>{{ $card['title'] }}</h3>
                            <p>{{ $card['text'] }}</p>
                            <span class="generator-tag">{{ $card['tag'] }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        @endforeach
    </section>

    <section class="form-section {{ $focusSection === 'deliberation' ? 'is-focus' : '' }}" id="deliberation" aria-labelledby="deliberation-title">
        <div class="form-section-head">
            <span class="form-step" aria-hidden="true">2</span>
            <div>
                <h2 id="deliberation-title">Deliberation Form</h2>
                <p>Record this month's changes. Start the monthly session (upper-right) first.</p>
            </div>
        </div>
        <div class="generator-grid">
            @foreach ($deliberationCards as $card)
                <a class="generator-card" href="{{ $card['url'] }}">
                    <div class="generator-card-top"><span class="generator-icon">{!! $icon($card['icon']) !!}</span>{!! $go !!}</div>
                    <h3>{{ $card['title'] }}</h3>
                    <p>{{ $card['text'] }}</p>
                    <span class="generator-tag">{{ $card['tag'] }}</span>
                </a>
            @endforeach
        </div>
    </section>
</div>
@include('forms.monthly-session-widget')
@endsection

@if ($focusSection)
    @push('scripts')
    <script>
        // Old links (?panel=…) land on the matching section.
        document.getElementById(@json($focusSection))?.scrollIntoView({ block: 'start' });
    </script>
    @endpush
@endif
