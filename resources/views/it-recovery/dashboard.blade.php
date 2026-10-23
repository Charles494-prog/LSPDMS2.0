@extends('it-recovery.layout')

@section('title', 'IT Recovery Actions')

@section('content')
    @include('it-recovery.nav')

    <div class="card warning">
        Each action asks for your IT recovery password again and is written to the IT audit log.
        Sign out when you are done; the session ends after 15 minutes idle or 2 hours in total.
    </div>

    <section class="card">
        <h2>1. Turn off email-dependent security features</h2>
        <p>Switches off <strong>Require OTP</strong> and <strong>Allow Forgot Password</strong> so everyone can sign in with a password only while email (SMTP) is unavailable.</p>
        <form method="POST" action="{{ route('it-recovery.actions.disable-email-features') }}" autocomplete="off">
            @csrf
            <label for="it_password_email">Your IT recovery password</label>
            <input id="it_password_email" type="password" name="it_password" required autocomplete="off">
            <div><button type="submit">Turn off OTP and Forgot Password</button></div>
        </form>
    </section>

    <section class="card">
        <h2>2. Reset an account password</h2>
        <p>Sets a random temporary password, shown once on the next page, and signs that account out on every device.</p>
        <form method="POST" action="{{ route('it-recovery.actions.reset-password') }}" autocomplete="off">
            @csrf
            <label for="reset_user_id">Account</label>
            <select id="reset_user_id" name="user_id" required>
                @foreach ($users as $user)
                    <option value="{{ $user->id }}">#{{ $user->id }} · {{ trim($user->first_name.' '.$user->last_name) }} · {{ $user->gmail }} · {{ $user->tungkulin }}</option>
                @endforeach
            </select>
            <label for="it_password_reset">Your IT recovery password</label>
            <input id="it_password_reset" type="password" name="it_password" required autocomplete="off">
            <div><button type="submit">Reset password</button></div>
        </form>
    </section>

    <section class="card">
        <h2>3. Re-enable a disabled account</h2>
        <form method="POST" action="{{ route('it-recovery.actions.reenable-account') }}" autocomplete="off">
            @csrf
            <label for="enable_user_id">Account</label>
            <select id="enable_user_id" name="user_id" required>
                @foreach ($users as $user)
                    <option value="{{ $user->id }}">#{{ $user->id }} · {{ trim($user->first_name.' '.$user->last_name) }} · {{ $user->gmail }} · {{ $user->is_verified ? 'enabled' : 'disabled' }}</option>
                @endforeach
            </select>
            <label for="it_password_enable">Your IT recovery password</label>
            <input id="it_password_enable" type="password" name="it_password" required autocomplete="off">
            <div><button type="submit">Re-enable account</button></div>
        </form>
    </section>

    <section class="card">
        <h2>LSPDMS accounts</h2>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>ID</th><th>Name</th><th>Gmail</th><th>Role</th><th>Verified</th></tr>
                </thead>
                <tbody>
                    @forelse ($users as $user)
                        <tr>
                            <td>{{ $user->id }}</td>
                            <td>{{ trim($user->first_name.' '.$user->last_name) }}</td>
                            <td>{{ $user->gmail }}</td>
                            <td>{{ $user->tungkulin }}</td>
                            <td>{{ $user->is_verified ? 'Yes' : 'No' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="muted">No accounts.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
