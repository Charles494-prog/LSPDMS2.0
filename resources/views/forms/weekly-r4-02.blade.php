{{--
    Contract with the controller (the controller must be updated to match):
    GET  forms.weekly.r4-02   purok = comma list of purok numbers or "all", kalagayan = MS | UWP (one only).
                              Optional $purokGroups = [['number' => , 'name' => , 'rows' => [...]], ...].
                              Without it, the view falls back to $rows for the first selected Purok.
    POST forms.weekly.r4-02   one request per Purok: purok, kalagayan, selection ("1-10,15"),
                              row_keys ("1:r12,2:r15", each selected row's data-key so a changed list is refused),
                              hakbang_code OR split_codes + code_assignments, date_of_dalaw, date_of_ulat,
                              output = "r4-02" | "r4-03" (the controller returns the matching DOCX).
--}}
@extends('layouts.app')

@section('title', 'R4-02 Generator')

@section('content')
<style>
    .r402-page { --hover:color-mix(in srgb,var(--text) 6%,var(--bg)); max-width:1320px; margin:0 auto; color:var(--text); }
    .r402-page button,.r402-page input,.r402-page select { font-family:inherit; }
    .r402-head { display:flex; flex-wrap:wrap; align-items:flex-end; justify-content:space-between; gap:.8rem; padding-bottom:1rem; border-bottom:1px solid var(--border); }
    .r402-head h1 { margin:0; font-size:1.25rem; font-weight:600; letter-spacing:-.01em; line-height:1.3; }
    .r402-head p { margin:.3rem 0 0; color:var(--text-muted); font-size:.85rem; line-height:1.5; }
    .r402-back { color:var(--text); font-size:.82rem; font-weight:500; text-decoration:underline; text-underline-offset:3px; }
    .r402-back:hover { color:var(--text-muted); }
    .r402-section { padding:1.25rem 0; border-bottom:1px solid var(--border); }
    .r402-section:last-child { border-bottom:0; }
    .r402-section-title { display:flex; flex-wrap:wrap; align-items:baseline; justify-content:space-between; gap:.5rem 1rem; margin:0 0 .8rem; }
    .r402-section-title h2 { margin:0; font-size:1rem; font-weight:600; letter-spacing:-.01em; }
    .r402-muted { color:var(--text-muted); font-size:.8rem; font-weight:500; font-variant-numeric:tabular-nums; }
    .r402-note { margin:0; padding:.7rem 0; color:var(--text-muted); font-size:.82rem; }
    .r402-error { margin:1rem 0 0; padding:.7rem .85rem; border:1px solid #e5484d; border-radius:8px; color:#e5484d; font-size:.85rem; font-weight:600; }

    .r402-tools { display:flex; flex-wrap:wrap; align-items:center; gap:.65rem; padding-top:1rem; font-weight:500; }
    .r402-filter-add { position:relative; }
    .r402-filter-trigger { display:inline-flex; align-items:center; gap:.5rem; min-height:36px; padding:0 .95rem; border:1px solid var(--border); border-radius:999px; background:transparent; color:var(--text); font-size:.82rem; font-weight:700; cursor:pointer; transition:background-color .12s,border-color .12s; }
    .r402-filter-trigger:hover,.r402-filter-trigger[aria-expanded="true"] { background:var(--hover); border-color:color-mix(in srgb,var(--text) 45%,var(--border)); }
    .r402-filter-trigger svg { width:15px; height:15px; flex:none; }
    .r402-filter-menu { position:absolute; top:calc(100% + 6px); bottom:auto; left:0; z-index:30; width:320px; max-width:calc(100vw - 2rem); border:1px solid var(--border); border-radius:12px; background:var(--surface); color:var(--text); overflow:hidden; }
    .r402-filter-menu--up { top:auto; bottom:calc(100% + 6px); }
    .r402-filter-menu[hidden] { display:none; }
    .r402-filter-head { border-bottom:1px solid var(--text); }
    .r402-filter-search { display:block; width:100%; min-height:44px; margin:0; padding:.65rem 1rem; border:0!important; border-radius:0; background:transparent!important; box-shadow:none!important; outline:none!important; color:var(--text); font-size:.85rem; font-weight:500; }
    .r402-filter-search::placeholder { color:var(--text-muted); }
    .r402-filter-label { padding:.6rem 1rem .25rem; color:var(--text-muted); font-size:.72rem; font-weight:700; letter-spacing:.04em; text-transform:uppercase; }
    .r402-filter-list { max-height:220px; margin:0; padding:.35rem; overflow-y:auto; list-style:none; }
    .r402-filter-list li[hidden] { display:none; }
    .r402-filter-row { display:flex; align-items:center; justify-content:space-between; gap:.75rem; width:100%; min-height:38px; padding:.45rem .65rem; border:0; border-radius:8px; background:transparent; color:var(--text); font-size:.85rem; font-weight:500; text-align:left; cursor:pointer; }
    .r402-filter-row:hover,.r402-filter-row:focus-visible { background:color-mix(in srgb,var(--text) 8%,var(--bg)); outline:none; }
    .r402-filter-option[aria-checked="true"] { font-weight:700; }
    .r402-filter-check { width:14px; height:14px; flex:none; opacity:0; }
    .r402-filter-option[aria-checked="true"] .r402-filter-check { opacity:1; color:var(--text); }
    .r402-filter-none { margin:0; padding:.65rem 1rem; color:var(--text-muted); font-size:.8rem; }
    .r402-filter-section { border-top:1px solid var(--border); }
    .r402-filter-seg { display:flex; gap:.5rem; padding:.35rem 1rem .8rem; }
    .r402-seg-btn { flex:1; min-height:34px; border:1px solid var(--border); border-radius:8px; background:transparent; color:var(--text); font-size:.82rem; font-weight:700; cursor:pointer; transition:background-color .12s,color .12s,border-color .12s; }
    .r402-seg-btn:hover { background:color-mix(in srgb,var(--text) 8%,var(--bg)); }
    .r402-seg-btn[aria-pressed="true"] { border-color:var(--text); background:var(--text); color:var(--bg); }
    .r402-filter-foot { display:flex; align-items:center; justify-content:space-between; gap:.75rem; padding:.6rem 1rem; border-top:1px solid var(--border); }
    .r402-foot-clear { padding:0; border:0; background:transparent; color:var(--text-muted); font-size:.8rem; font-weight:500; text-decoration:underline; text-underline-offset:3px; cursor:pointer; }
    .r402-foot-clear:hover { color:var(--text); }
    .r402-foot-apply { min-height:32px; padding:0 .95rem; border:1px solid var(--text); border-radius:999px; background:var(--text); color:var(--bg); font-size:.8rem; font-weight:700; cursor:pointer; transition:opacity .12s; }
    .r402-foot-apply:hover:not(:disabled) { opacity:.85; }
    .r402-foot-apply:disabled { border-color:var(--border); background:transparent; color:var(--text-muted); cursor:not-allowed; opacity:.55; }
    .r402-chip { display:inline-flex; align-items:center; gap:.45rem; min-height:36px; padding:0 .4rem 0 .85rem; border:1px dashed color-mix(in srgb,var(--text) 45%,var(--border)); border-radius:999px; color:var(--text); font-size:.82rem; }
    .r402-chip-label { color:var(--text-muted); font-weight:500; }
    .r402-chip-value { font-weight:700; }
    .r402-chip-remove { display:inline-flex; align-items:center; justify-content:center; width:24px; height:24px; padding:0; border:0; border-radius:50%; background:transparent; color:var(--text-muted); cursor:pointer; }
    .r402-chip-remove:hover { background:color-mix(in srgb,var(--text) 10%,var(--bg)); color:var(--text); }
    .r402-chip-remove svg { width:12px; height:12px; }
    .r402-reset { color:var(--text); font-size:.82rem; font-weight:500; text-decoration:underline; text-underline-offset:3px; }
    .r402-reset:hover { color:var(--text-muted); }

    .r402-quick { display:flex; flex-wrap:wrap; align-items:center; gap:.65rem 1rem; }
    .r402-quick label { display:grid; gap:.3rem; color:var(--text-muted); font-size:.75rem; font-weight:600; }
    .r402-input { min-height:36px; padding:.4rem .65rem; border:1px solid var(--border); border-radius:8px; background:transparent; color:var(--text); font-size:.85rem; font-weight:500; color-scheme:light dark; }
    .r402-quick .r402-input { width:min(100%,320px); }
    .r402-quick-meta { display:flex; align-items:center; gap:1rem; align-self:flex-end; min-height:36px; }
    .r402-link-btn { padding:0; border:0; background:transparent; color:var(--text); font-size:.8rem; font-weight:500; text-decoration:underline; text-underline-offset:3px; cursor:pointer; }
    .r402-link-btn:hover { color:var(--text-muted); }
    .r402-quick-note { flex-basis:100%; margin:0; color:var(--text-muted); font-size:.78rem; line-height:1.5; }

    .r402-group + .r402-group { margin-top:1.25rem; }
    .r402-table-wrap { max-height:380px; overflow:auto; border:1px solid var(--border); border-radius:8px; }
    .r402-table { width:100%; border-collapse:collapse; white-space:nowrap; font-size:.84rem; }
    .r402-table th,.r402-table td { padding:.55rem .7rem; border-bottom:1px solid var(--border); text-align:left; vertical-align:middle; }
    .r402-table th { position:sticky; top:0; z-index:1; background:var(--bg); color:var(--text-muted); font-size:.72rem; font-weight:600; letter-spacing:.04em; text-transform:uppercase; }
    .r402-table td { font-variant-numeric:tabular-nums; }
    .r402-table tbody tr:last-child td { border-bottom:0; }
    .r402-records-table tbody tr { cursor:pointer; }
    .r402-records-table tbody tr:hover { background:var(--hover); }
    .r402-records-table tbody tr.is-selected { background:color-mix(in srgb,var(--text) 11%,var(--bg)); }
    .r402-records-table tbody tr.is-selected td { font-weight:700; }
    .r402-check { width:36px; }
    .r402-page input[type="checkbox"] { width:16px; height:16px; margin:0; accent-color:var(--text); cursor:pointer; }
    .r402-empty { padding:1rem; color:var(--text-muted); text-align:center; }

    .r402-summary-wrap { overflow-x:auto; border:1px solid var(--border); border-radius:8px; }
    .r402-summary-table td { white-space:nowrap; }
    .r402-summary-table .r402-records-cell { min-width:110px; font-weight:700; }
    .r402-summary-table select.r402-input { max-width:240px; }
    .r402-split { display:flex; align-items:center; gap:.4rem; margin-top:.4rem; color:var(--text-muted); font-size:.75rem; font-weight:500; cursor:pointer; }
    .r402-split input { width:14px; height:14px; }
    .r402-assign { display:block; width:100%; margin-top:.4rem; }
    .r402-assign[hidden] { display:none; }
    .r402-actions { display:flex; flex-wrap:wrap; gap:.4rem; }
    .r402-btn { display:inline-flex; align-items:center; justify-content:center; min-height:34px; padding:0 .9rem; border:1px solid var(--border); border-radius:999px; background:transparent; color:var(--text); font-size:.8rem; font-weight:600; white-space:nowrap; cursor:pointer; transition:background-color .12s,border-color .12s,opacity .12s; }
    .r402-btn:hover { background:var(--hover); border-color:color-mix(in srgb,var(--text) 45%,var(--border)); }
    .r402-btn.primary { border-color:var(--text); background:var(--text); color:var(--bg); }
    .r402-btn.primary:hover { opacity:.85; }
    .r402-btn:disabled { border-color:var(--border); background:transparent; color:var(--text-muted); cursor:not-allowed; opacity:.55; }
    .r402-forms { display:none; }

    .r402-page button:focus-visible,.r402-page a:focus-visible,.r402-page input:focus-visible,.r402-page select:focus-visible { outline:2px solid color-mix(in srgb,var(--text) 55%,transparent); outline-offset:2px; }
    @media (prefers-reduced-motion:reduce) { .r402-filter-trigger,.r402-seg-btn,.r402-btn,.r402-foot-apply { transition:none; } }
</style>

@php
    $selectedPurokRaw = is_array($selectedPurok ?? null) ? implode(',', $selectedPurok) : (string) ($selectedPurok ?? '');
    $selectedValues = array_values(array_filter(explode(',', $selectedPurokRaw), fn ($v) => $v !== ''));
    $hasPurok = $selectedValues !== [];
    $selectedStatus = in_array($selectedStatus ?? null, ['MS', 'UWP'], true) ? $selectedStatus : null;
    $hasStatus = $selectedStatus !== null;
    $ready = $hasPurok && $hasStatus;

    $purokLabels = [];
    foreach ($puroks as $p) {
        $purokLabels[(string) ($p->selection_token ?? $p->purok_number)] = $p->purok_name ?: 'Purok '.$p->purok_number;
    }
    $purokChipValue = null;
    if ($hasPurok) {
        if (in_array('all', $selectedValues, true)) {
            $purokChipValue = 'All Purok';
        } elseif (count($selectedValues) > 1) {
            $purokChipValue = count($selectedValues).' selected';
        } else {
            $purokChipValue = $purokLabels[$selectedValues[0]] ?? 'Purok '.$selectedValues[0];
        }
    }

    $groups = $purokGroups ?? null;
    if ($groups === null) {
        $groups = [];
        if ($ready && ($rows ?? []) !== []) {
            $firstNumber = $selectedValues[0];
            $groups[] = [
                'number' => $firstNumber,
                'name' => ($purokNames[$firstNumber] ?? null) ?: ($purokLabels[$firstNumber] ?? 'Purok '.$firstNumber),
                'rows' => $rows,
            ];
        }
    }
    $hakbangList = $hakbangs ?? collect();
@endphp

<main class="r402-page">
    <header class="r402-head">
        <div>
            <h1>R4-02 Generator</h1>
            <p>Filter the R4-01 list, pick the rows you need, then download the R4-02 and R4-03 for each Purok.</p>
        </div>
        <a class="r402-back" href="{{ route('form-generator') }}#generate-weekly">Back to Form Generator</a>
    </header>

    @if ($errors->any())
        <div class="r402-error" role="alert">{{ $errors->first() }}</div>
    @endif
    @foreach ($reportWarnings ?? [] as $reportWarning)
        <p class="r402-note" role="status" data-r402-warning>{{ $reportWarning }}</p>
    @endforeach

    <form class="r402-tools" id="r402Filters" method="GET" action="{{ route('forms.weekly.r4-02') }}">
        <input type="hidden" name="purok" id="r402PurokInput" value="{{ $selectedPurokRaw }}">
        <input type="hidden" name="kalagayan" id="r402KalagayanInput" value="{{ $selectedStatus ?? '' }}">

        <div class="r402-filter-add">
            <button type="button" class="r402-filter-trigger" id="r402FilterTrigger" aria-haspopup="true" aria-expanded="false" aria-controls="r402FilterMenu">
                <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" aria-hidden="true"><path d="M2 4.5h12M4.5 8h7M7 11.5h2"/></svg>
                Add Filter
            </button>

            <div class="r402-filter-menu" id="r402FilterMenu" hidden>
                <div class="r402-filter-head">
                    <input type="text" class="r402-filter-search" id="r402FilterSearch" placeholder="Search Purok…" autocomplete="off" aria-label="Search Purok">
                </div>

                <ul class="r402-filter-list" role="group" aria-label="Purok">
                    <li data-label="All Purok">
                        <button type="button" class="r402-filter-row r402-filter-option" role="checkbox" aria-checked="false" data-purok="all">
                            <span>All Purok</span>
                            <svg class="r402-filter-check" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m3.5 8.5 3 3 6-7"/></svg>
                        </button>
                    </li>
                    @foreach ($puroks as $purok)
                        @php($optionLabel = $purok->purok_name ?: 'Purok '.$purok->purok_number)
                        <li data-label="{{ $optionLabel }}">
                            <button type="button" class="r402-filter-row r402-filter-option" role="checkbox" aria-checked="false" data-purok="{{ $purok->selection_token ?? $purok->purok_number }}">
                                <span>{{ $optionLabel }}</span>
                                <svg class="r402-filter-check" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m3.5 8.5 3 3 6-7"/></svg>
                            </button>
                        </li>
                    @endforeach
                </ul>
                <p class="r402-filter-none" id="r402FilterNone" hidden>No matches.</p>

                <div class="r402-filter-section">
                    <div class="r402-filter-label" id="r402KalagayanLabel">Kalagayan</div>
                    <div class="r402-filter-seg" role="group" aria-labelledby="r402KalagayanLabel">
                        <button type="button" class="r402-seg-btn" aria-pressed="false" data-status="MS">MS</button>
                        <button type="button" class="r402-seg-btn" aria-pressed="false" data-status="UWP">UWP</button>
                    </div>
                </div>

                <div class="r402-filter-foot">
                    <button type="button" class="r402-foot-clear" id="r402FilterClear">Clear</button>
                    <button type="button" class="r402-foot-apply" id="r402FilterApply" disabled>Apply</button>
                </div>
            </div>
        </div>

        @if ($hasPurok)
            <span class="r402-chip">
                <span class="r402-chip-label">Purok</span>
                <span class="r402-chip-value">{{ $purokChipValue }}</span>
                <button type="button" class="r402-chip-remove" data-clear="purok" aria-label="Remove Purok filter">
                    <svg viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" aria-hidden="true"><path d="m2.5 2.5 7 7M9.5 2.5l-7 7"/></svg>
                </button>
            </span>
        @endif
        @if ($hasStatus)
            <span class="r402-chip">
                <span class="r402-chip-label">Kalagayan</span>
                <span class="r402-chip-value">{{ $selectedStatus }}</span>
                <button type="button" class="r402-chip-remove" data-clear="kalagayan" aria-label="Remove Kalagayan filter">
                    <svg viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" aria-hidden="true"><path d="m2.5 2.5 7 7M9.5 2.5l-7 7"/></svg>
                </button>
            </span>
        @endif
        @if ($hasPurok || $hasStatus)
            <a class="r402-reset" href="{{ route('forms.weekly.r4-02') }}">Reset filters</a>
        @endif
    </form>

    @if (! $ready)
        <section class="r402-section">
            <p class="r402-note">Select a Purok and Kalagayan to list the R4-01 records.</p>
        </section>
    @elseif ($groups === [])
        <section class="r402-section">
            <p class="r402-note">No R4-01 records match this Purok and Kalagayan.</p>
        </section>
    @else
        <section class="r402-section">
            <div class="r402-section-title">
                <h2>Select records</h2>
                <span class="r402-muted" id="r402Total">None selected</span>
            </div>
            <div class="r402-quick">
                <label for="r402Quick">Row numbers
                    <input class="r402-input" id="r402Quick" type="text" autocomplete="off" placeholder="1-10, 15 or 1,3,5-8">
                </label>
                <div class="r402-quick-meta">
                    <button type="button" class="r402-link-btn" id="r402QuickClear">Clear selection</button>
                </div>
                <p class="r402-quick-note">Typing row numbers selects those rows in every Purok shown. Click a row or its checkbox to adjust a single Purok. <span id="r402QuickNote"></span></p>
            </div>
        </section>

        <section class="r402-section">
            <div class="r402-section-title">
                <h2>R4-01 records · {{ $selectedStatus }}</h2>
                <span class="r402-muted">{{ count($groups) }} {{ \Illuminate\Support\Str::plural('Purok', count($groups)) }}</span>
            </div>
            @foreach ($groups as $gi => $group)
                <div class="r402-group" data-group="{{ $gi }}">
                    <div class="r402-section-title">
                        <h2 style="font-size:.9rem">{{ $group['name'] }}</h2>
                        <span class="r402-muted"><span>{{ count($group['rows']) }} rows</span> · <span data-count>0 selected</span></span>
                    </div>
                    <div class="r402-table-wrap">
                        <table class="r402-table r402-records-table">
                            <thead>
                                <tr>
                                    <th class="r402-check"><input type="checkbox" data-all aria-label="Select all rows in {{ $group['name'] }}"></th>
                                    <th>No.</th><th>R4-01 Code</th><th>Name</th><th>Registry</th><th>Purok-Grupo</th><th>Kapisanan</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($group['rows'] as $ri => $row)
                                    <tr data-row="{{ $ri + 1 }}" data-key="{{ $row['row_key'] ?? '' }}">
                                        <td class="r402-check"><input type="checkbox" aria-label="Select row {{ $ri + 1 }}"></td>
                                        <td>{{ $ri + 1 }}</td>
                                        <td>{{ $row['code'] }}</td>
                                        <td>{{ $row['name'] }}</td>
                                        <td>{{ $row['registry'] }}</td>
                                        <td>{{ $row['purok_grupo'] }}</td>
                                        <td>{{ $row['kapisanan'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach
        </section>

        <section class="r402-section">
            <div class="r402-section-title">
                <h2>What you're generating</h2>
                <span class="r402-muted">Each Purok downloads separately.</span>
            </div>
            @if ($hakbangList->isEmpty())
                <p class="r402-error" style="margin:0 0 .8rem">No Hakbang codes are configured for {{ $selectedStatus }}. Ask an administrator to add them in Admin Portal → Mga R4-02 Hakbang.</p>
            @endif
            <div class="r402-summary-wrap">
                <table class="r402-table r402-summary-table">
                    <thead>
                        <tr><th>Purok</th><th>Record</th><th>Code</th><th>Date of Dalaw</th><th>Date of Ulat</th><th>Download</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($groups as $gi => $group)
                            <tr data-summary="{{ $gi }}">
                                <td>{{ $group['name'] }}</td>
                                <td class="r402-records-cell" data-records>—</td>
                                <td>
                                    @if ($hakbangList->isEmpty())
                                        <span class="r402-muted">Not configured</span>
                                    @else
                                        <select class="r402-input" name="hakbang_code" form="r402Gen{{ $gi }}" data-code required aria-label="Hakbang code for {{ $group['name'] }}">
                                            <option value="">Select code</option>
                                            @foreach ($hakbangList as $hakbang)
                                                <option value="{{ $hakbang->code }}" title="{{ $hakbang->description }}">{{ $hakbang->code }} — {{ $hakbang->description }}</option>
                                            @endforeach
                                        </select>
                                        <label class="r402-split">
                                            <input type="checkbox" name="split_codes" value="1" form="r402Gen{{ $gi }}" data-split>
                                            Different codes by row numbers
                                        </label>
                                        <input class="r402-input r402-assign" type="text" name="code_assignments" form="r402Gen{{ $gi }}" data-assign placeholder="1-3=1-7;5,7=1-8" aria-label="Row-to-code assignments for {{ $group['name'] }}" hidden>
                                    @endif
                                </td>
                                <td><input class="r402-input" type="date" name="date_of_dalaw" form="r402Gen{{ $gi }}" aria-label="Date of Dalaw for {{ $group['name'] }}"></td>
                                <td><input class="r402-input" type="date" name="date_of_ulat" form="r402Gen{{ $gi }}" aria-label="Date of Ulat for {{ $group['name'] }}"></td>
                                <td>
                                    <div class="r402-actions">
                                        <button type="submit" class="r402-btn primary" form="r402Gen{{ $gi }}" name="output" value="r4-02" data-output disabled>Download R4-02 DOCX</button>
                                        <button type="submit" class="r402-btn" form="r402Gen{{ $gi }}" name="output" value="r4-03" data-output disabled>Download R4-03 DOCX</button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="r402-forms">
                @foreach ($groups as $gi => $group)
                    <form id="r402Gen{{ $gi }}" method="POST" action="{{ route('forms.weekly.r4-02', ['purok' => $group['number'], 'kalagayan' => $selectedStatus]) }}">
                        @csrf
                        <input type="hidden" name="purok" value="{{ $group['number'] }}">
                        <input type="hidden" name="kalagayan" value="{{ $selectedStatus }}">
                        <input type="hidden" name="selection" value="" data-selection>
                        <input type="hidden" name="row_keys" value="" data-row-keys>
                    </form>
                @endforeach
            </div>
        </section>
    @endif
</main>

<script>
    (() => {
        const form = document.getElementById('r402Filters');
        const trigger = document.getElementById('r402FilterTrigger');
        const menu = document.getElementById('r402FilterMenu');
        const search = document.getElementById('r402FilterSearch');
        const none = document.getElementById('r402FilterNone');
        const apply = document.getElementById('r402FilterApply');
        const purokInput = document.getElementById('r402PurokInput');
        const statusInput = document.getElementById('r402KalagayanInput');
        const options = menu.querySelectorAll('[data-purok]');
        const segButtons = menu.querySelectorAll('[data-status]');
        let pending = new Set();
        let status = '';

        const render = () => {
            options.forEach((option) => {
                option.setAttribute('aria-checked', pending.has(option.dataset.purok) ? 'true' : 'false');
            });
            segButtons.forEach((button) => {
                button.setAttribute('aria-pressed', button.dataset.status === status ? 'true' : 'false');
            });
            apply.disabled = pending.size === 0 || status === '';
        };

        const syncFromInputs = () => {
            pending = new Set(purokInput.value.split(',').filter(Boolean));
            status = statusInput.value;
            render();
        };

        const applySearch = () => {
            const query = search.value.trim().toLowerCase();
            let visible = 0;
            menu.querySelectorAll('li[data-label]').forEach((item) => {
                const match = item.dataset.label.toLowerCase().includes(query);
                item.hidden = !match;
                if (match) visible++;
            });
            none.hidden = visible > 0;
        };

        const place = () => {
            menu.classList.remove('r402-filter-menu--up');
            const menuRect = menu.getBoundingClientRect();
            if (menuRect.bottom <= window.innerHeight - 8) return;
            const spaceAbove = trigger.getBoundingClientRect().top - 8;
            if (spaceAbove >= menuRect.height) {
                menu.classList.add('r402-filter-menu--up');
            } else {
                window.scrollBy({ top: menuRect.bottom - window.innerHeight + 16, behavior: 'smooth' });
            }
        };

        const openMenu = () => {
            menu.hidden = false;
            trigger.setAttribute('aria-expanded', 'true');
            search.value = '';
            applySearch();
            syncFromInputs();
            place();
            search.focus({ preventScroll: true });
        };

        const closeMenu = () => {
            menu.hidden = true;
            trigger.setAttribute('aria-expanded', 'false');
        };

        trigger.addEventListener('click', () => (menu.hidden ? openMenu() : closeMenu()));
        search.addEventListener('input', applySearch);

        options.forEach((option) => {
            option.addEventListener('click', () => {
                const value = option.dataset.purok;
                if (value === 'all') {
                    const wasOn = pending.has('all');
                    pending.clear();
                    if (!wasOn) pending.add('all');
                } else {
                    pending.delete('all');
                    if (pending.has(value)) pending.delete(value); else pending.add(value);
                }
                render();
            });
        });

        segButtons.forEach((button) => {
            button.addEventListener('click', () => {
                status = status === button.dataset.status ? '' : button.dataset.status;
                render();
            });
        });

        document.getElementById('r402FilterClear').addEventListener('click', () => {
            pending.clear();
            status = '';
            render();
        });

        apply.addEventListener('click', () => {
            if (apply.disabled) return;
            purokInput.value = Array.from(pending).join(',');
            statusInput.value = status;
            form.requestSubmit();
        });

        form.querySelectorAll('[data-clear]').forEach((button) => {
            button.addEventListener('click', () => {
                (button.dataset.clear === 'purok' ? purokInput : statusInput).value = '';
                form.requestSubmit();
            });
        });

        document.addEventListener('click', (event) => {
            if (!menu.hidden && !event.target.closest('.r402-filter-add')) closeMenu();
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && !menu.hidden) {
                closeMenu();
                trigger.focus();
            }
        });
    })();

    (() => {
        const groupEls = Array.from(document.querySelectorAll('[data-group]'));
        if (!groupEls.length) return;

        const quick = document.getElementById('r402Quick');
        const quickNote = document.getElementById('r402QuickNote');
        const totalEl = document.getElementById('r402Total');
        const state = groupEls.map(() => new Set());

        const groups = groupEls.map((el, i) => {
            const summary = document.querySelector('[data-summary="' + i + '"]');
            const genForm = document.getElementById('r402Gen' + i);
            return {
                el,
                rows: Array.from(el.querySelectorAll('[data-row]')),
                all: el.querySelector('[data-all]'),
                count: el.querySelector('[data-count]'),
                records: summary.querySelector('[data-records]'),
                selection: genForm.querySelector('[data-selection]'),
                rowKeys: genForm.querySelector('[data-row-keys]'),
                outputs: Array.from(summary.querySelectorAll('[data-output]')),
                code: summary.querySelector('[data-code]'),
                split: summary.querySelector('[data-split]'),
                assign: summary.querySelector('[data-assign]'),
            };
        });

        const compress = (set) => {
            const nums = Array.from(set).sort((a, b) => a - b);
            const parts = [];
            let start = null;
            let prev = null;
            nums.forEach((n) => {
                if (start === null) { start = prev = n; return; }
                if (n === prev + 1) { prev = n; return; }
                parts.push(start === prev ? String(start) : start + '-' + prev);
                start = prev = n;
            });
            if (start !== null) parts.push(start === prev ? String(start) : start + '-' + prev);
            return parts;
        };

        const parse = (text) => {
            const nums = new Set();
            const bad = [];
            text.split(/[,;\s]+/).filter(Boolean).forEach((token) => {
                const match = token.match(/^(\d+)(?:-(\d+))?$/);
                if (!match || Number(match[1]) < 1) { bad.push(token); return; }
                let from = Number(match[1]);
                let to = match[2] ? Number(match[2]) : from;
                if (from > to) [from, to] = [to, from];
                if (to - from > 5000) { bad.push(token); return; }
                for (let n = from; n <= to; n++) nums.add(n);
            });
            return { nums, bad };
        };

        const render = () => {
            let total = 0;
            groups.forEach((g, i) => {
                const set = state[i];
                total += set.size;
                g.rows.forEach((row) => {
                    const on = set.has(Number(row.dataset.row));
                    row.classList.toggle('is-selected', on);
                    row.querySelector('input').checked = on;
                });
                g.all.checked = g.rows.length > 0 && set.size === g.rows.length;
                g.all.indeterminate = set.size > 0 && set.size < g.rows.length;
                g.count.textContent = set.size + ' selected';
                const parts = compress(set);
                g.records.textContent = parts.length ? parts.join(', ') : '—';
                g.selection.value = parts.join(',');
                // The server checks each number still names the member shown here before saving.
                g.rowKeys.value = g.rows
                    .filter((row) => set.has(Number(row.dataset.row)))
                    .map((row) => row.dataset.row + ':' + row.dataset.key)
                    .join(',');
                g.outputs.forEach((button) => { button.disabled = set.size === 0 || !g.code; });
            });
            totalEl.textContent = total ? total + ' selected' : 'None selected';
        };

        quick.addEventListener('input', () => {
            const { nums, bad } = parse(quick.value);
            groups.forEach((g, i) => {
                state[i] = new Set(Array.from(nums).filter((n) => n <= g.rows.length));
            });
            quickNote.textContent = bad.length ? 'Ignored: ' + bad.join(', ') : '';
            render();
        });

        document.getElementById('r402QuickClear').addEventListener('click', () => {
            state.forEach((set) => set.clear());
            quick.value = '';
            quickNote.textContent = '';
            render();
        });

        groups.forEach((g, i) => {
            const tbody = g.el.querySelector('tbody');
            tbody.addEventListener('click', (event) => {
                const row = event.target.closest('[data-row]');
                if (!row || event.target.closest('input')) return;
                const n = Number(row.dataset.row);
                if (state[i].has(n)) state[i].delete(n); else state[i].add(n);
                render();
            });
            tbody.addEventListener('change', (event) => {
                const row = event.target.closest('[data-row]');
                if (!row) return;
                const n = Number(row.dataset.row);
                if (event.target.checked) state[i].add(n); else state[i].delete(n);
                render();
            });
            g.all.addEventListener('change', () => {
                state[i] = g.all.checked ? new Set(g.rows.map((row) => Number(row.dataset.row))) : new Set();
                render();
            });
            if (g.split && g.code && g.assign) {
                g.split.addEventListener('change', () => {
                    g.assign.hidden = !g.split.checked;
                    g.code.required = !g.split.checked;
                    g.assign.required = g.split.checked;
                });
            }
        });

        render();
    })();
</script>
@endsection