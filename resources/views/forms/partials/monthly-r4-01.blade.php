<style>
    .r401-page { width:100%; max-width:1500px; margin:0 auto; font-family:Tahoma,Arial,sans-serif; font-weight:700; }
    .r401-tools { display:flex; flex-wrap:wrap; align-items:center; gap:.65rem; margin-bottom:1rem; font-weight:500; }
    .r401-tools * { font-family:Tahoma,Arial,sans-serif; }
    .r401-filter-add { position:relative; }
    .r401-filter-trigger { display:inline-flex; align-items:center; gap:.5rem; min-height:36px; padding:0 .95rem; border:1px solid var(--border); border-radius:999px; background:transparent; color:var(--text); font-size:.82rem; font-weight:700; cursor:pointer; transition:background-color .12s,border-color .12s; }
    .r401-filter-trigger:hover,.r401-filter-trigger[aria-expanded="true"] { background:color-mix(in srgb,var(--text) 6%,var(--bg)); border-color:color-mix(in srgb,var(--text) 45%,var(--border)); }
    .r401-filter-trigger svg { width:15px; height:15px; flex:none; }
    .r401-filter-menu { position:absolute; top:calc(100% + 6px); bottom:auto; left:0; z-index:30; width:320px; max-width:calc(100vw - 2rem); border:1px solid var(--border); border-radius:12px; background:var(--surface); color:var(--text); overflow:hidden; }
    .r401-filter-menu--up { top:auto; bottom:calc(100% + 6px); }
    .r401-filter-menu[hidden] { display:none; }
    .r401-filter-head { border-bottom:1px solid var(--text); }
    .r401-filter-search { display:block; width:100%; min-height:44px; margin:0; padding:.65rem 1rem; border:0!important; border-radius:0; background:transparent!important; box-shadow:none!important; outline:none!important; color:var(--text); font-size:.85rem; font-weight:500; }
    .r401-filter-search::placeholder { color:var(--text-muted); }
    .r401-filter-label { padding:.6rem 1rem .25rem; color:var(--text-muted); font-size:.72rem; font-weight:700; letter-spacing:.04em; text-transform:uppercase; }
    .r401-filter-list { max-height:220px; margin:0; padding:.35rem; overflow-y:auto; list-style:none; }
    .r401-filter-list li[hidden] { display:none; }
    .r401-filter-row { display:flex; align-items:center; justify-content:space-between; gap:.75rem; width:100%; min-height:38px; padding:.45rem .65rem; border:0; border-radius:8px; background:transparent; color:var(--text); font-size:.85rem; font-weight:500; text-align:left; cursor:pointer; }
    .r401-filter-row:hover,.r401-filter-row:focus-visible { background:color-mix(in srgb,var(--text) 8%,var(--bg)); outline:none; }
    .r401-filter-option[aria-checked="true"] { font-weight:700; }
    .r401-filter-check { width:14px; height:14px; flex:none; opacity:0; }
    .r401-filter-option[aria-checked="true"] .r401-filter-check { opacity:1; color:var(--text); }
    .r401-filter-none { margin:0; padding:.65rem 1rem; color:var(--text-muted); font-size:.8rem; }
    .r401-filter-section { border-top:1px solid var(--border); }
    .r401-filter-seg { display:flex; gap:.5rem; padding:.35rem 1rem .8rem; }
    .r401-seg-btn { flex:1; min-height:34px; border:1px solid var(--border); border-radius:8px; background:transparent; color:var(--text); font-size:.82rem; font-weight:700; cursor:pointer; transition:background-color .12s,color .12s,border-color .12s; }
    .r401-seg-btn:hover { background:color-mix(in srgb,var(--text) 8%,var(--bg)); }
    .r401-seg-btn[aria-pressed="true"] { border-color:var(--text); background:var(--text); color:var(--bg); }
    .r401-filter-foot { display:flex; align-items:center; justify-content:space-between; gap:.75rem; padding:.6rem 1rem; border-top:1px solid var(--border); }
    .r401-foot-clear { padding:0; border:0; background:transparent; color:var(--text-muted); font-size:.8rem; font-weight:500; text-decoration:underline; text-underline-offset:3px; cursor:pointer; }
    .r401-foot-clear:hover { color:var(--text); }
    .r401-foot-apply { min-height:32px; padding:0 .95rem; border:1px solid var(--text); border-radius:999px; background:var(--text); color:var(--bg); font-size:.8rem; font-weight:700; cursor:pointer; transition:opacity .12s; }
    .r401-foot-apply:hover:not(:disabled) { opacity:.85; }
    .r401-foot-apply:disabled { border-color:var(--border); background:transparent; color:var(--text-muted); cursor:not-allowed; opacity:.55; }
    .r401-chip { display:inline-flex; align-items:center; gap:.45rem; min-height:36px; padding:0 .4rem 0 .85rem; border:1px dashed color-mix(in srgb,var(--text) 45%,var(--border)); border-radius:999px; color:var(--text); font-size:.82rem; }
    .r401-chip-label { color:var(--text-muted); font-weight:500; }
    .r401-chip-value { font-weight:700; }
    .r401-chip-remove { display:inline-flex; align-items:center; justify-content:center; width:24px; height:24px; padding:0; border:0; border-radius:50%; background:transparent; color:var(--text-muted); cursor:pointer; }
    .r401-chip-remove:hover { background:color-mix(in srgb,var(--text) 10%,var(--bg)); color:var(--text); }
    .r401-chip-remove svg { width:12px; height:12px; }
    .r401-reset { color:var(--text); font-size:.82rem; font-weight:500; text-decoration:underline; text-underline-offset:3px; }
    .r401-reset:hover { color:var(--text-muted); }
    .r401-download { display:inline-flex; align-items:center; justify-content:center; min-height:36px; margin-left:auto; padding:0 .95rem; border:1px solid var(--text); border-radius:999px; background:var(--text); color:var(--bg); font-size:.8rem; font-weight:700; text-decoration:none; cursor:pointer; transition:opacity .12s; }
    .r401-download:hover { opacity:.85; }
    .r401-download:disabled,.r401-download[aria-disabled="true"] { border-color:var(--border); background:transparent; color:var(--text-muted); cursor:not-allowed; opacity:.7; }
    .r401-tools button:focus-visible,.r401-tools a:focus-visible { outline:2px solid color-mix(in srgb,var(--text) 55%,transparent); outline-offset:2px; }
    .r401-empty-note { margin:0 0 1rem; padding:.7rem 0; border-top:1px solid var(--border); border-bottom:1px solid var(--border); color:var(--text-muted); font-size:.79rem; font-weight:500; }
    .r401-pages { display:grid; gap:1.3rem; }
    .r401-page-sheet { width:100%; padding:.25rem; background:#fff; color:#111; page-break-after:always; break-after:page; font:700 8px Tahoma,Arial,sans-serif; }
    .r401-page-sheet:last-child { page-break-after:auto; break-after:auto; }
    .r401-page-sheet + .r401-page-sheet { page-break-before:always; break-before:page; }
    .r401-page-sheet table { width:100%; border-collapse:collapse; table-layout:fixed; color:#111; font:700 8px Tahoma,Arial,sans-serif; }
    .r401-page-sheet th,.r401-page-sheet td { padding:2px 1px; border:1px solid #111; text-align:center; vertical-align:middle; overflow-wrap:anywhere; }
    .r401-title-table { margin-bottom:5px; }
    .r401-title-table td { height:28px; }
    .r401-form-title { font-size:13px; }
    .r401-form-ident { font-size:8px; text-align:left!important; }
    .r401-status-option { white-space:nowrap; }
    .r401-status-box { display:inline-flex; width:11px; height:11px; margin-right:4px; align-items:center; justify-content:center; border:1px solid #111; vertical-align:middle; font:700 9px/1 Tahoma,Arial,sans-serif; }
    .r401-meta-table { margin-bottom:5px; }
    .r401-meta-table td { height:22px; text-align:left; }
    .r401-meta-table .r401-meta-label { text-align:center; }
    .r401-month-value { font-size:10px; text-transform:uppercase; }
    .r401-report-table { width:100%; }
    .r401-report-table th { height:31px; text-align:center; vertical-align:middle; }
    .r401-report-table td { height:23px; }
    .r401-report-table tbody tr.r401-movement-row td:nth-child(2) { color:#c62828!important; }
    .r401-report-table td:nth-child(3) { text-align:left; padding-left:4px; }
    .r401-report-table td:nth-child(1),.r401-report-table td:nth-child(2),.r401-report-table td:nth-child(n+4):nth-child(-n+16),.r401-report-table td:nth-child(17),.r401-report-table td:nth-child(18),.r401-report-table td:nth-child(19),.r401-report-table td:nth-child(20) { text-align:center; }
    .r401-signature-table { margin-top:9px; }
    .r401-signature-table td { height:55px; text-align:center; }
    .r401-signature-name { display:block; margin-bottom:4px; font-size:13px; line-height:1.15; text-transform:uppercase; }
    .r401-reference { margin-top:1rem; }
    .r401-reference summary { color:var(--accent); cursor:pointer; font-weight:700; }
    .r401-reference img { display:block; width:min(100%,1200px); height:auto; margin-top:.5rem; }
    @media(max-width:680px) {
        .r401-download { margin-left:0; }
        .r401-page-sheet { min-width:0; padding:0; font-size:6px; }
        .r401-page-sheet table { font-size:6px; }
        .r401-page-sheet th,.r401-page-sheet td { padding:1px 0; }
        .r401-meta-table td { height:19px; }
        .r401-report-table th { height:26px; }
        .r401-report-table td { height:20px; }
        .r401-signature-name { font-size:9px; }
    }
    @media (prefers-reduced-motion:reduce) {
        .r401-filter-trigger,.r401-download,.r401-seg-btn,.r401-foot-apply { transition:none; }
    }
    @media print {
        @page { size:landscape; margin:.25in; }
        body { background:#fff!important; }
        .sidebar,.topbar,.overlay,.r401-tools,.r401-empty-note,.r401-reference { display:none!important; }
        .main-area,.content { width:100%!important; margin:0!important; padding:0!important; }
        .r401-page { max-width:none; }
        .r401-pages { display:block; }
        .r401-page-sheet { width:100%; padding:0; }
        .r401-page-sheet table { font-size:7pt; }
        .r401-page-sheet th,.r401-page-sheet td { padding:1px; }
        .r401-report-table th { height:29px; }
        .r401-report-table td { height:21px; }
        .r401-report-table tbody tr.r401-movement-row td:nth-child(2) { color:#c62828!important; }
    }
</style>

<div class="r401-page">
    @unless($pdf ?? false)
    @php
        $selectedPurokRaw = is_array($selectedPurok) ? implode(',', $selectedPurok) : (string) ($selectedPurok ?? '');
        $selectedValues = array_values(array_filter(explode(',', $selectedPurokRaw), fn ($v) => $v !== ''));
        $hasPurok = $selectedValues !== [];

        $selectedStatusRaw = is_array($selectedStatus) ? implode(',', $selectedStatus) : (string) ($selectedStatus ?? '');
        $statusValues = array_values(array_intersect(['MS', 'UWP'], explode(',', $selectedStatusRaw)));
        $selectedStatusRaw = implode(',', $statusValues);
        $hasStatus = $statusValues !== [];

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
        $statusChipValue = implode(', ', $statusValues);
        $resetUrl = route(($embedded ?? false) ? 'forms.monthly.generators' : 'forms.monthly.r4-01', ($embedded ?? false) ? ['month' => $selectedMonth] : []);
    @endphp
    <form class="r401-tools" id="r401Filters" method="GET" action="{{ route(($embedded ?? false) ? 'forms.monthly.generators' : 'forms.monthly.r4-01') }}">
        @if ($embedded ?? false)<input type="hidden" name="month" value="{{ $selectedMonth }}">@endif
        <input type="hidden" name="purok" id="r401PurokInput" value="{{ $selectedPurokRaw }}">
        <input type="hidden" name="kalagayan" id="r401KalagayanInput" value="{{ $selectedStatusRaw }}">

        <div class="r401-filter-add">
            <button type="button" class="r401-filter-trigger" id="r401FilterTrigger" aria-haspopup="true" aria-expanded="false" aria-controls="r401FilterMenu">
                <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" aria-hidden="true"><path d="M2 4.5h12M4.5 8h7M7 11.5h2"/></svg>
                Add Filter
            </button>

            <div class="r401-filter-menu" id="r401FilterMenu" hidden>
                <div class="r401-filter-head">
                    <input type="text" class="r401-filter-search" id="r401FilterSearch" placeholder="Search Purok…" autocomplete="off" aria-label="Search Purok">
                </div>

                <ul class="r401-filter-list" role="group" aria-label="Purok">
                    <li data-label="All Purok">
                        <button type="button" class="r401-filter-row r401-filter-option" role="checkbox" aria-checked="false" data-purok="all">
                            <span>All Purok</span>
                            <svg class="r401-filter-check" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m3.5 8.5 3 3 6-7"/></svg>
                        </button>
                    </li>
                    @foreach ($puroks as $purok)
                        @php($optionLabel = $purok->purok_name ?: 'Purok '.$purok->purok_number)
                        <li data-label="{{ $optionLabel }}">
                            <button type="button" class="r401-filter-row r401-filter-option" role="checkbox" aria-checked="false" data-purok="{{ $purok->selection_token ?? $purok->purok_number }}">
                                <span>{{ $optionLabel }}</span>
                                <svg class="r401-filter-check" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m3.5 8.5 3 3 6-7"/></svg>
                            </button>
                        </li>
                    @endforeach
                </ul>
                <p class="r401-filter-none" id="r401FilterNone" hidden>No matches.</p>

                <div class="r401-filter-section">
                    <div class="r401-filter-label" id="r401KalagayanLabel">Kalagayan</div>
                    <div class="r401-filter-seg" role="group" aria-labelledby="r401KalagayanLabel">
                        <button type="button" class="r401-seg-btn" aria-pressed="false" data-status="MS">MS</button>
                        <button type="button" class="r401-seg-btn" aria-pressed="false" data-status="UWP">UWP</button>
                    </div>
                </div>

                <div class="r401-filter-foot">
                    <button type="button" class="r401-foot-clear" id="r401FilterClear">Clear</button>
                    <button type="button" class="r401-foot-apply" id="r401FilterApply" disabled>Apply</button>
                </div>
            </div>
        </div>

        @if ($hasPurok)
            <span class="r401-chip">
                <span class="r401-chip-label">Purok</span>
                <span class="r401-chip-value">{{ $purokChipValue }}</span>
                <button type="button" class="r401-chip-remove" data-clear="purok" aria-label="Remove Purok filter">
                    <svg viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" aria-hidden="true"><path d="m2.5 2.5 7 7M9.5 2.5l-7 7"/></svg>
                </button>
            </span>
        @endif
        @if ($hasStatus)
            <span class="r401-chip">
                <span class="r401-chip-label">Kalagayan</span>
                <span class="r401-chip-value">{{ $statusChipValue }}</span>
                <button type="button" class="r401-chip-remove" data-clear="kalagayan" aria-label="Remove Kalagayan filter">
                    <svg viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" aria-hidden="true"><path d="m2.5 2.5 7 7M9.5 2.5l-7 7"/></svg>
                </button>
            </span>
        @endif
        @if ($hasPurok || $hasStatus)
            <a class="r401-reset" href="{{ $resetUrl }}">Reset filters</a>
        @endif

        @if ($hasPurok && $hasStatus && $reportRows !== [])
            <a class="r401-download" href="{{ route('forms.monthly.r4-01.download', ['purok' => $selectedPurokRaw, 'kalagayan' => $selectedStatusRaw]) }}">Download DOCX</a>
        @else
            <button type="button" class="r401-download" disabled>Download DOCX</button>
        @endif
    </form>

    @if (! $hasPurok || ! $hasStatus)
        <p class="r401-empty-note">Select a Purok and Kalagayan, then generate the report preview.</p>
    @elseif ($reportRows === [])
        <p class="r401-empty-note">No {{ $statusChipValue }} records were found in the selected Purok.</p>
    @endif
    @foreach ($reportWarnings ?? [] as $reportWarning)
        <p class="r401-empty-note" role="status" data-r401-warning>{{ $reportWarning }}</p>
    @endforeach
    @endunless

    @php($tickedStatuses = array_values(array_intersect(['MS', 'UWP'], explode(',', is_array($selectedStatus ?? null) ? implode(',', $selectedStatus) : (string) ($selectedStatus ?? '')))))
    <section class="r401-pages" aria-label="R4-01 report preview">
        @foreach ($reportPages as $pageData)
            <article class="r401-page-sheet">
                <table class="r401-title-table" aria-label="R4-01 report title">
                    <colgroup><col style="width:24%"><col style="width:52%"><col style="width:10%"><col style="width:14%"></colgroup>
                    <tbody><tr>
                        <td class="r401-form-ident">Records FormR4-01</td>
                        <td class="r401-form-title">ULAT UKOL SA SAKOP NA MS / UWP</td>
                        <td class="r401-status-option"><span class="r401-status-box">{{ in_array('MS', $tickedStatuses, true) ? '✓' : '' }}</span>MS</td>
                        <td class="r401-status-option"><span class="r401-status-box">{{ in_array('UWP', $tickedStatuses, true) ? '✓' : '' }}</span>UWP</td>
                    </tr></tbody>
                </table>

                <table class="r401-meta-table" aria-label="R4-01 report details">
                    <colgroup>
                        <col style="width:27.19%"><col style="width:9.75%"><col style="width:8.08%"><col style="width:28.11%"><col style="width:8.96%"><col style="width:1.12%"><col style="width:9.79%"><col style="width:7%">
                    </colgroup>
                    <tbody>
                    <tr>
                        <td>DISTRITO: {{ $district }}</td>
                        <td>Dcode: {{ $dcode }}</td>
                        <td class="r401-meta-label">MAYSAKOP</td>
                        <td>PANGALAN: {{ $pageData['maysakop_name'] }}</td>
                        <td>Assigned No.: {{ $pageData['assigned_number'] }}</td>
                        <td></td>
                        <td class="r401-meta-label">Buwan</td>
                        <td class="r401-meta-label">Taon</td>
                    </tr>
                    <tr>
                        <td>LOKAL: {{ $locale }}</td>
                        <td>Lcode: {{ $lcode }}</td>
                        <td></td>
                        <td colspan="2">TUNGKULIN: MANGGAGAWA PAMUNUAN</td>
                        <td></td>
                        <td class="r401-meta-label r401-month-value">{{ mb_strtoupper($month->translatedFormat('F')) }}</td>
                        <td class="r401-meta-label r401-month-value">{{ $month->format('Y') }}</td>
                    </tr>
                    </tbody>
                </table>

                <table class="r401-report-table" aria-label="R4-01 member list">
                    <colgroup>
                        <col style="width:3.7%"><col style="width:3.7%"><col style="width:29.75%">
                        @for ($i = 0; $i < 13; $i++) <col style="width:1.605%"> @endfor
                        <col style="width:4.95%"><col style="width:6.05%"><col style="width:3.9%"><col style="width:8.95%"><col style="width:18.1%">
                    </colgroup>
                    <thead><tr>
                        <th>BLG</th><th>CODE</th><th>PANGALAN</th><th colspan="13">ID NUMBER</th>
                        <th>TAUHIN</th><th>PRK-GRP</th><th>KAP</th><th>PETSA NG BAUTISMO</th><th>NAGDOKTRINA</th>
                    </tr></thead>
                    <tbody>
                    @foreach ($pageData['rows'] as $index => $row)
                        @php($digits = str_split($row['registry']))
                        <tr @if($row['code'] !== 'A')class="r401-movement-row"@endif>
                            <td>{{ (($pageData['page_number'] - 1) * 20) + $index + 1 }}</td>
                            <td>{{ $row['code'] }}</td>
                            <td>{{ $row['name'] }}</td>
                            @for ($digit = 0; $digit < 13; $digit++)
                                <td>{{ $digits[$digit] ?? '' }}</td>
                            @endfor
                            <td>{{ $row['gender'] }}</td>
                            <td>{{ $row['purok_grupo'] }}</td>
                            <td>{{ $row['kapisanan'] }}</td>
                            <td>{{ $row['date_of_baptism'] }}</td>
                            <td>{{ $row['doctrinator'] }}</td>
                        </tr>
                    @endforeach
                    @for ($blank = count($pageData['rows']); $blank < 20; $blank++)
                        <tr>
                            <td></td><td></td><td></td>
                            @for ($digit = 0; $digit < 13; $digit++) <td></td> @endfor
                            <td></td><td></td><td></td><td></td><td></td>
                        </tr>
                    @endfor
                    </tbody>
                </table>
                <table class="r401-signature-table" aria-label="R4-01 signatures">
                    <colgroup><col style="width:24.7%"><col style="width:24.95%"><col style="width:24.65%"><col style="width:25.7%"></colgroup>
                    <tbody><tr>
                        <td><span class="r401-signature-name">{{ mb_strtoupper($pageData['kalihim_name'] ?? $kalihimName) }}</span>Kalihim</td>
                        <td><span class="r401-signature-name">{{ mb_strtoupper($globalVariables?->ksp_pd ?? '') }}</span>Pangulong Diakono / KSP</td>
                        <td><span class="r401-signature-name">{{ mb_strtoupper($pageData['maysakop_name']) }}</span>Maysakop</td>
                        <td><span class="r401-signature-name">{{ mb_strtoupper($globalVariables?->pastor_destinado ?? '') }}</span>Pastor / Destinado</td>
                    </tr></tbody>
                </table>
            </article>
        @endforeach
    </section>
</div>
@unless($pdf ?? false)
<script>
    (() => {
        const form = document.getElementById('r401Filters');
        const trigger = document.getElementById('r401FilterTrigger');
        const menu = document.getElementById('r401FilterMenu');
        const search = document.getElementById('r401FilterSearch');
        const none = document.getElementById('r401FilterNone');
        const apply = document.getElementById('r401FilterApply');
        const purokInput = document.getElementById('r401PurokInput');
        const statusInput = document.getElementById('r401KalagayanInput');
        const options = menu.querySelectorAll('[data-purok]');
        const segButtons = menu.querySelectorAll('[data-status]');
        const statusOrder = ['MS', 'UWP'];
        let pending = new Set();
        let statuses = new Set();

        const render = () => {
            options.forEach((option) => {
                option.setAttribute('aria-checked', pending.has(option.dataset.purok) ? 'true' : 'false');
            });
            segButtons.forEach((button) => {
                button.setAttribute('aria-pressed', statuses.has(button.dataset.status) ? 'true' : 'false');
            });
            apply.disabled = pending.size === 0 || statuses.size === 0;
        };

        const syncFromInputs = () => {
            pending = new Set(purokInput.value.split(',').filter(Boolean));
            statuses = new Set(statusInput.value.split(',').filter(Boolean));
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
            menu.classList.remove('r401-filter-menu--up');
            const menuRect = menu.getBoundingClientRect();
            if (menuRect.bottom <= window.innerHeight - 8) return;
            const spaceAbove = trigger.getBoundingClientRect().top - 8;
            if (spaceAbove >= menuRect.height) {
                menu.classList.add('r401-filter-menu--up');
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
                const value = button.dataset.status;
                if (statuses.has(value)) statuses.delete(value); else statuses.add(value);
                render();
            });
        });

        document.getElementById('r401FilterClear').addEventListener('click', () => {
            pending.clear();
            statuses.clear();
            render();
        });

        apply.addEventListener('click', () => {
            if (apply.disabled) return;
            purokInput.value = Array.from(pending).join(',');
            statusInput.value = statusOrder.filter((value) => statuses.has(value)).join(',');
            form.requestSubmit();
        });

        form.querySelectorAll('[data-clear]').forEach((button) => {
            button.addEventListener('click', () => {
                (button.dataset.clear === 'purok' ? purokInput : statusInput).value = '';
                form.requestSubmit();
            });
        });

        document.addEventListener('click', (event) => {
            if (!menu.hidden && !event.target.closest('.r401-filter-add')) closeMenu();
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && !menu.hidden) {
                closeMenu();
                trigger.focus();
            }
        });
    })();
</script>
@endunless