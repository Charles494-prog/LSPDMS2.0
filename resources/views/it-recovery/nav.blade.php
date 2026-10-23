<header>
    <h1>@yield('title', 'IT Recovery')</h1>
    <nav>
        <span class="muted">{{ $account->email }}</span>
        <a href="{{ route('it-recovery.dashboard') }}">Actions</a>
        <a href="{{ route('it-recovery.audit') }}">Audit log</a>
        <form method="POST" action="{{ route('it-recovery.logout') }}">
            @csrf
            <button type="submit" class="link">Sign out</button>
        </form>
    </nav>
</header>
