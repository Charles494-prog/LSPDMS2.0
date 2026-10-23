@extends('it-recovery.layout')

@section('title', 'Temporary Password')

@section('content')
    @include('it-recovery.nav')

    <section class="card warning">
        <h2>Temporary password for #{{ $target->id }} · {{ trim($target->first_name.' '.$target->last_name) }} ({{ $target->gmail }})</h2>
        <p>This is shown <strong>only once</strong>. Give it to the account owner in person and ask them to change it in their profile right after signing in.</p>
        <p><code class="secret">{{ $temporaryPassword }}</code></p>
        <p class="muted">The account has been signed out on every device and its "remember me" token was cleared.</p>
        <p><a href="{{ route('it-recovery.dashboard') }}">Back to actions</a></p>
    </section>
@endsection
