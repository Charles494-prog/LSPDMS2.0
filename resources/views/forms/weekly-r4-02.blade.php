@extends('layouts.app')

@section('title', 'R4-02 Generator')

@section('content')
<style>
    .r402-page { display:grid; gap:1rem; max-width:1320px; margin:0 auto; color:var(--text); }
    .r402-card { min-width:0; padding:1.2rem; border:1px solid var(--border); border-radius:12px; background:var(--surface); }
    .r402-heading { display:flex; flex-wrap:wrap; align-items:flex-start; justify-content:space-between; gap:.8rem; }
    .r402-heading h1,.r402-card h2 { margin:0; font-size:1.2rem; }
    .r402-heading p,.r402-hint { margin:.35rem 0 0; color:var(--text-muted); font-size:.86rem; line-height:1.5; }
    .r402-back { color:var(--accent); font-weight:700; text-decoration:none; }
    .r402-filters { display:flex; flex-wrap:wrap; align-items:flex-end; gap:.7rem; margin-top:1rem; }
    .r402-field { display:grid; gap:.35rem; min-width:180px; color:var(--text-muted); font-size:.78rem; font-weight:700; }
    .r402-field input,.r402-field select,.r402-field textarea { width:100%; min-height:40px; padding:.55rem .65rem; border:1px solid var(--border); border-radius:8px; background:var(--bg); color:var(--text); font:inherit; }
    .r402-field textarea { min-height:80px; resize:vertical; font-family:ui-monospace,Consolas,monospace; }
    .r402-button { display:inline-flex; align-items:center; justify-content:center; min-height:40px; padding:.55rem .8rem; border:1px solid var(--border); border-radius:8px; background:var(--surface); color:var(--text); font:inherit; font-weight:700; text-decoration:none; cursor:pointer; }
    .r402-button.primary { border-color:var(--accent); background:var(--accent); color:#fff; }
    .r402-button:disabled { opacity:.55; cursor:not-allowed; }
    .r402-table-wrap { overflow:auto; border:1px solid var(--border); border-radius:9px; }
    .r402-table { width:100%; border-collapse:collapse; white-space:nowrap; font-size:.84rem; }
    .r402-table th,.r402-table td { padding:.62rem .7rem; border-bottom:1px solid var(--border); text-align:left; }
    .r402-table th { background:var(--bg); color:var(--text-muted); font-size:.72rem; letter-spacing:.04em; text-transform:uppercase; }
    .r402-table tr:last-child td { border-bottom:0; }
    .r402-empty { padding:1rem; color:var(--text-muted); text-align:center; }
    .r402-error { padding:.7rem .85rem; border:1px solid color-mix(in srgb,var(--danger) 35%,var(--border)); border-radius:8px; color:var(--danger); }
    .r402-option { display:flex; align-items:center; gap:.5rem; color:var(--text); font-size:.84rem; font-weight:650; }
    .r402-code-rules[hidden] { display:none; }
    @media(max-width:650px) { .r402-card { padding:.9rem; } .r402-field { min-width:100%; } }
</style>

<main class="r402-page">
    <section class="r402-card">
        <div class="r402-heading">
            <div>
                <h1>R4-02 Generator</h1>
                <p>Filter the R4-01 list, select rows by number or range, then assign the configured Hakbang code.</p>
            </div>
            <a class="r402-back" href="{{ route('form-generator', ['panel' => 'weekly']) }}">Back to Form Generator</a>
        </div>
        <form class="r402-filters" method="GET" action="{{ route('forms.weekly.r4-02') }}">
            <label class="r402-field" for="r402Purok">Purok
                <select id="r402Purok" name="purok" required>
                    <option value="">Select Purok</option>
                    @foreach ($puroks as $purok)
                        <option value="{{ $purok->purok_number }}" @selected($selectedPurok === (string) $purok->purok_number)>
                            {{ $purok->purok_name ?: 'Purok '.$purok->purok_number }}
                        </option>
                    @endforeach
                </select>
            </label>
            <label class="r402-field" for="r402Status">Kalagayan
                <select id="r402Status" name="kalagayan" required>
                    <option value="">Select Kalagayan</option>
                    <option value="MS" @selected($selectedStatus === 'MS')>MS</option>
                    <option value="UWP" @selected($selectedStatus === 'UWP')>UWP</option>
                </select>
            </label>
            <button class="r402-button primary" type="submit">Show R4-01 list</button>
        </form>
    </section>

    @if ($errors->any())
        <div class="r402-error" role="alert">{{ $errors->first() }}</div>
    @endif

    @if ($selectedPurok && $selectedStatus)
        <section class="r402-card">
            <div class="r402-heading">
                <div>
                    <h2>R4-01 records: {{ $purokNames[$selectedPurok] ?? 'Purok '.$selectedPurok }} · {{ $selectedStatus }}</h2>
                    <p class="r402-hint">{{ count($rows) }} rows. Use the visible row numbers when selecting records below.</p>
                </div>
            </div>
            <div class="r402-table-wrap" style="margin-top:.9rem">
                <table class="r402-table">
                    <thead><tr><th>No.</th><th>R4-01 Code</th><th>Name</th><th>Registry</th><th>Purok-Grupo</th><th>Kapisanan</th></tr></thead>
                    <tbody>
                    @forelse ($rows as $index => $row)
                        <tr>
                            <td>{{ $index + 1 }}</td><td>{{ $row['code'] }}</td><td>{{ $row['name'] }}</td>
                            <td>{{ $row['registry'] }}</td><td>{{ $row['purok_grupo'] }}</td><td>{{ $row['kapisanan'] }}</td>
                        </tr>
                    @empty
                        <tr><td class="r402-empty" colspan="6">No R4-01 records match this Purok and Kalagayan.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            @if ($rows !== [])
                <form method="POST" action="{{ route('forms.weekly.r4-02', ['purok' => $selectedPurok, 'kalagayan' => $selectedStatus]) }}" class="r402-filters" style="align-items:flex-start">
                    @csrf
                    <input type="hidden" name="purok" value="{{ $selectedPurok }}">
                    <input type="hidden" name="kalagayan" value="{{ $selectedStatus }}">
                    <label class="r402-field" for="r402Selection">R4-01 row numbers
                        <input id="r402Selection" name="selection" value="{{ old('selection', '1-5') }}" placeholder="1-5 or 1,3,5" required>
                    </label>
                    @if ($hakbangs->isEmpty())
                        <p class="r402-error" style="flex-basis:100%">No Hakbang codes are configured for {{ $selectedStatus }}. Ask an administrator to add them in Admin Portal → Mga R4-02 Hakbang.</p>
                    @else
                        <label class="r402-field" for="r402Hakbang">Hakbang code for all selected rows
                            <select id="r402Hakbang" name="hakbang_code" required>
                                <option value="">Select code</option>
                                @foreach ($hakbangs as $hakbang)
                                    <option value="{{ $hakbang->code }}" @selected(old('hakbang_code') === $hakbang->code)>{{ $hakbang->code }} — {{ $hakbang->description }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="r402-field" for="r402DateDalaw">Date of Dalaw (optional)
                            <input id="r402DateDalaw" name="date_of_dalaw" type="date" value="{{ old('date_of_dalaw') }}">
                        </label>
                        <label class="r402-field" for="r402DateUlat">Date of Ulat (optional)
                            <input id="r402DateUlat" name="date_of_ulat" type="date" value="{{ old('date_of_ulat') }}">
                        </label>
                        <label class="r402-option" style="flex-basis:100%">
                            <input type="checkbox" name="split_codes" value="1" @checked(old('split_codes')) id="r402SplitCodes">
                            Assign different Hakbang codes by row numbers
                        </label>
                        <label class="r402-field r402-code-rules" id="r402CodeRulesField" for="r402CodeRules" style="flex-basis:min(100%,600px)">
                            Row-to-code assignments
                            <textarea id="r402CodeRules" name="code_assignments" placeholder="1-3=1-7;5,7=1-8">{{ old('code_assignments') }}</textarea>
                            <span>Example: 1-3=1-7;5,7=1-8. Every selected row must be assigned once, using a code configured for {{ $selectedStatus }}.</span>
                        </label>
                        <button class="r402-button primary" type="submit">Save and generate R4-02</button>
                    @endif
                </form>
                <script>
                    (() => {
                        const toggle = document.getElementById('r402SplitCodes');
                        const rules = document.getElementById('r402CodeRulesField');
                        const code = document.getElementById('r402Hakbang');
                        if (!toggle || !rules || !code) return;
                        const update = () => {
                            rules.hidden = !toggle.checked;
                            code.required = !toggle.checked;
                            document.getElementById('r402CodeRules').required = toggle.checked;
                        };
                        toggle.addEventListener('change', update);
                        update();
                    })();
                </script>
            @endif
        </section>
    @endif

    <section class="r402-card">
        <h2>Recently generated R4-02 forms</h2>
        <div class="r402-table-wrap" style="margin-top:.8rem">
            <table class="r402-table">
                <thead><tr><th>Generated</th><th>Purok</th><th>Kalagayan</th><th>Rows</th><th>Dates</th><th></th></tr></thead>
                <tbody>
                @forelse ($recentForms as $form)
                    <tr>
                        <td>{{ $form->created_at?->format('Y-m-d H:i') }}</td>
                        <td>{{ $purokNames[$form->purok_number] ?? 'Purok '.$form->purok_number }}</td>
                        <td>{{ $form->kalagayan }}</td>
                        <td>{{ $form->form_records_count }}</td>
                        <td>Dalaw: {{ $form->date_of_dalaw?->format('Y-m-d') ?? '—' }} · Ulat: {{ $form->date_of_ulat?->format('Y-m-d') ?? '—' }}</td>
                        <td><a class="r402-button" href="{{ route('forms.weekly.r4-02.print', $form) }}">Open / Print</a></td>
                    </tr>
                @empty
                    <tr><td class="r402-empty" colspan="6">No R4-02 forms have been generated yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</main>
@endsection
