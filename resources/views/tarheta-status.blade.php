@extends('layouts.app')

@section('title', 'Tarheta Status Checker')

@section('content')
<style>
    .tarheta-page { max-width:1000px; margin:0 auto; }
    .tarheta-heading,.tarheta-panel { padding:clamp(1rem,3vw,1.6rem); border:1px solid var(--border); border-radius:16px; background:var(--surface); }
    .tarheta-heading { margin-bottom:1rem; }
    .tarheta-heading h1 { margin:.2rem 0 .45rem; }
    .tarheta-heading p,.tarheta-note { color:var(--text-muted); line-height:1.5; }
    .tarheta-table { width:100%; min-width:900px; border-collapse:collapse; }
    .tarheta-table th,.tarheta-table td { padding:.8rem; border-bottom:1px solid var(--border); text-align:left; vertical-align:middle; }
    .tarheta-table select,.tarheta-table input { width:100%; min-height:40px; padding:.5rem .7rem; border:1px solid var(--border); border-radius:8px; background:var(--bg); color:var(--text); font:inherit; }
    .tarheta-table select:disabled,.tarheta-table input:disabled { opacity:.65; cursor:wait; }
    .tarheta-status { min-width:100px; }
    .tarheta-message { margin-top:.35rem; color:var(--danger); font-size:.8rem; }
    .tarheta-row[hidden] { display:none; }
    .tarheta-empty { padding:1.5rem; text-align:center; color:var(--text-muted); }
    .tarheta-controls { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:.8rem; margin:1rem 0; }
    .tarheta-search { min-height:42px; flex:1 1 240px; padding:.6rem .75rem; border:1px solid var(--border); border-radius:9px; background:var(--bg); color:var(--text); font:inherit; }
    .tarheta-toggle { display:flex; align-items:center; gap:.45rem; color:var(--text-muted); font-size:.85rem; }
    .tarheta-toggle input { width:17px; height:17px; accent-color:var(--accent); }
    .tarheta-progress { height:12px; overflow:hidden; border-radius:99px; background:var(--border); }
    .tarheta-progress > span { display:block; width:{{ $percentage }}%; height:100%; border-radius:inherit; background:#16a34a; transition:width .25s ease; }
    .tarheta-summary { margin:.6rem 0 1rem; color:var(--text-muted); font-size:.86rem; }
    @media(max-width:680px) { .tarheta-table th,.tarheta-table td { padding:.55rem .5rem; } }
</style>
<main class="tarheta-page">
    <header class="tarheta-heading">
        <h1>Tarheta Status Checker</h1>
        <p>Compare each record’s status with its Tarheta and filing status. Add a filing number when one is issued. A record is complete when all monitored statuses match.</p>
        <div class="tarheta-controls">
            <input id="tarhetaSearch" class="tarheta-search" type="search" placeholder="Search by member name or status" aria-label="Search Tarheta records">
            <label class="tarheta-toggle"><input id="showMatched" type="checkbox"> Show already-matched records</label>
            <label class="tarheta-toggle"><input id="monitorFiling" type="checkbox" checked> Monitor filing status</label>
        </div>
        <div class="tarheta-summary"><strong id="tarhetaMatched">{{ $matched }}</strong> of <strong id="tarhetaTotal">{{ $total }}</strong> evaluated records matched (<strong id="tarhetaPercent">{{ $percentage }}%</strong>). <span id="tarhetaMatchRule">Tarheta and filing statuses must both match the record status.</span></div>
        <div class="tarheta-progress" role="progressbar" aria-label="Records matching Tarheta status" aria-valuemin="0" aria-valuemax="{{ $total }}" aria-valuenow="{{ $matched }}">
            <span id="tarhetaProgress" style="width:{{ $percentage }}%"></span>
        </div>
        <p class="tarheta-note"><strong id="tarhetaCount">{{ $total - $matched }}</strong> record{{ $total - $matched === 1 ? '' : 's' }} need review.</p>
    </header>
    <section class="tarheta-panel">
        <div class="table-scroll">
            <table class="tarheta-table">
                <thead><tr><th>Name</th><th>Status</th><th>Tarheta status</th><th>Filing status</th><th>Filing number</th></tr></thead>
                <tbody id="tarhetaRows">
                    @forelse ($records as $record)
                        <tr class="tarheta-row" data-record-id="{{ $record['id'] }}" data-status="{{ $record['status'] }}" data-tarheta-status="{{ $record['tarheta_status'] }}" data-filing-status="{{ $record['filing_status'] }}" data-matched="{{ $record['matched'] ? '1' : '0' }}" @if ($record['matched']) hidden @endif>
                            <td class="tarheta-name">{{ $record['name'] }}</td>
                            <td class="tarheta-status">{{ $record['status'] ?: 'Not set' }}</td>
                            <td>
                                <select class="tarheta-choice" aria-label="Tarheta status for {{ $record['name'] }}">
                                    <option value="">Select status</option>
                                    @foreach (['MS', 'UWP', 'MK', 'INACTIVE'] as $status)
                                        <option value="{{ $status }}" @selected($record['tarheta_status'] === $status)>{{ $status }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <select class="filing-status-choice" aria-label="Filing status for {{ $record['name'] }}">
                                    <option value="">Select status</option>
                                    @foreach (['MS', 'UWP', 'MK', 'INACTIVE'] as $status)
                                        <option value="{{ $status }}" @selected($record['filing_status'] === $status)>{{ $status }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <input class="filing-number-input" type="text" maxlength="100" value="{{ $record['filing_number'] }}" aria-label="Filing number for {{ $record['name'] }}" placeholder="Enter filing number">
                                <div class="tarheta-message" role="status" aria-live="polite"></div>
                            </td>
                        </tr>
                    @empty
                        <tr class="tarheta-empty-row"><td colspan="5" class="tarheta-empty">No records are available to evaluate.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</main>
<script>
(() => {
    const rows = document.getElementById('tarhetaRows');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const allRows = () => [...rows.querySelectorAll('tr[data-record-id]')];
    const showMatched = document.getElementById('showMatched');
    const monitorFiling = document.getElementById('monitorFiling');
    const search = document.getElementById('tarhetaSearch');
    const isMatched = row => row.dataset.status === row.dataset.tarhetaStatus
        && (!monitorFiling.checked || row.dataset.status === row.dataset.filingStatus);
    const render = () => {
        const term = search.value.trim().toLocaleLowerCase();
        allRows().forEach(row => {
            row.dataset.matched = isMatched(row) ? '1' : '0';
            const matchesSearch = !term || row.textContent.toLocaleLowerCase().includes(term);
            row.hidden = !matchesSearch || (!showMatched.checked && row.dataset.matched === '1');
        });
        const currentRows = allRows();
        const matched = currentRows.filter(row => row.dataset.matched === '1').length;
        const total = currentRows.length;
        const percentage = total === 0 ? 0 : Math.round((matched / total) * 1000) / 10;
        document.getElementById('tarhetaMatched').textContent = matched;
        document.getElementById('tarhetaTotal').textContent = total;
        document.getElementById('tarhetaPercent').textContent = `${percentage}%`;
        document.getElementById('tarhetaProgress').style.width = `${percentage}%`;
        const progress = document.querySelector('.tarheta-progress');
        progress.setAttribute('aria-valuemax', total);
        progress.setAttribute('aria-valuenow', matched);
        const remaining = total - matched;
        const review = document.getElementById('tarhetaCount');
        review.textContent = remaining;
        review.parentElement.lastChild.textContent = ` record${remaining === 1 ? '' : 's'} need review.`;
        document.getElementById('tarhetaMatchRule').textContent = monitorFiling.checked
            ? 'Tarheta and filing statuses must both match the record status.'
            : 'Tarheta status must match the record status; filing status is not monitored.';
    };
    showMatched.addEventListener('change', render);
    monitorFiling.addEventListener('change', render);
    search.addEventListener('input', render);
    rows.addEventListener('change', async event => {
        const control = event.target.closest('.tarheta-choice, .filing-status-choice, .filing-number-input');
        if (!control) return;
        const row = control.closest('tr[data-record-id]');
        const message = row.querySelector('.tarheta-message');
        const controls = [...row.querySelectorAll('.tarheta-choice, .filing-status-choice, .filing-number-input')];
        controls.forEach(input => { input.disabled = true; });
        try {
            const response = await fetch(`{{ route('tarheta-status.update', ['record' => '__RECORD__']) }}`.replace('__RECORD__', encodeURIComponent(row.dataset.recordId)), {
                method: 'PUT',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify({
                    tarheta_status: row.querySelector('.tarheta-choice').value || null,
                    filing_status: row.querySelector('.filing-status-choice').value || null,
                    filing_number: row.querySelector('.filing-number-input').value
                })
            });
            let result = {};
            try { result = await response.json(); } catch (error) { throw new Error('The server returned an unreadable response.'); }
            if (!response.ok) {
                const errors = Object.values(result.errors || {}).flat();
                throw new Error(errors.join(' ') || result.message || 'Could not save Tarheta status.');
            }
            row.dataset.status = result.status;
            row.dataset.tarhetaStatus = result.tarheta_status;
            row.dataset.filingStatus = result.filing_status;
            row.querySelector('.filing-number-input').value = result.filing_number;
            message.textContent = 'Status and filing details saved.';
            render();
        } catch (error) {
            message.textContent = error.message || 'Could not save Tarheta status.';
        } finally {
            if (row.isConnected) controls.forEach(input => { input.disabled = false; });
        }
    });
    render();
})();
</script>
@endsection
