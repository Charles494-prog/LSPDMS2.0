@extends('layouts.app')

@section('title', 'R4-03 Print')

@section('content')
<style>
    .r403-print { max-width:1120px; margin:0 auto; padding:1rem; background:#fff; color:#111; font-family:Arial,sans-serif; }
    .r403-tools { display:flex; justify-content:space-between; gap:.7rem; margin-bottom:1rem; }
    .r403-tools a,.r403-tools button { padding:.55rem .8rem; border:1px solid #bbb; border-radius:6px; background:#fff; color:#111; font:inherit; font-weight:700; text-decoration:none; cursor:pointer; }
    .r403-print h1 { margin:.2rem 0; text-align:center; font-size:1.35rem; }
    .r403-print h2 { margin:.4rem 0; font-size:1rem; }
    .r403-purok { margin:1.1rem 0 1.7rem; break-inside:avoid; }
    .r403-form-block { margin:.7rem 0 1.2rem; }
    .r403-meta { margin:.3rem 0; font-size:.8rem; }
    .r403-print table { width:100%; border-collapse:collapse; font-size:.78rem; }
    .r403-print th,.r403-print td { padding:.34rem; border:1px solid #333; text-align:left; }
    .r403-print th { background:#eee; }
    .r403-empty { padding:1rem; border:1px solid #aaa; }
    @media print { @page { size:landscape; margin:.45in; } .r403-tools { display:none; } .r403-print { max-width:none; padding:0; } }
</style>
<main class="r403-print">
    <nav class="r403-tools">
        <a href="{{ route('forms.weekly.r4-03', ['kalagayan' => $selectedStatus]) }}">Back to R4-03 Generator</a>
        <button type="button" onclick="window.print()">Print / Save as PDF</button>
    </nav>
    <h1>R4-03</h1>
    <p style="text-align:center">WEEKLY SUMMARY OF GENERATED R4-02 FORMS · {{ $selectedStatus === 'all' ? 'MS AND UWP' : $selectedStatus }}</p>
    @forelse ($groups as $group)
        <section class="r403-purok">
            <h2>{{ $group['name'] }}</h2>
            @foreach ($group['forms'] as $form)
                <div class="r403-form-block">
                    <p class="r403-meta"><strong>R4-02 #{{ $form->id }}</strong> · {{ $form->kalagayan }} · Dalaw: {{ $form->date_of_dalaw?->format('M j, Y') ?? '—' }} · Ulat: {{ $form->date_of_ulat?->format('M j, Y') ?? '—' }} · Prepared by: {{ $form->created_by ?: '—' }}</p>
                    <table>
                        <thead><tr><th>No.</th><th>Hakbang</th><th>R4-01 Code</th><th>Name</th><th>Registry</th><th>Purok-Grupo</th><th>Kapisanan</th></tr></thead>
                        <tbody>
                        @foreach ($form->formRecords as $row)
                            <tr><td>{{ $loop->iteration }}</td><td>{{ $row->hakbang_code }}</td><td>{{ $row->record_code }}</td><td>{{ $row->full_name }}</td><td>{{ $row->registry }}</td><td>{{ $row->purok_grupo }}</td><td>{{ $row->kapisanan }}</td></tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endforeach
        </section>
    @empty
        <p class="r403-empty">No saved R4-02 forms match this report. No blank Purok sections were generated.</p>
    @endforelse
</main>
@endsection
