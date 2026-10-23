@extends('layouts.app')

@section('title', 'Resumen & R4-01 Snapshots')

@section('content')
<style>
    .r401-snapshots { --hover:color-mix(in srgb,var(--text) 6%,var(--bg)); max-width:1200px; margin:0 auto; }
    .r401-snapshots button,.r401-snapshots select,.r401-snapshots input { font-family:inherit; }
    .r401-snapshot-heading { display:flex; flex-wrap:wrap; justify-content:space-between; align-items:flex-end; gap:1rem; padding-bottom:1rem; border-bottom:1px solid var(--border); }
    .r401-snapshot-heading h1 { margin:0; color:var(--text); font-size:1.25rem; font-weight:600; letter-spacing:-.01em; line-height:1.3; }
    .r401-snapshot-heading p { margin:.3rem 0 0; color:var(--text-muted); font-size:.85rem; }
    .r401-snapshot-heading-links { display:flex; flex-wrap:wrap; gap:.5rem; }

    .r401-snapshot-download { display:inline-flex; align-items:center; justify-content:center; min-height:34px; padding:0 .9rem; border:1px solid var(--border); border-radius:999px; background:transparent; color:var(--text); font-size:.8rem; font-weight:600; text-decoration:none; white-space:nowrap; cursor:pointer; transition:background-color .12s,border-color .12s,color .12s,opacity .12s; }
    .r401-snapshot-download:hover { background:var(--hover); border-color:color-mix(in srgb,var(--text) 45%,var(--border)); }
    .r401-snapshot-download.primary { border-color:var(--text); background:var(--text); color:var(--bg); }
    .r401-snapshot-download.primary:hover { opacity:.85; }
    .r401-snapshot-download.delete { color:#e5484d; border-color:color-mix(in srgb,#e5484d 45%,var(--border)); }
    .r401-snapshot-download.delete:hover { background:color-mix(in srgb,#e5484d 10%,var(--bg)); border-color:#e5484d; }
    .r401-snapshots button:focus-visible,.r401-snapshots a:focus-visible,.r401-snapshots select:focus-visible,.r401-snapshots input:focus-visible,
    .r401-snapshot-viewer button:focus-visible,.r401-snapshot-summary button:focus-visible,.r401-snapshot-delete-dialog button:focus-visible,.r401-snapshot-delete-dialog input:focus-visible { outline:2px solid color-mix(in srgb,var(--text) 55%,transparent); outline-offset:2px; }

    .r401-snapshot-filters { display:flex; flex-wrap:wrap; align-items:flex-end; gap:.65rem 1rem; padding:1rem 0; border-bottom:1px solid var(--border); }
    .r401-snapshot-filters label { display:grid; gap:.3rem; color:var(--text-muted); font-size:.75rem; font-weight:600; }
    .r401-snapshot-filters input,.r401-snapshot-filters select { min-height:36px; padding:.4rem .65rem; border:1px solid var(--border); border-radius:8px; background:transparent; color:var(--text); font-size:.82rem; font-weight:500; color-scheme:light dark; }

    .r401-snapshot-actions { display:flex; flex-wrap:wrap; gap:.4rem; }
    .r401-snapshot-actions form { margin:0; }
    .r401-snapshot-table-wrap { overflow-x:auto; }
    .r401-snapshot-table { width:100%; border-collapse:collapse; }
    .r401-snapshot-table th,.r401-snapshot-table td { padding:.8rem .7rem; border-bottom:1px solid var(--border); text-align:left; white-space:nowrap; font-size:.85rem; }
    .r401-snapshot-table th { color:var(--text-muted); font-size:.72rem; font-weight:600; letter-spacing:.04em; text-transform:uppercase; }
    .r401-snapshot-table td { font-variant-numeric:tabular-nums; }
    .r401-snapshot-table td:first-child { white-space:normal; font-weight:600; }
    .r401-snapshot-table tbody tr:hover { background:var(--hover); }
    .r401-snapshot-empty { padding:2.5rem 1rem; border-bottom:1px solid var(--border); color:var(--text-muted); font-size:.85rem; text-align:center; }
    .r401-snapshot-pagination { margin-top:1rem; }

    .r401-snapshot-viewer { width:min(1200px,calc(100% - 2rem)); height:min(92vh,900px); max-width:none; max-height:none; padding:0; overflow:hidden; border:1px solid var(--border); border-radius:12px; background:var(--surface); color:var(--text); }
    .r401-snapshot-viewer::backdrop,.r401-snapshot-summary::backdrop,.r401-snapshot-delete-dialog::backdrop { background:rgba(0,0,0,.72); }
    .r401-snapshot-viewer header,.r401-snapshot-summary header { display:flex; align-items:center; justify-content:space-between; gap:1rem; min-height:54px; padding:.65rem 1rem; border-bottom:1px solid var(--border); }
    .r401-snapshot-viewer h2,.r401-snapshot-summary h2 { margin:0; overflow:hidden; font-size:.95rem; font-weight:600; text-overflow:ellipsis; white-space:nowrap; }
    .r401-dialog-close { min-height:32px; padding:0 .8rem; border:1px solid var(--border); border-radius:999px; background:transparent; color:var(--text); font-size:.8rem; font-weight:600; cursor:pointer; }
    .r401-dialog-close:hover { background:var(--hover); }
    .r401-snapshot-viewer iframe { display:block; width:100%; height:calc(100% - 55px); border:0; background:#fff; }

    .r401-snapshot-summary { width:min(760px,calc(100% - 2rem)); max-height:min(82vh,760px); padding:0; overflow:hidden; border:1px solid var(--border); border-radius:12px; background:var(--surface); color:var(--text); }
    .r401-summary-tablist { display:flex; gap:1.25rem; padding:0 1rem; border-bottom:1px solid var(--border); }
    .r401-summary-tab { margin-bottom:-1px; padding:.65rem 0; border:0; border-bottom:2px solid transparent; background:transparent; color:var(--text-muted); font-size:.82rem; font-weight:600; cursor:pointer; }
    .r401-summary-tab[aria-selected="true"] { border-bottom-color:var(--text); color:var(--text); }
    .r401-summary-panel { max-height:calc(min(82vh,760px) - 112px); overflow:auto; padding:1rem; }
    .r401-summary-period { margin:0 0 .75rem; color:var(--text-muted); font-size:.82rem; }
    .r401-summary-list { margin:0; padding:0; border-top:1px solid var(--border); list-style:none; }
    .r401-summary-list li { padding:.7rem 0; border-bottom:1px solid var(--border); font-size:.85rem; line-height:1.5; }
    .r401-summary-list strong { color:var(--text); }
    .r401-summary-empty,.r401-summary-error { padding:1.2rem; color:var(--text-muted); font-size:.85rem; text-align:center; }
    .r401-summary-error { color:#e5484d; }

    .r401-snapshot-delete-dialog { width:min(520px,calc(100% - 2rem)); padding:0; border:1px solid var(--border); border-radius:12px; background:var(--surface); color:var(--text); }
    .r401-snapshot-delete-content { padding:1.25rem; }
    .r401-snapshot-delete-content h2 { margin:0 0 .6rem; font-size:1.05rem; font-weight:600; }
    .r401-snapshot-delete-content p { margin:.5rem 0; color:var(--text-muted); font-size:.85rem; line-height:1.5; }
    .r401-snapshot-delete-content strong { color:var(--text); overflow-wrap:anywhere; }
    .r401-snapshot-delete-content label { display:grid; gap:.4rem; margin-top:1rem; font-size:.82rem; font-weight:600; }
    .r401-snapshot-delete-content input[type="text"] { min-height:38px; padding:.5rem .65rem; border:1px solid var(--border); border-radius:8px; background:transparent; color:var(--text); font-size:.85rem; font-weight:500; }
    .r401-snapshot-delete-acknowledgement { display:flex!important; align-items:flex-start; gap:.5rem; }
    .r401-snapshot-delete-acknowledgement input { width:16px; min-height:16px!important; margin:.15rem 0 0; accent-color:var(--text); }
    .r401-snapshot-delete-acknowledgement span { font-size:.85rem; font-weight:500; }
    .r401-snapshots .r401-snapshot-delete-error,.r401-snapshot-delete-content .r401-snapshot-delete-error { color:#e5484d; font-size:.8rem; font-weight:600; }
    .r401-snapshot-delete-actions { display:flex; justify-content:flex-end; gap:.5rem; margin-top:1.1rem; padding-top:1rem; border-top:1px solid var(--border); }
    .r401-snapshot-delete-actions button { min-height:34px; padding:0 .9rem; border:1px solid var(--border); border-radius:999px; background:transparent; color:var(--text); font-size:.8rem; font-weight:600; cursor:pointer; }
    .r401-snapshot-delete-actions button:hover { background:var(--hover); }
    .r401-snapshot-delete-actions .danger { border-color:#e5484d; background:#e5484d; color:#fff; }
    .r401-snapshot-delete-actions .danger:hover { background:#e5484d; opacity:.88; }
    .r401-snapshot-delete-actions button:disabled { cursor:not-allowed; opacity:.45; }

    @media (prefers-reduced-motion:reduce) { .r401-snapshot-download { transition:none; } }
</style>

<main class="r401-snapshots">
    <section>
        <header class="r401-snapshot-heading">
            <div>
                <h1>Resumen &amp; R4-01 Snapshots</h1>
                <p>Combined monthly reports saved when the monthly session is finalized.</p>
            </div>
            <div class="r401-snapshot-heading-links">
                <a class="r401-snapshot-download primary" href="{{ route('form-generator', ['panel' => 'monthly', 'monthly_section' => 'monthly-forms']) }}">Open Resumen &amp; R4-01 Generators</a>
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
                                        <button type="button" class="r401-snapshot-download" data-snapshot-summary="{{ route('forms.monthly.r4-01.snapshots.summary', $snapshot) }}" data-snapshot-name="{{ $snapshot->file_name }}">Summary</button>
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
        <button type="button" class="r401-dialog-close" id="r401SnapshotViewerClose">Close</button>
    </header>
    <iframe id="r401SnapshotViewerFrame" title="Monthly report preview"></iframe>
</dialog>
<dialog class="r401-snapshot-summary" id="r401SnapshotSummary" aria-labelledby="r401SnapshotSummaryTitle">
    <header>
        <h2 id="r401SnapshotSummaryTitle">Monthly session summary</h2>
        <button type="button" class="r401-dialog-close" id="r401SnapshotSummaryClose">Close</button>
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