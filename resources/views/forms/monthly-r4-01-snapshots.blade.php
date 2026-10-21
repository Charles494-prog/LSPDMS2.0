@extends('layouts.app')

@section('title', 'Resumen & R4-01 Snapshots')

@section('content')
<style>
    .r401-snapshots { max-width:1200px; margin:0 auto; }
    .r401-snapshot-panel { padding:1.25rem; border:1px solid var(--border); border-radius:14px; background:var(--surface); }
    .r401-snapshot-heading { display:flex; flex-wrap:wrap; justify-content:space-between; align-items:center; gap:1rem; margin-bottom:1rem; }
    .r401-snapshot-heading-links { display:flex; flex-wrap:wrap; gap:.5rem; }
    .r401-snapshot-heading h1 { margin:0; color:var(--text); font-size:1.35rem; }
    .r401-snapshot-heading p { margin:.35rem 0 0; color:var(--text-muted); }
    .r401-snapshot-filters { display:flex; flex-wrap:wrap; align-items:end; gap:.65rem; margin:1rem 0; }
    .r401-snapshot-filters label { display:grid; gap:.35rem; color:var(--text-muted); font-size:.8rem; font-weight:700; }
    .r401-snapshot-filters input,.r401-snapshot-filters select { min-height:38px; padding:.45rem .6rem; border:1px solid var(--border); border-radius:8px; background:var(--bg); color:var(--text); font:inherit; }
    .r401-snapshot-download { display:inline-flex; min-height:38px; align-items:center; justify-content:center; padding:.5rem .8rem; border:0; border-radius:8px; background:var(--accent); color:#fff; font-weight:700; text-decoration:none; cursor:pointer; }
    .r401-snapshot-actions { display:flex; flex-wrap:wrap; gap:.4rem; }
    .r401-snapshot-actions form { margin:0; }
    .r401-snapshot-actions .delete { background:#a52b2b; }
    .r401-snapshot-actions .summary { background:#344d73; }
    .r401-snapshot-table-wrap { overflow-x:auto; }
    .r401-snapshot-table { width:100%; border-collapse:collapse; }
    .r401-snapshot-table th,.r401-snapshot-table td { padding:.9rem .7rem; border-bottom:1px solid var(--border); text-align:left; white-space:nowrap; }
    .r401-snapshot-table th { color:var(--text-muted); font-size:.76rem; text-transform:uppercase; }
    .r401-snapshot-table td:first-child { white-space:normal; font-weight:700; }
    .r401-snapshot-empty { padding:2.5rem 1rem; color:var(--text-muted); text-align:center; }
    .r401-snapshot-pagination { margin-top:1rem; }
    .r401-snapshot-viewer { width:min(1200px,calc(100% - 2rem)); height:min(92vh,900px); max-width:none; max-height:none; padding:0; overflow:hidden; border:1px solid var(--border); border-radius:14px; background:var(--surface); color:var(--text); box-shadow:0 24px 70px rgba(15,23,42,.35); }
    .r401-snapshot-viewer::backdrop,.r401-snapshot-delete-dialog::backdrop { background:rgba(15,23,42,.72); backdrop-filter:blur(3px); }
    .r401-snapshot-viewer header { display:flex; align-items:center; justify-content:space-between; gap:1rem; min-height:54px; padding:.65rem 1rem; border-bottom:1px solid var(--border); }
    .r401-snapshot-viewer h2 { margin:0; overflow:hidden; font-size:1rem; text-overflow:ellipsis; white-space:nowrap; }
    .r401-snapshot-viewer header button { min-height:36px; padding:.45rem .75rem; border:0; border-radius:8px; background:var(--bg); color:var(--text); font:inherit; font-weight:700; cursor:pointer; }
    .r401-snapshot-viewer iframe { display:block; width:100%; height:calc(100% - 55px); border:0; background:#eef2f6; }
    .r401-snapshot-summary { width:min(760px,calc(100% - 2rem)); max-height:min(82vh,760px); padding:0; overflow:hidden; border:1px solid var(--border); border-radius:14px; background:var(--surface); color:var(--text); box-shadow:0 24px 70px rgba(15,23,42,.35); }
    .r401-snapshot-summary::backdrop { background:rgba(15,23,42,.72); backdrop-filter:blur(3px); }
    .r401-snapshot-summary header { display:flex; align-items:center; justify-content:space-between; gap:1rem; min-height:58px; padding:.7rem 1rem; border-bottom:1px solid var(--border); }
    .r401-snapshot-summary h2 { margin:0; overflow:hidden; font-size:1rem; text-overflow:ellipsis; white-space:nowrap; }
    .r401-snapshot-summary header button { min-height:36px; padding:.45rem .75rem; border:0; border-radius:8px; background:var(--bg); color:var(--text); font:inherit; font-weight:700; cursor:pointer; }
    .r401-summary-tablist { display:flex; gap:.4rem; padding:.65rem 1rem 0; border-bottom:1px solid var(--border); }
    .r401-summary-tab { padding:.6rem .9rem; border:1px solid transparent; border-bottom:0; border-radius:8px 8px 0 0; background:transparent; color:var(--text-muted); font:inherit; font-weight:750; }
    .r401-summary-tab[aria-selected="true"] { border-color:var(--border); background:var(--surface); color:var(--accent); }
    .r401-summary-panel { max-height:calc(min(82vh,760px) - 112px); overflow:auto; padding:1rem; }
    .r401-summary-period { margin:0 0 .85rem; color:var(--text-muted); font-size:.86rem; }
    .r401-summary-list { display:grid; gap:.55rem; margin:0; padding:0; list-style:none; }
    .r401-summary-list li { padding:.75rem .85rem; border:1px solid var(--border); border-radius:9px; background:var(--bg); line-height:1.5; }
    .r401-summary-list strong { color:var(--text); }
    .r401-summary-empty,.r401-summary-error { padding:1.2rem; color:var(--text-muted); text-align:center; }
    .r401-summary-error { color:#a52b2b; }
    .r401-snapshot-delete-dialog { width:min(520px,calc(100% - 2rem)); padding:0; border:1px solid var(--border); border-radius:16px; background:var(--surface); color:var(--text); box-shadow:0 24px 70px rgba(15,23,42,.35); }
    .r401-snapshot-delete-content { padding:1.25rem; }
    .r401-snapshot-delete-content h2 { margin:0 0 .6rem; font-size:1.15rem; }
    .r401-snapshot-delete-content p { color:var(--text-muted); line-height:1.5; }
    .r401-snapshot-delete-content strong { overflow-wrap:anywhere; }
    .r401-snapshot-delete-content label { display:grid; gap:.4rem; margin-top:1rem; font-weight:700; }
    .r401-snapshot-delete-content input[type="text"] { min-height:42px; padding:.6rem .7rem; border:1px solid var(--border); border-radius:8px; background:var(--bg); color:var(--text); font:inherit; }
    .r401-snapshot-delete-acknowledgement { display:flex!important; align-items:flex-start; gap:.5rem; }
    .r401-snapshot-delete-acknowledgement input { width:18px; min-height:18px!important; margin-top:.15rem; }
    .r401-snapshot-delete-acknowledgement span { font-size:.9rem; font-weight:600; }
    .r401-snapshot-delete-error { color:#a52b2b!important; font-size:.85rem; font-weight:700; }
    .r401-snapshot-delete-actions { display:flex; justify-content:flex-end; gap:.5rem; margin-top:1.1rem; }
    .r401-snapshot-delete-actions button { min-height:38px; padding:.5rem .75rem; border:1px solid var(--border); border-radius:8px; background:var(--bg); color:var(--text); font:inherit; font-weight:700; cursor:pointer; }
    .r401-snapshot-delete-actions .danger { border-color:#a52b2b; background:#a52b2b; color:#fff; }
    .r401-snapshot-delete-actions button:disabled { cursor:not-allowed; opacity:.5; }
</style>

<main class="r401-snapshots">
    <section class="r401-snapshot-panel">
        <header class="r401-snapshot-heading">
            <div>
                <h1>Resumen &amp; R4-01 Snapshots</h1>
                <p>Combined monthly reports saved when the monthly session is finalized.</p>
            </div>
            <div class="r401-snapshot-heading-links">
                <a class="r401-snapshot-download" href="{{ route('form-generator', ['panel' => 'monthly', 'monthly_section' => 'monthly-forms']) }}">Open Resumen &amp; R4-01 Generators</a>
            </div>
        </header>
        <form class="r401-snapshot-filters" method="GET" action="{{ route('forms.monthly.r4-01.snapshots') }}">
            <label>Year
                <select name="year" aria-label="Filter snapshots by year">
                    <option value="">All years</option>
                    @foreach ($years as $year)
                        <option value="{{ $year }}" @selected(($filters['year'] ?? '') === (string) $year)>{{ $year }}</option>
                    @endforeach
                </select>
            </label>
            <label>Month<input type="month" name="month" aria-label="Filter snapshots by month" value="{{ $filters['month'] ?? '' }}"></label>
        </form>

        @if ($snapshots->isEmpty())
            <p class="r401-snapshot-empty">No saved monthly session snapshots match these filters. A snapshot is created when a monthly session is finalized.</p>
        @else
            <div class="r401-snapshot-table-wrap">
                <table class="r401-snapshot-table">
                    <thead>
                        <tr><th>File name</th><th>Generated on</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($snapshots as $snapshot)
                            <tr>
                                <td>{{ $snapshot->file_name }}</td>
                                <td>{{ $snapshot->created_at->timezone('Asia/Manila')->format('d M Y, h:i A') }}</td>
                                <td>
                                    <div class="r401-snapshot-actions">
                                        <button type="button" class="r401-snapshot-download summary" data-snapshot-summary="{{ route('forms.monthly.r4-01.snapshots.summary', $snapshot) }}" data-snapshot-name="{{ $snapshot->file_name }}">Summary</button>
                                        <button type="button" class="r401-snapshot-download" data-snapshot-view="{{ route('forms.monthly.r4-01.snapshots.view', $snapshot) }}" data-snapshot-name="{{ $snapshot->file_name }}">View</button>
                                        <a class="r401-snapshot-download" href="{{ route('forms.monthly.r4-01.snapshots.download', $snapshot) }}">Download</a>
                                        @if ($canDeleteSnapshots)
                                            <form method="POST" action="{{ route('forms.monthly.r4-01.snapshots.delete', $snapshot) }}" class="r401-snapshot-delete-form" data-snapshot-name="{{ $snapshot->file_name }}">
                                                @csrf
                                                @method('DELETE')
                                                <input type="hidden" name="acknowledge_permanent_deletion">
                                                <input type="hidden" name="confirmation_phrase">
                                                <button class="r401-snapshot-download delete" type="submit">Delete</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="r401-snapshot-pagination">{{ $snapshots->links() }}</div>
        @endif
    </section>
</main>
<dialog class="r401-snapshot-viewer" id="r401SnapshotViewer" aria-labelledby="r401SnapshotViewerTitle">
    <header>
        <h2 id="r401SnapshotViewerTitle"></h2>
        <button type="button" id="r401SnapshotViewerClose">Close</button>
    </header>
    <iframe id="r401SnapshotViewerFrame" title="Monthly report preview"></iframe>
</dialog>
<dialog class="r401-snapshot-summary" id="r401SnapshotSummary" aria-labelledby="r401SnapshotSummaryTitle">
    <header>
        <h2 id="r401SnapshotSummaryTitle">Monthly session summary</h2>
        <button type="button" id="r401SnapshotSummaryClose">Close</button>
    </header>
    <div class="r401-summary-tablist" role="tablist" aria-label="Snapshot details">
        <button type="button" class="r401-summary-tab" id="r401SummaryTab" role="tab" aria-selected="true" aria-controls="r401SummaryPanel">Summary</button>
    </div>
    <section class="r401-summary-panel" id="r401SummaryPanel" role="tabpanel" aria-labelledby="r401SummaryTab">
        <p class="r401-summary-period" id="r401SummaryPeriod"></p>
        <div id="r401SummaryContent" aria-live="polite"></div>
    </section>
</dialog>
@if ($canDeleteSnapshots)
    <dialog class="r401-snapshot-delete-dialog" id="r401SnapshotDeleteDialog" aria-labelledby="r401SnapshotDeleteTitle">
        <form class="r401-snapshot-delete-content" id="r401SnapshotDeleteForm">
            <h2 id="r401SnapshotDeleteTitle">Permanently delete monthly snapshot?</h2>
            <p>This action cannot be undone. It will permanently remove the saved file and its archive entry:</p>
            <p><strong id="r401SnapshotDeleteName"></strong></p>
            <p>Only proceed if you are certain this is the correct snapshot. To confirm, type <strong>SUPERADMIN</strong> exactly; the text is case-sensitive.</p>
            <label class="r401-snapshot-delete-acknowledgement">
                <span><input id="r401SnapshotDeleteAcknowledge" type="checkbox"> I understand this permanently deletes both the saved file and its archive entry.</span>
            </label>
            <label for="r401SnapshotDeletePhrase">Confirmation phrase
                <input id="r401SnapshotDeletePhrase" name="confirmation_phrase" type="text" autocomplete="off" required>
            </label>
            <p class="r401-snapshot-delete-error" id="r401SnapshotDeleteError" role="alert" hidden>Please type SUPERADMIN exactly to enable deletion.</p>
            <p class="r401-snapshot-delete-error" id="r401SnapshotDeleteAcknowledgementError" role="alert" hidden>Please acknowledge that this deletion is permanent.</p>
            <div class="r401-snapshot-delete-actions">
                <button type="button" id="r401SnapshotDeleteCancel">Cancel</button>
                <button type="submit" class="danger" id="r401SnapshotDeleteConfirm" disabled>Delete permanently</button>
            </div>
        </form>
    </dialog>
@endif
<script>
(() => {
    const filters = document.querySelector('.r401-snapshot-filters');
    const yearFilter = filters?.querySelector('select[name="year"]');
    const monthFilter = filters?.querySelector('input[type="month"]');
    yearFilter?.addEventListener('change', () => {
        if (monthFilter) monthFilter.value = '';
        filters.requestSubmit();
    });
    monthFilter?.addEventListener('change', () => {
        if (monthFilter.value && yearFilter) yearFilter.value = monthFilter.value.slice(0, 4);
        filters.requestSubmit();
    });

    const viewer = document.getElementById('r401SnapshotViewer');
    const viewerTitle = document.getElementById('r401SnapshotViewerTitle');
    const viewerFrame = document.getElementById('r401SnapshotViewerFrame');
    document.querySelectorAll('[data-snapshot-view]').forEach(button => {
        button.addEventListener('click', () => {
            viewerTitle.textContent = button.dataset.snapshotName;
            viewerFrame.src = button.dataset.snapshotView;
            viewer.showModal();
        });
    });
    document.getElementById('r401SnapshotViewerClose')?.addEventListener('click', () => viewer.close());
    viewer?.addEventListener('close', () => { viewerFrame.src = 'about:blank'; });

    const summaryDialog = document.getElementById('r401SnapshotSummary');
    const summaryTitle = document.getElementById('r401SnapshotSummaryTitle');
    const summaryPeriod = document.getElementById('r401SummaryPeriod');
    const summaryContent = document.getElementById('r401SummaryContent');
    document.querySelectorAll('[data-snapshot-summary]').forEach(button => {
        button.addEventListener('click', async () => {
            summaryTitle.textContent = button.dataset.snapshotName;
            summaryPeriod.textContent = '';
            summaryContent.replaceChildren(Object.assign(document.createElement('p'), {
                className: 'r401-summary-empty',
                textContent: 'Loading session summary…',
            }));
            summaryDialog.showModal();
            try {
                const response = await fetch(button.dataset.snapshotSummary, {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                });
                const data = await response.json();
                if (!response.ok) throw new Error(data.message || 'Unable to load this snapshot summary.');
                summaryPeriod.textContent = data.period;
                if (!data.entries.length) {
                    const empty = document.createElement('p');
                    empty.className = 'r401-summary-empty';
                    empty.textContent = 'No recorded monthly changes are available for this snapshot.';
                    summaryContent.replaceChildren(empty);
                    return;
                }
                const list = document.createElement('ul');
                list.className = 'r401-summary-list';
                data.entries.forEach(entry => {
                    const item = document.createElement('li');
                    const name = document.createElement('strong');
                    name.textContent = `${entry.name} `;
                    item.append(name, document.createTextNode(entry.summary));
                    list.append(item);
                });
                summaryContent.replaceChildren(list);
            } catch (error) {
                const message = document.createElement('p');
                message.className = 'r401-summary-error';
                message.textContent = error.message;
                summaryContent.replaceChildren(message);
            }
        });
    });
    document.getElementById('r401SnapshotSummaryClose')?.addEventListener('click', () => summaryDialog.close());

    const deleteDialog = document.getElementById('r401SnapshotDeleteDialog');
    if (!deleteDialog) return;
    const deleteName = document.getElementById('r401SnapshotDeleteName');
    const deletePhrase = document.getElementById('r401SnapshotDeletePhrase');
    const deleteAcknowledge = document.getElementById('r401SnapshotDeleteAcknowledge');
    const deleteError = document.getElementById('r401SnapshotDeleteError');
    const deleteAcknowledgementError = document.getElementById('r401SnapshotDeleteAcknowledgementError');
    const deleteConfirm = document.getElementById('r401SnapshotDeleteConfirm');
    const deleteForm = document.getElementById('r401SnapshotDeleteForm');
    let selectedDeleteForm = null;

    document.querySelectorAll('.r401-snapshot-delete-form').forEach(form => {
        form.addEventListener('submit', event => {
            if (form.dataset.confirmed === 'true') return;
            event.preventDefault();
            selectedDeleteForm = form;
            deleteName.textContent = form.dataset.snapshotName;
            deletePhrase.value = '';
            deleteAcknowledge.checked = false;
            deleteConfirm.disabled = true;
            deleteError.hidden = true;
            deleteAcknowledgementError.hidden = true;
            deleteDialog.showModal();
            deletePhrase.focus();
        });
    });
    deletePhrase.addEventListener('input', () => {
        const matches = deletePhrase.value === 'SUPERADMIN';
        deleteConfirm.disabled = !matches || !deleteAcknowledge.checked;
        deleteError.hidden = matches || deletePhrase.value.length === 0;
    });
    deleteAcknowledge.addEventListener('change', () => {
        deleteConfirm.disabled = deletePhrase.value !== 'SUPERADMIN' || !deleteAcknowledge.checked;
        deleteAcknowledgementError.hidden = deleteAcknowledge.checked;
    });
    document.getElementById('r401SnapshotDeleteCancel').addEventListener('click', () => deleteDialog.close());
    deleteForm.addEventListener('submit', event => {
        event.preventDefault();
        if (!selectedDeleteForm || deletePhrase.value !== 'SUPERADMIN' || !deleteAcknowledge.checked) {
            deleteError.hidden = deletePhrase.value === 'SUPERADMIN';
            deleteAcknowledgementError.hidden = deleteAcknowledge.checked;
            return;
        }
        selectedDeleteForm.querySelector('[name="acknowledge_permanent_deletion"]').value = '1';
        selectedDeleteForm.querySelector('[name="confirmation_phrase"]').value = deletePhrase.value;
        selectedDeleteForm.dataset.confirmed = 'true';
        selectedDeleteForm.requestSubmit();
    });
})();
</script>
@endsection
