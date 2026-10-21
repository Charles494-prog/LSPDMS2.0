@extends('layouts.app')

@section('title', 'User Settings')

@section('content')
<style>
    .settings-page { width:min(100%,760px); margin:0 auto; color:var(--text); }
    .settings-heading { margin-bottom:1.4rem; }
    .settings-heading h1 { margin:0; font-size:clamp(1.45rem,3vw,1.9rem); letter-spacing:-.03em; }
    .settings-heading p { margin:.5rem 0 0; color:var(--text-muted); line-height:1.6; }
    .settings-card { padding:clamp(1rem,3vw,1.5rem); border:1px solid var(--border); border-radius:14px; background:var(--surface); }
    .settings-card h2 { margin:0; font-size:1rem; }
    .settings-card > p { margin:.4rem 0 1.2rem; color:var(--text-muted); font-size:.88rem; line-height:1.55; }
    .settings-options { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:.8rem; border:0; margin:0 0 1.2rem; padding:0; }
    .settings-page .sr-only { position:absolute; width:1px; height:1px; padding:0; margin:-1px; overflow:hidden; clip:rect(0,0,0,0); white-space:nowrap; border:0; }
    .settings-choice { display:flex; align-items:flex-start; gap:.7rem; padding:.9rem; border:1px solid var(--border); border-radius:10px; cursor:pointer; }
    .settings-choice:has(input:checked) { border-color:var(--accent); background:color-mix(in srgb,var(--accent) 6%,var(--surface)); }
    .settings-choice input { margin:.15rem 0 0; accent-color:var(--accent); }
    .settings-choice strong { display:block; font-size:.9rem; }
    .settings-choice span { display:block; margin-top:.2rem; color:var(--text-muted); font-size:.8rem; line-height:1.45; }
    .settings-actions { display:flex; justify-content:flex-end; }
    .settings-save { min-height:42px; padding:.65rem 1rem; border:0; border-radius:8px; background:var(--accent); color:#fff; font:inherit; font-weight:700; cursor:pointer; }
    .settings-save:hover { background:var(--accent-hover); }
    .settings-save:focus-visible,.settings-choice input:focus-visible { outline:3px solid color-mix(in srgb,var(--accent) 38%,transparent); outline-offset:2px; }
    @media(max-width:520px) { .settings-options { grid-template-columns:1fr; } .settings-actions .settings-save { width:100%; } }
</style>

<main class="settings-page">
    <header class="settings-heading">
        <h1>User Settings</h1>
        <p>Choose how LSPDMS looks on your account. Your preference is saved and used the next time you open a page.</p>
    </header>

    <form class="settings-card" method="POST" action="{{ route('settings.update') }}">
        @csrf
        @method('PUT')
        <h2>Appearance</h2>
        <p>Select a theme for your workspace.</p>

        <fieldset class="settings-options">
            <legend class="sr-only">Color theme</legend>
            <label class="settings-choice">
                <input type="radio" name="dark_mode" value="0" @checked(!($user['dark_mode'] ?? false))>
                <span><strong>Light mode</strong><span>Use the light workspace theme.</span></span>
            </label>
            <label class="settings-choice">
                <input type="radio" name="dark_mode" value="1" @checked((bool) ($user['dark_mode'] ?? false))>
                <span><strong>Dark mode</strong><span>Use the dark workspace theme.</span></span>
            </label>
        </fieldset>

        <div class="settings-actions">
            <button class="settings-save" type="submit">Save settings</button>
        </div>
    </form>
</main>
@endsection
