@extends('it-recovery.layout')

@section('title', 'IT Recovery Audit Log')

@section('content')
    @include('it-recovery.nav')

    <section class="card">
        <p class="muted">Read-only. Entries cannot be edited or deleted from LSPDMS and are not part of SQL export or import.</p>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>#</th><th>When</th><th>Event</th><th>Account</th><th>IP</th><th>Details</th><th>User agent</th></tr>
                </thead>
                <tbody>
                    @forelse ($entries as $entry)
                        <tr>
                            <td>{{ $entry->id }}</td>
                            <td>{{ $entry->created_at?->timezone('Asia/Manila')->format('Y-m-d H:i:s') }}</td>
                            <td>{{ $entry->event }}</td>
                            <td>{{ $entry->account_email }}</td>
                            <td>{{ $entry->ip }}</td>
                            <td>{{ $entry->details ? json_encode($entry->details, JSON_UNESCAPED_SLASHES) : '' }}</td>
                            <td class="muted">{{ $entry->user_agent }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="muted">No entries.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <nav>
            @if ($entries->previousPageUrl())
                <a href="{{ $entries->previousPageUrl() }}">Newer</a>
            @endif
            @if ($entries->nextPageUrl())
                <a href="{{ $entries->nextPageUrl() }}">Older</a>
            @endif
        </nav>
    </section>
@endsection
