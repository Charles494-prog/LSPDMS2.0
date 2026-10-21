<style>
    .r401-page { width:100%; max-width:1500px; margin:0 auto; font-family:Tahoma,Arial,sans-serif; font-weight:700; }
    .r401-tools { display:flex; flex-wrap:wrap; align-items:flex-end; gap:.65rem; margin-bottom:1rem; padding:.85rem; border:1px solid var(--border); border-radius:12px; background:var(--surface); }
    .r401-tools label { display:grid; gap:.3rem; min-width:150px; color:var(--text-muted); font-size:.76rem; font-weight:700; }
    .r401-tools input,.r401-tools select { min-height:36px; padding:.4rem .5rem; border:1px solid var(--border); border-radius:7px; background:var(--bg); color:var(--text); font:700 .82rem Tahoma,Arial,sans-serif; }
    .r401-tools button,.r401-tools a { min-height:36px; padding:.5rem .75rem; border:0; border-radius:7px; background:var(--accent); color:#fff; font:700 .8rem Tahoma,Arial,sans-serif; text-decoration:none; cursor:pointer; }
    .r401-tools button:disabled,.r401-tools a[aria-disabled="true"] { background:#9ca3af; cursor:not-allowed; opacity:.7; }
    .r401-empty-note { flex:1 1 100%; margin:0; color:var(--text-muted); font-size:.79rem; }
    .r401-empty-note { padding:.75rem; border:1px solid #f4c982; border-radius:8px; background:#fff8e8; color:#7a4b00; }
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
        .r401-page-sheet { min-width:0; padding:0; font-size:6px; }
        .r401-page-sheet table { font-size:6px; }
        .r401-page-sheet th,.r401-page-sheet td { padding:1px 0; }
        .r401-meta-table td { height:19px; }
        .r401-report-table th { height:26px; }
        .r401-report-table td { height:20px; }
        .r401-signature-name { font-size:9px; }
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
    <form class="r401-tools" id="r401Filters" method="GET" action="{{ route(($embedded ?? false) ? 'forms.monthly.generators' : 'forms.monthly.r4-01') }}">
        @if ($embedded ?? false)<input type="hidden" name="month" value="{{ $selectedMonth }}">@endif
        <label for="r401Purok">Purok
            <select id="r401Purok" name="purok">
                <option value="">Select Purok</option>
                <option value="all" @selected($selectedPurok === 'all')>All Purok</option>
                @foreach ($puroks as $purok)
                    <option value="{{ $purok->purok_number }}" @selected($selectedPurok === (string) $purok->purok_number)>
                        {{ $purok->purok_name ?: 'Purok '.$purok->purok_number }}
                    </option>
                @endforeach
            </select>
        </label>
        <label for="r401Kalagayan">Kalagayan
            <select id="r401Kalagayan" name="kalagayan">
                <option value="">Select Kalagayan</option>
                <option value="MS" @selected($selectedStatus === 'MS')>MS</option>
                <option value="UWP" @selected($selectedStatus === 'UWP')>UWP</option>
            </select>
        </label>
        @if ($selectedPurok !== null && $selectedStatus !== null && $reportRows !== [])
            <a href="{{ route('forms.monthly.r4-01.download', ['purok' => $selectedPurok, 'kalagayan' => $selectedStatus]) }}">Download DOCX</a>
        @else
            <button type="button" disabled>Download DOCX</button>
        @endif
    </form>

    @if ($selectedPurok === null || $selectedStatus === null)
        <p class="r401-empty-note">Select a Purok and Kalagayan, then generate the report preview.</p>
    @elseif ($reportRows === [])
        <p class="r401-empty-note">No {{ $selectedStatus }} records were found in the selected Purok.</p>
    @endif
    @endunless

    <section class="r401-pages" aria-label="R4-01 report preview">
        @foreach ($reportPages as $pageData)
            <article class="r401-page-sheet">
                <table class="r401-title-table" aria-label="R4-01 report title">
                    <colgroup><col style="width:24%"><col style="width:52%"><col style="width:10%"><col style="width:14%"></colgroup>
                    <tbody><tr>
                        <td class="r401-form-ident">Records FormR4-01</td>
                        <td class="r401-form-title">ULAT UKOL SA SAKOP NA MS / UWP</td>
                        <td class="r401-status-option"><span class="r401-status-box">{{ $selectedStatus === 'MS' ? '✓' : '' }}</span>MS</td>
                        <td class="r401-status-option"><span class="r401-status-box">{{ $selectedStatus === 'UWP' ? '✓' : '' }}</span>UWP</td>
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
    document.querySelectorAll('#r401Filters select').forEach((select) => {
        select.addEventListener('change', () => document.getElementById('r401Filters').requestSubmit());
    });
</script>
@endunless
