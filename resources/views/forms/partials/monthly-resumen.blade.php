<style>
    .resumen-page { max-width:1500px; margin:0 auto; font-family:Tahoma,Arial,sans-serif; font-weight:700; }
    .resumen-tools { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:.75rem 1.25rem; margin-bottom:1rem; padding:.7rem 0; border-top:1px solid var(--border); border-bottom:1px solid var(--border); font-weight:500; }
    .resumen-note { flex:1 1 420px; margin:0; color:var(--text-muted); font-family:Tahoma,Arial,sans-serif; font-size:.79rem; font-weight:500; line-height:1.5; }
    .resumen-download { display:inline-flex; align-items:center; justify-content:center; min-height:36px; padding:0 .95rem; border:1px solid var(--text); border-radius:999px; background:var(--text); color:var(--bg); font:700 .8rem Tahoma,Arial,sans-serif; text-decoration:none; cursor:pointer; transition:opacity .12s; }
    .resumen-download:hover { opacity:.85; }
    .resumen-download:focus-visible { outline:2px solid color-mix(in srgb,var(--text) 55%,transparent); outline-offset:2px; }
    .resumen-scroll { overflow-x:auto; padding:.4rem; border:1px solid var(--border); border-radius:4px; background:#fff; }
    .resumen-sheet { min-width:1320px; color:#111; font:700 8px Tahoma,Arial,sans-serif; }
    .resumen-sheet h1 { margin:0 0 .4rem; text-align:center; font-size:14px; }
    .resumen-table { width:100%; border-collapse:collapse; table-layout:fixed; color:#111; font:700 8px Tahoma,Arial,sans-serif; }
    .resumen-table th,.resumen-table td { padding:3px 2px; border:1px solid #111; text-align:center; vertical-align:middle; overflow-wrap:anywhere; }
    .resumen-table .left { text-align:left; }
    .resumen-table thead th { background:#f4f4f4; }
    .resumen-table .worker-col { width:145px; }
    .resumen-table .purok-col { width:65px; }
    .resumen-table .count-col { width:43px; }
    .resumen-table tfoot { font-weight:700; background:#f4f4f4; }
    .resumen-signatories { width:100%; margin:2.5rem 0 .5rem; border-collapse:separate; border-spacing:2rem 0; table-layout:fixed; text-align:center; color:#111; font-size:.8rem; }
    .resumen-signatory { padding-top:1.5rem; border-top:1px solid #111; }
    .resumen-signatory { width:33.33%; vertical-align:bottom; }
    .resumen-signatory strong { display:block; margin-bottom:.25rem; text-transform:uppercase; }
    @media (prefers-reduced-motion:reduce) {
        .resumen-download { transition:none; }
    }
    @media print {
        @page { size:landscape; margin:.25in; }
        body { background:#fff!important; }
        .sidebar,.topbar,.overlay,.resumen-tools { display:none!important; }
        .main-area,.content { width:100%!important; margin:0!important; padding:0!important; }
        .resumen-page { max-width:none; }
        .resumen-scroll { overflow:visible; padding:0; border:0; }
        .resumen-sheet { min-width:0; }
        .resumen-table { font-size:7pt; }
        .resumen-table th,.resumen-table td { padding:2px; }
        .resumen-signatories { margin-top:1.5rem; }
    }
</style>

<div class="resumen-page">
    @unless($pdf ?? false)
    <div class="resumen-tools">
        <p class="resumen-note">The starting count (A) is the previous month’s closing MS/UWP total. Additions (B, C+) are added; C-, D, DD, E, G, and H are deducted to calculate this month’s total.</p>
        <a class="resumen-download" href="{{ route('forms.monthly.resumen.download') }}">Download DOCX</a>
    </div>
    @endunless

    <div class="resumen-scroll">
        <section class="resumen-sheet" aria-label="Monthly Resumen report">
            <h1>BILANG NG NADAGDAG/NABAWAS NA MS/TS AT UWP</h1>
            <table class="resumen-table">
                <colgroup>
                    <col class="worker-col"><col class="purok-col">
                    @for ($i = 0; $i < 20; $i++)<col class="count-col">@endfor
                </colgroup>
                <thead>
                    <tr>
                        <th rowspan="4" class="left">MAYSAKOP</th>
                        <th rowspan="4">SAKOP NA PUROK</th>
                        <th colspan="9">PARA SA BUWAN NG: {{ mb_strtoupper($report['month']->format('F Y')) }} · MADALANG SUMAMBA (MS)</th>
                        <th rowspan="4">BILANG<br>NGAYON</th>
                        <th colspan="9">PARA SA BUWAN NG: {{ mb_strtoupper($report['month']->format('F Y')) }} · UMALIS WALANG PAALAM (UWP)</th>
                        <th rowspan="4">BILANG<br>NGAYON</th>
                    </tr>
                    <tr><th colspan="1">DATISAKOP</th><th colspan="2">NADAGDAG</th><th colspan="6">NABAWAS</th><th colspan="1">DATISAKOP</th><th colspan="2">NADAGDAG</th><th colspan="6">NABAWAS</th></tr>
                    <tr>
                        @foreach (['A','B','C+','C-','D','DD','E','G','H'] as $code)<th>{{ $code }}</th>@endforeach
                        @foreach (['A','B','C+','C-','D','DD','E','G','H'] as $code)<th>{{ $code }}</th>@endforeach
                    </tr>
                    <tr>
                        @foreach (['Opening','Add','Transfer in','Transfer out','Subtract','Subtract','Subtract','Subtract','Subtract'] as $label)<th>{{ $label }}</th>@endforeach
                        @foreach (['Opening','Add','Transfer in','Transfer out','Subtract','Subtract','Subtract','Subtract','Subtract'] as $label)<th>{{ $label }}</th>@endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($report['rows'] as $row)
                        <tr>
                            <td class="left">{{ $row['maysakop'] }}</td>
                            <td>{{ $row['purok_label'] }}</td>
                            @foreach (['A','B','C+','C-','D','DD','E','G','H'] as $code)<td>{{ $row['ms'][$code] }}</td>@endforeach
                            <td>{{ $row['ms_total'] }}</td>
                            @foreach (['A','B','C+','C-','D','DD','E','G','H'] as $code)<td>{{ $row['uwp'][$code] }}</td>@endforeach
                            <td>{{ $row['uwp_total'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="22">No Purok or movement data is available for this month.</td></tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr>
                        <td class="left">KABUUAN</td><td></td>
                        @foreach (['A','B','C+','C-','D','DD','E','G','H'] as $code)<td>{{ $report['totals']['ms'][$code] }}</td>@endforeach
                        <td>{{ $report['totals']['ms_total'] }}</td>
                        @foreach (['A','B','C+','C-','D','DD','E','G','H'] as $code)<td>{{ $report['totals']['uwp'][$code] }}</td>@endforeach
                        <td>{{ $report['totals']['uwp_total'] }}</td>
                    </tr>
                </tfoot>
            </table>
            <p>LOKAL: {{ $locale }} &nbsp; LCODE: {{ $lcode }}</p>
            <table class="resumen-signatories">
                <tbody><tr>
                    <td class="resumen-signatory"><strong>{{ $signatories['head_lsp'] ?: ' ' }}</strong>KALIHIM SA LSP</td>
                    <td class="resumen-signatory"><strong>{{ $signatories['ksp_pd'] ?: ' ' }}</strong>KATIWALA SA PAGPAPATIBAY (KSP)</td>
                    <td class="resumen-signatory"><strong>{{ $signatories['pastor_destinado'] ?: ' ' }}</strong>PASTOR NG LOKAL</td>
                </tr></tbody>
            </table>
        </section>
    </div>
</div>