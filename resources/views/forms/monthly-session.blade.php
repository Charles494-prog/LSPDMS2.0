@extends('layouts.app')

@section('title', 'Monthly Movement Monitor')

@section('content')
<style>
    .monthly-back-button { display:inline-flex; align-items:center; min-height:40px; padding:.5rem .85rem; border:1px solid var(--border); border-radius:9px; background:var(--surface); color:var(--accent); font:inherit; font-size:.85rem; font-weight:750; text-decoration:none; }
    .monthly-session-notice { margin:.8rem 0; padding:.75rem .9rem; border:1px solid var(--border); border-left:4px solid var(--danger); border-radius:9px; background:var(--surface); color:var(--text); font-size:.85rem; line-height:1.5; overflow-wrap:anywhere; }
    .monthly-session-notice[hidden] { display:none; }
</style>
<main style="max-width:1100px;margin:0 auto">
    <section style="padding:1.4rem;border:1px solid var(--border);border-radius:16px;background:var(--surface);margin-bottom:1rem">
        <nav style="display:flex;flex-wrap:wrap;gap:.5rem;margin-bottom:1rem">
            <a class="monthly-back-button" href="{{ route('forms.monthly.deliberation') }}">← Add / Subtract</a>
            <a class="monthly-back-button" href="{{ route('forms.monthly.modify') }}">Modify</a>
        </nav>
        <h1>Monthly Movement Monitor</h1>
        @if ($session)
            <p>Active session started {{ $session->started_at?->timezone('Asia/Manila')->format('d M Y H:i') }} by {{ $session->started_by ?: 'Unknown user' }}. Undoing a movement removes it from this temporary log. Finalizing copies remaining movements to permanent history.</p>
        @else
            <p>No monthly session is active. Start a session from a Monthly Add/Subtract or Monthly Modify page.</p>
        @endif
    </section>
    <section style="padding:1rem;border:1px solid var(--border);border-radius:16px;background:var(--surface)">
        <p class="monthly-session-notice" id="monthlySessionNotice" role="alert" aria-live="assertive" hidden></p>
        <div style="overflow-x:auto">
            <table style="width:100%;border-collapse:collapse">
                <thead><tr><th style="text-align:left;padding:.7rem">Member / details</th><th style="text-align:left;padding:.7rem">Movement</th><th style="text-align:left;padding:.7rem">Changed by / at</th><th style="padding:.7rem">Action</th></tr></thead>
                <tbody id="monthlyMovementRows" data-session-id="{{ $session?->id ?? '' }}" data-rendered-count="{{ $session?->movements()->count() ?? 0 }}">
                    @forelse ($session?->movements ?? collect() as $movement)
                        <tr data-movement-id="{{ $movement->id }}" style="border-top:1px solid var(--border)">
                            <td style="padding:.7rem">
                                <strong>{{ $movement->full_name }}</strong><br>
                                <small>{{ $movement->registry ?: 'No registry' }} · {{ $movement->gender ?: 'Gender not set' }} · {{ $movement->kapisanan ?: 'Kapisanan not set' }}</small><br>
                                <small>Baptism: {{ $movement->date_of_baptism?->format('d/m/Y') ?: 'Not set' }} · Evangelist: {{ $movement->evangelist ?: 'Not set' }}</small>
                            </td>
                            <td style="padding:.7rem">
                                <strong>{{ ucfirst($movement->action) }} · {{ $movement->destination_description }}</strong><br>
                                <small>{{ $movement->monthly_code ?: 'No monthly code' }}@if ($movement->description) · {{ $movement->description }}@endif</small>
                            </td>
                            <td style="padding:.7rem">{{ $movement->changed_by ?: 'Unknown' }}<br><small>{{ $movement->changed_at?->timezone('Asia/Manila')->format('d/m/Y H:i:s') }}</small></td>
                            <td style="padding:.7rem;text-align:center"><button type="button" class="monthly-undo" data-url="{{ route('forms.monthly.session.undo', $movement) }}" style="padding:.5rem .75rem;border:0;border-radius:8px;background:#b42318;color:white;font:inherit;font-weight:700;cursor:pointer">Undo</button></td>
                        </tr>
                    @empty
                        <tr id="monthlyMovementEmpty"><td colspan="4" style="padding:1.5rem;text-align:center;color:var(--text-muted)">No movements in the temporary session log.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</main>
@include('forms.monthly-session-widget', ['monthlySession' => $session, 'monthlySessionWidgetPolls' => false])
<script>
(() => {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const body = document.getElementById('monthlyMovementRows');
    const notice = document.getElementById('monthlySessionNotice');
    const statusUrl = @json(route('forms.monthly.session.status'));
    const renderMovements = movements => {
        body.replaceChildren();
        if (!movements.length) {
            const row = document.createElement('tr');
            const cell = document.createElement('td');
            cell.colSpan = 4;
            cell.style.cssText = 'padding:1.5rem;text-align:center;color:var(--text-muted)';
            cell.textContent = 'No movements in the temporary session log.';
            row.append(cell);
            body.append(row);
            return;
        }
        movements.forEach(movement => {
            const row = document.createElement('tr');
            row.dataset.movementId = movement.id;
            row.style.borderTop = '1px solid var(--border)';
            const member = document.createElement('td');
            member.style.padding = '.7rem';
            const name = document.createElement('strong');
            name.textContent = movement.name || 'Unnamed record';
            const details = document.createElement('small');
            details.textContent = `${movement.registry || 'No registry'} · ${movement.gender || 'Gender not set'} · ${movement.kapisanan || 'Kapisanan not set'}`;
            const personal = document.createElement('small');
            personal.textContent = `Baptism: ${movement.date_of_baptism || 'Not set'} · Evangelist: ${movement.evangelist || 'Not set'}`;
            member.append(name, document.createElement('br'), details, document.createElement('br'), personal);

            const action = document.createElement('td');
            action.style.padding = '.7rem';
            const label = document.createElement('strong');
            label.textContent = `${movement.action} · ${movement.destination_description || ''}`;
            const code = document.createElement('small');
            code.textContent = `${movement.monthly_code || 'No monthly code'}${movement.description ? ` · ${movement.description}` : ''}`;
            action.append(label, document.createElement('br'), code);

            const changed = document.createElement('td');
            changed.style.padding = '.7rem';
            changed.append(document.createTextNode(movement.changed_by || 'Unknown'), document.createElement('br'));
            const changedAt = document.createElement('small');
            changedAt.textContent = movement.changed_at || '';
            changed.append(changedAt);

            const controls = document.createElement('td');
            controls.style.cssText = 'padding:.7rem;text-align:center';
            const undo = document.createElement('button');
            undo.type = 'button';
            undo.className = 'monthly-undo';
            undo.dataset.url = movement.undo_url;
            undo.textContent = 'Undo';
            undo.style.cssText = 'padding:.5rem .75rem;border:0;border-radius:8px;background:#b42318;color:white;font:inherit;font-weight:700;cursor:pointer';
            controls.append(undo);
            row.append(member, action, changed, controls);
            body.append(row);
        });
    };
    body.addEventListener('click', async event => {
        const button = event.target.closest('.monthly-undo');
        if (!button || !window.confirm('Undo this movement? The member record will be restored and this temporary log entry deleted.')) return;
        button.disabled = true;
        try {
            const response = await fetch(button.dataset.url, { method: 'DELETE', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf } });
            const result = await response.json();
            if (!response.ok) {
                const errors = Object.values(result.errors || {}).flat();
                throw new Error(errors.join(' ') || result.message || 'Could not undo this movement.');
            }
            window.location.reload();
        } catch (error) {
            notice.textContent = error.message;
            notice.hidden = false;
            button.disabled = false;
        }
    });
    // One lightweight poll serves both this page and the floating widget; the full movement
    // list is fetched only when the count shows it changed.
    const poll = async () => {
        try {
            const response = await fetch(statusUrl, { headers: { 'Accept': 'application/json' } });
            if (!response.ok) return;
            const result = await response.json();
            window.dispatchEvent(new CustomEvent('monthly-session-status', { detail: result }));
            if (!result.session) {
                if (body.dataset.sessionId) window.location.reload();
                return;
            }
            if (String(body.dataset.sessionId) !== String(result.session.id)) {
                window.location.reload();
                return;
            }
            if (Number(body.dataset.renderedCount) !== result.session.movement_count) {
                const fullResponse = await fetch(`${statusUrl}?include_movements=1`, { headers: { 'Accept': 'application/json' } });
                if (!fullResponse.ok) return;
                const full = await fullResponse.json();
                if (!full.session || String(full.session.id) !== String(body.dataset.sessionId)) return;
                renderMovements(full.movements || []);
                body.dataset.renderedCount = full.session.movement_count;
            }
        } catch (_) {}
    };
    window.setInterval(() => {
        if (!document.hidden) poll();
    }, 5000);
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) poll();
    });
})();
</script>
@endsection
