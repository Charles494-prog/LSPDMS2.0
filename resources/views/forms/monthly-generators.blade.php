@extends('layouts.app')

@section('title', 'Monthly Generators')

@section('content')
    <style>
        .monthly-generators-page { --r-btn:0; --hover:color-mix(in srgb,var(--text) 6%,var(--bg)); max-width:1500px; margin:0 auto; }
        .monthly-generators-back { display:inline-flex; align-items:center; min-height:32px; margin-bottom:1.25rem; padding:.3rem .65rem; border:1px solid var(--text); border-radius:var(--r-btn); background:transparent; color:var(--text); font-size:.8125rem; font-weight:500; text-decoration:none; transition:background-color .12s,border-color .12s,color .12s; }
        .monthly-generators-back:hover { background:var(--text); border-color:var(--text); color:var(--bg); }
        .monthly-generators-back:focus-visible { outline:2px solid color-mix(in srgb,var(--text) 55%,transparent); outline-offset:2px; }
        .monthly-generators-section + .monthly-generators-section { margin-top:2rem; padding-top:1.5rem; border-top:1px solid var(--border); }
        .monthly-generators-section > h2 { margin:0 0 1rem; font-size:1.125rem; font-weight:600; letter-spacing:-.01em; line-height:1.3; }
        @media (prefers-reduced-motion:reduce) { .monthly-generators-back { transition:none; } }
    </style>
    <main class="monthly-generators-page">
        <a class="monthly-generators-back" href="{{ route('form-generator', ['panel' => 'monthly', 'monthly_section' => 'monthly-forms']) }}">← Monthly Forms</a>
        <section class="monthly-generators-section" aria-labelledby="resumen-generator-heading">
            <h2 id="resumen-generator-heading">Resumen Generator</h2>
            @include('forms.partials.monthly-resumen', ['embedded' => true])
        </section>
        <section class="monthly-generators-section" aria-labelledby="r401-generator-heading">
            <h2 id="r401-generator-heading">R4-01 Generator</h2>
            @include('forms.partials.monthly-r4-01', ['embedded' => true])
        </section>
    </main>
    @include('forms.monthly-session-widget')
@endsection