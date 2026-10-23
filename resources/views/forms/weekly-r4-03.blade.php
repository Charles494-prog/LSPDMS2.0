@extends('layouts.app')

@section('title', 'R4-03 Generator')

@section('content')
<style>
    .r403-page { display:grid; gap:1rem; max-width:1120px; margin:0 auto; color:var(--text); }
    .r403-card { padding:1.2rem; border:1px solid var(--border); border-radius:12px; background:var(--surface); }
    .r403-heading { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:.7rem; }
    .r403-heading h1,.r403-group h2 { margin:0; font-size:1.2rem; }
    .r403-heading p,.r403-muted { margin:.35rem 0 0; color:var(--text-muted); font-size:.86rem; }
    .r403-filter { display:flex; flex-wrap:wrap; align-items:end; gap:.7rem; margin-top:1rem; }
    .r403-filter label { display:grid; gap:.3rem; min-width:180px; color:var(--text-muted); font-size:.78rem; font-weight:700; }
    .r403-filter select { min-height:40px; padding:.5rem .6rem; border:1px solid var(--border); border-radius:8px; background:var(--bg); color:var(--text); font:inherit; }
    .r403-button { display:inline-flex; align-items:center; justify-content:center; min-height:40px; padding:.55rem .8rem; border:1px solid var(--border); border-radius:8px; background:var(--surface); color:var(--text); font:inherit; font-weight:700; text-decoration:none; cursor:pointer; }
    .r403-button.primary { border-color:var(--accent); background:var(--accent); color:#fff; }
    .r403-list { display:grid; gap:.7rem; margin-top:1rem; }
    .r403-group { padding:1rem; border:1px solid var(--border); border-radius:9px; background:var(--bg); }
    .r403-group-head { display:flex; flex-wrap:wrap; justify-content:space-between; gap:.5rem; }
    .r403-empty { margin:1rem 0 0; padding:1rem; border:1px dashed var(--border); border-radius:8px; color:var(--text-muted); text-align:center; }
</style>
<main class="r403-page">
    <section class="r403-card">
        <div class="r403-heading">
            <div><h1>R4-03 Generator</h1><p>Summarize generated R4-02 forms, grouped only under Puroks that have saved reports.</p></div>
            <a class="r403-button" href="{{ route('form-generator') }}#generate-weekly">Back to Form Generator</a>
        </div>
        <form class="r403-filter" method="GET" action="{{ route('forms.weekly.r4-03') }}">
            <label for="r403Status">Kalagayan
                <select id="r403Status" name="kalagayan">
                    <option value="all" @selected($selectedStatus === 'all')>All saved forms</option>
                    <option value="MS" @selected($selectedStatus === 'MS')>MS</option>
                    <option value="UWP" @selected($selectedStatus === 'UWP')>UWP</option>
                </select>
            </label>
            <button class="r403-button primary" type="submit">Show saved R4-02 groups</button>
            @if ($groups->isNotEmpty())
                <a class="r403-button" href="{{ route('forms.weekly.r4-03.print', ['kalagayan' => $selectedStatus]) }}">Generate / Print R4-03</a>
            @endif
        </form>
        @if ($groups->isEmpty())
            <p class="r403-empty">No R4-02 forms have been generated for this selection. No empty Purok sections will be created.</p>
        @else
            <div class="r403-list">
                @foreach ($groups as $group)
                    <article class="r403-group">
                        <div class="r403-group-head">
                            <h2>{{ $group['name'] }}</h2>
                            <span class="r403-muted">{{ $group['forms']->count() }} R4-02 form(s) · {{ $group['record_count'] }} listed record(s)</span>
                        </div>
                        <p class="r403-muted">Kalagayan: {{ $group['forms']->pluck('kalagayan')->unique()->implode(', ') }}</p>
                    </article>
                @endforeach
            </div>
        @endif
    </section>
</main>
@endsection
