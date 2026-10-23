@extends('it-recovery.layout')

@section('title', 'IT Recovery Sign-in')

@section('content')
    <header><h1>IT Recovery Sign-in</h1></header>
    <div class="card warning">
        Restricted break-glass access for the IT administrator. Every attempt is recorded.
    </div>
    <form method="POST" action="{{ route('it-recovery.login.attempt') }}" class="card" autocomplete="off">
        @csrf
        <label for="email">Email</label>
        <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>
        <label for="password">Password</label>
        <input id="password" type="password" name="password" required autocomplete="current-password">
        <div><button type="submit">Sign in</button></div>
    </form>
@endsection
