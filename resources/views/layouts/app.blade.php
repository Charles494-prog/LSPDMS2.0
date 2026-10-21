@php
    $authenticatedUser = $authenticatedUser ?? [];
    $icons = [
        'home' => '<path d="M3 10.5 10 4l7 6.5"/><path d="M5 9v7h10V9"/>',
        'grid' => '<rect x="3" y="3" width="6" height="6" rx="1"/><rect x="11" y="3" width="6" height="6" rx="1"/><rect x="3" y="11" width="6" height="6" rx="1"/><rect x="11" y="11" width="6" height="6" rx="1"/>',
        'file' => '<path d="M6 2h6l4 4v12H6z"/><path d="M12 2v4h4"/><path d="M8 11h6M8 14h6"/>',
        'user-x' => '<circle cx="8" cy="7" r="3"/><path d="M2 17c0-3 2.5-5 6-5"/><path d="M14 8l4 4M18 8l-4 4"/>',
        'shield' => '<path d="M10 2l7 3v5c0 4.5-3 7.5-7 8-4-.5-7-3.5-7-8V5l7-3z"/>',
        'profile' => '<circle cx="10" cy="7" r="3.2"/><path d="M3.5 17c1-3.2 3.6-5 6.5-5s5.5 1.8 6.5 5"/>',
        'logout' => '<path d="M8 17H4V3h4"/><path d="M13 13l4-3-4-3"/><path d="M17 10H8"/>',
        'search-edit' => '<circle cx="8.5" cy="8.5" r="5.5"/><path d="M12.5 12.5 17 17"/><path d="M12 15.5l2-2 1.5 1.5-2 2H12v-1.5z"/>',
        'filter' => '<path d="M3 4h14l-5.5 6.5v5l-3 1.5v-6.5L3 4z"/>',
    ];
    $role = mb_strtolower(trim(preg_replace('/\s+/', ' ', (string) ($authenticatedUser['tungkulin'] ?? '')) ?? ''));
    $isHeadSecretary = in_array($role, ['head secretary', 'secretary head'], true);
@endphp
<!DOCTYPE html>
<html lang="en" data-theme="{{ (($authenticatedUser['dark_mode'] ?? false)) ? 'dark' : 'light' }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>@yield('title', 'Dashboard') - {{ config('app.name', 'LSPDMS') }}</title>
        @include('components.brand-meta')
        <style>
            :root {
                color-scheme: light;
                --bg: #f6f8fa;
                --surface: #ffffff;
                --border: #e3e8ee;
                --text: #101828;
                --text-muted: #64748b;
                --accent: #0a6bd6;
                --accent-hover: #0a4a8a;
                --sidebar-bg: #101828;
                --sidebar-text: #cbd5e1;
                --sidebar-text-active: #ffffff;
                --sidebar-active-bg: rgba(255,255,255,0.08);
                --danger: #dc2626;
                --sidebar-width: 268px;
                --sidebar-width-collapsed: 76px;

            }
            html[data-theme="dark"] {
                color-scheme: dark;
                --bg: #0b1220;
                --surface: #141b2d;
                --border: #263043;
                --text: #e5e9f0;
                --text-muted: #93a0b4;
                --accent: #3b93ff;
                --accent-hover: #63acff;
                --danger: #f87171;
                --sidebar-bg: #0a0f1a;
                --sidebar-text: #93a0b4;
                --sidebar-text-active: #ffffff;
                --sidebar-active-bg: rgba(255,255,255,0.06);

            }
            * { box-sizing: border-box; }
            body {
                margin: 0;
                font-family: -apple-system, 'Helvetica Neue', Arial, sans-serif;
                background: var(--bg);
                color: var(--text);
                display: flex;
                min-height: 100vh;
            }

            /* Sidebar */
            .sidebar {
                width: var(--sidebar-width);
                flex-shrink: 0;
                background: var(--sidebar-bg);
                position: fixed;
                top: 0; bottom: 0; left: 0;
                display: flex;
                flex-direction: column;
                transform: translateX(0);
                transition: transform 0.2s ease, width 0.2s ease;
                z-index: 40;
                box-shadow: 1px 0 0 rgba(255,255,255,0.04) inset, 4px 0 24px rgba(0,0,0,0.18);
            }
            .sidebar.collapsed { width: var(--sidebar-width-collapsed); }
            .sidebar-top {
                position: relative;
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 1.3rem 1.2rem 1.1rem;
                border-bottom: 1px solid rgba(255,255,255,0.08);
                margin-bottom: 0.6rem;
            }
            .sidebar-brand { display: flex; align-items: center; flex: 1; min-width: 0; overflow: hidden; }
            .sidebar-brand img { display: block; width: 132px; max-width: 100%; max-height: 62px; object-fit: contain; object-position: left center; }
            .sidebar.collapsed .sidebar-brand { display: none; }
            .collapse-btn {
                background: rgba(255,255,255,0.06);
                border: none;
                width: 30px; height: 30px;
                border-radius: 7px;
                color: var(--sidebar-text);
                display: flex; align-items: center; justify-content: center;
                cursor: pointer;
                flex-shrink: 0;
            }
            .collapse-btn:hover { background: rgba(255,255,255,0.12); color: #fff; }
            .collapse-btn svg { transition: transform 0.2s ease; }
            .sidebar.collapsed .collapse-btn svg { transform: rotate(180deg); }

            .sidebar nav { position: relative; flex: 1; overflow-y: auto; overflow-x: hidden; padding: 0.4rem 0.9rem; }
            .sidebar a {
                display: flex;
                align-items: center;
                gap: 0.85rem;
                padding: 0.8rem 0.9rem;
                margin-bottom: 0.25rem;
                border-radius: 10px;
                color: var(--sidebar-text);
                text-decoration: none;
                font-size: 0.95rem;
                font-weight: 500;
                white-space: nowrap;
                transition: background 0.15s ease, color 0.15s ease, box-shadow 0.15s ease;
            }
            .sidebar a svg { flex-shrink: 0; width: 21px; height: 21px; }
            .sidebar a:hover { background: var(--sidebar-active-bg); color: var(--sidebar-text-active); }
            .sidebar a.active {
                background: var(--accent);
                color: #ffffff;
            }
            .sidebar.collapsed a span.label { display: none; }
            .sidebar.collapsed a { justify-content: center; padding: 0.8rem; }

            .sidebar-section-label {
                position: relative;
                font-size: 0.68rem;
                text-transform: uppercase;
                letter-spacing: 0.08em;
                color: #56617a;
                padding: 0.9rem 0.9rem 0.4rem;
                font-weight: 700;
            }
            .sidebar.collapsed .sidebar-section-label { display: none; }
            .sidebar-admin { position: relative; border-top: 1px solid rgba(255,255,255,0.08); padding: 0.4rem 0.9rem 1rem; }

            /* Main area */
            .main { margin-left: var(--sidebar-width); flex: 1; min-width: 0; display: flex; flex-direction: column; transition: margin-left 0.2s ease; }
            .main.collapsed { margin-left: var(--sidebar-width-collapsed); }
            .has-messages-page { height: 100vh; overflow: hidden; }
            .has-messages-page .main { height: 100vh; min-height: 0; overflow: hidden; }
            .has-messages-page .topbar { flex: 0 0 auto; }
            .has-messages-page .content { display: flex; min-height: 0; overflow: hidden; }
            .topbar {
                position: sticky;
                top: 0;
                z-index: 20;
                background: var(--surface);
                border-bottom: 1px solid var(--border);
                padding: 0.9rem 1.5rem;
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 1rem;
                box-shadow: 0 1px 0 rgba(15,23,42,0.04), 0 6px 18px rgba(15,23,42,0.05);
            }
            .topbar-title { font-weight: 700; font-size: 1.05rem; }
            .menu-toggle {
                display: none;
                background: none;
                border: none;
                cursor: pointer;
                padding: 0.4rem;
                color: var(--text);
            }

            /* User menu */
            .user-menu { position: relative; }
            .user-chip {
                display: flex; align-items: center; gap: 0.6rem;
                background: none; border: none; cursor: pointer;
                padding: 0.3rem 0.5rem 0.3rem 0.3rem;
                border-radius: 999px;
                color: inherit;
            }
            .user-chip:hover { background: var(--bg); }
            .user-chip img { width: 36px; height: 36px; border-radius: 50%; object-fit: cover; background: var(--border); }
            .user-chip-name { font-size: 0.85rem; font-weight: 600; line-height: 1.2; text-align: left; }
            .user-chip-role { font-size: 0.72rem; color: var(--text-muted); text-align: left; }

            .user-dropdown {
                position: absolute;
                top: calc(100% + 8px);
                right: 0;
                width: 220px;
                background: var(--surface);
                border: 1px solid var(--border);
                border-radius: 12px;
                box-shadow: 0 12px 28px rgba(15,23,42,0.14);
                padding: 0.4rem;
                display: none;
                z-index: 50;
            }
            .user-dropdown.open { display: block; }
            .user-dropdown a, .user-dropdown button {
                display: flex; align-items: center; gap: 0.7rem;
                width: 100%; text-align: left;
                background: none; border: none; cursor: pointer;
                padding: 0.65rem 0.7rem;
                border-radius: 8px;
                font-size: 0.88rem;
                color: var(--text);
                text-decoration: none;
                font-family: inherit;
            }
            .user-dropdown a:hover, .user-dropdown button:hover { background: var(--bg); }
            .user-dropdown svg { width: 17px; height: 17px; flex-shrink: 0; color: var(--text-muted); }
            .user-dropdown .divider { height: 1px; background: var(--border); margin: 0.3rem 0.2rem; }
            .theme-switch-row { display:flex; align-items:center; justify-content:space-between; gap:.75rem; padding:.55rem .7rem; color:var(--text); font-size:.86rem; font-weight:600; }
            .theme-switch { position:relative; display:inline-flex; width:42px; height:24px; flex:none; }
            .theme-switch input { position:absolute; width:1px; height:1px; opacity:0; }
            .theme-switch-track { position:absolute; inset:0; border:1px solid var(--border); border-radius:999px; background:var(--border); cursor:pointer; transition:background .15s,border-color .15s; }
            .theme-switch-track::before { position:absolute; top:2px; left:2px; width:18px; height:18px; border-radius:50%; background:var(--surface); content:""; transition:transform .15s; }
            .theme-switch input:checked + .theme-switch-track { border-color:var(--accent); background:var(--accent); }
            .theme-switch input:checked + .theme-switch-track::before { transform:translateX(18px); }
            .theme-switch input:focus-visible + .theme-switch-track { outline:3px solid color-mix(in srgb,var(--accent) 35%,transparent); outline-offset:2px; }
            .theme-switch-status { padding:0 .7rem .45rem; color:var(--text-muted); font-size:.72rem; }
            .user-dropdown button.logout-item { color: var(--danger); }
            .user-dropdown button.logout-item svg { color: var(--danger); }

            .content { padding: 1.6rem; flex: 1; }
            .chat-widget {
                position: fixed;
                right: 24px;
                bottom: 24px;
                z-index: 60;
                display: flex;
                flex-direction: column;
                align-items: flex-end;
                gap: 0.8rem;
            }
            .chat-window {
                display: flex;
                width: min(390px, calc(100vw - 32px));
                height: min(560px, calc(100dvh - 112px));
                min-height: min(360px, calc(100dvh - 112px));
                flex-direction: column;
                overflow: hidden;
                border: 1px solid var(--border);
                border-radius: 16px;
                background: var(--surface);
                box-shadow: 0 18px 55px rgba(15, 23, 42, 0.25);
            }
            .chat-window[hidden] { display: none; }
            .chat-window-header {
                display: flex;
                flex: none;
                align-items: center;
                justify-content: space-between;
                min-height: 52px;
                padding: 0.5rem 0.75rem 0.5rem 1rem;
                border-bottom: 1px solid var(--border);
                color: var(--text);
                font-size: 0.9rem;
                font-weight: 700;
            }
            .chat-window-minimize {
                display: grid;
                width: 34px;
                height: 34px;
                place-items: center;
                border: 0;
                border-radius: 50%;
                background: transparent;
                color: var(--text-muted);
                cursor: pointer;
                font: inherit;
                font-size: 1.3rem;
            }
            .chat-window-minimize:hover { background: var(--bg); color: var(--text); }
            .chat-window iframe { width: 100%; height: 0; min-height: 0; flex: 1; border: 0; background: var(--bg); }
            .chat-head-launcher {
                display: grid;
                width: 60px;
                height: 60px;
                place-items: center;
                border: 2px solid var(--surface);
                border-radius: 50%;
                background: var(--accent);
                box-shadow: 0 2px 8px rgba(10, 107, 214, 0.22);
                color: #fff;
                cursor: pointer;
                transition: transform 0.18s ease, box-shadow 0.18s ease, opacity 0.18s ease;
            }
            .chat-head-launcher:hover {
                transform: translateY(-2px);
                box-shadow: 0 14px 32px rgba(10, 107, 214, 0.42);
            }
            .chat-head-launcher svg {
                width: 30px;
                height: 30px;
            }
            .chat-head-launcher[aria-expanded="true"] { opacity: 0.86; }
            .chat-unread-badge {
                position: absolute;
                top: -3px;
                right: -3px;
                display: grid;
                min-width: 23px;
                height: 23px;
                place-items: center;
                padding: 0 5px;
                border: 2px solid var(--bg);
                border-radius: 999px;
                background: #e02424;
                color: #fff;
                font-size: 0.68rem;
                font-weight: 800;
                line-height: 1;
                box-shadow: 0 2px 6px rgba(127, 29, 29, 0.35);
            }
            .chat-unread-badge[hidden] { display: none; }
            .has-smart-helper .chat-widget { bottom: 94px; }
            .has-smart-helper .chat-window { height: min(560px, calc(100dvh - 190px)); }

            .overlay {
                display: none;
                position: fixed; inset: 0;
                background: rgba(0,0,0,0.4);
                z-index: 30;
            }

            @media (max-width: 900px) {
                .sidebar { transform: translateX(-100%); width: var(--sidebar-width) !important; }
                .sidebar.open { transform: translateX(0); }
                .main, .main.collapsed { margin-left: 0; }
                .menu-toggle { display: inline-flex; }
                .overlay.open { display: block; }
                .collapse-btn { display: none; }
                .sidebar.collapsed a span.label { display: inline; }
                .sidebar.collapsed a { justify-content: flex-start; padding: 0.8rem 0.9rem; }
                .sidebar.collapsed .sidebar-brand { display: block; }
            }
            @media (max-width: 600px) {
                .chat-widget { right: 12px; bottom: 16px; }
                .has-smart-helper .chat-widget { bottom: 84px; }
                .chat-window { width: calc(100vw - 24px); height: min(560px, calc(100dvh - 112px)); }
                .has-smart-helper .chat-window { height: min(560px, calc(100dvh - 190px)); }
                .chat-head-launcher { width: 56px; height: 56px; }
            }
        </style>
        @stack('styles')
    </head>
    <body class="{{ request()->routeIs('inquire-modify') ? 'has-smart-helper' : '' }}{{ request()->routeIs('messages.*') ? ' has-messages-page' : '' }}">
        <x-flash-toast :message="session('status')" />
        <div class="overlay" id="sidebarOverlay"></div>

        <aside class="sidebar" id="sidebar">
            <div class="sidebar-top">
                <a class="sidebar-brand" href="{{ route('home') }}" aria-label="LSPDMS home">
                    <img src="{{ asset('images/LSP_logo_white.png') }}" alt="LSPDMS — LSP's Data Management System">
                </a>
                <button class="collapse-btn" id="collapseToggle" aria-label="Collapse sidebar">
                    <svg width="16" height="16" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 4l-6 6 6 6"/></svg>
                </button>
            </div>

            @php
                $icons = [
                    'home' => '<path d="M3 10.5 10 4l7 6.5"/><path d="M5 9v7h10V9"/>',
                    'grid' => '<rect x="3" y="3" width="6" height="6" rx="1"/><rect x="11" y="3" width="6" height="6" rx="1"/><rect x="3" y="11" width="6" height="6" rx="1"/><rect x="11" y="11" width="6" height="6" rx="1"/>',
                    'file' => '<path d="M6 2h6l4 4v12H6z"/><path d="M12 2v4h4"/><path d="M8 11h6M8 14h6"/>',
                    'user-x' => '<circle cx="8" cy="7" r="3"/><path d="M2 17c0-3 2.5-5 6-5"/><path d="M14 8l4 4M18 8l-4 4"/>',
                    'shield' => '<path d="M10 2l7 3v5c0 4.5-3 7.5-7 8-4-.5-7-3.5-7-8V5l7-3z"/>',
                    'profile' => '<circle cx="10" cy="7" r="3.2"/><path d="M3.5 17c1-3.2 3.6-5 6.5-5s5.5 1.8 6.5 5"/>',
                    'logout' => '<path d="M8 17H4V3h4"/><path d="M13 13l4-3-4-3"/><path d="M17 10H8"/>',
                    'search-edit' => '<circle cx="8.5" cy="8.5" r="5.5"/><path d="M12.5 12.5 17 17"/><path d="M12 15.5l2-2 1.5 1.5-2 2H12v-1.5z"/>',
                    'filter' => '<path d="M3 4h14l-5.5 6.5v5l-3 1.5v-6.5L3 4z"/>',
                ];
                $role = mb_strtolower(trim(preg_replace('/\s+/', ' ', (string) ($authenticatedUser['tungkulin'] ?? '')) ?? ''));
                $isHeadSecretary = in_array($role, ['head secretary', 'secretary head'], true);

            @endphp

            <nav>
                <a href="{{ route('home') }}" class="{{ request()->routeIs('home') ? 'active' : '' }}">
                    <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">{!! $icons['home'] !!}</svg>
                    <span class="label">Home</span>
                </a>

                @if ($isHeadSecretary || ($authenticatedUser['dashboard_allow'] ?? false))
                    <a data-feature="dashboard_allow" href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">
                        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">{!! $icons['grid'] !!}</svg>
                        <span class="label">Dashboard</span>
                    </a>
                @endif

                @if ($isHeadSecretary || ($authenticatedUser['form_generator_allow'] ?? false))
                    <a data-feature="form_generator_allow" href="{{ route('form-generator') }}" class="{{ request()->routeIs('form-generator') ? 'active' : '' }}">
                        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">{!! $icons['file'] !!}</svg>
                        <span class="label">Form Generator</span>
                    </a>
                @endif

                @if ($isHeadSecretary || ($authenticatedUser['Inquire_Allow'] ?? false))
                    <a data-feature="Inquire_Allow" href="{{ route('inquire-modify') }}" class="{{ request()->routeIs('inquire-modify') ? 'active' : '' }}">
                        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">{!! $icons['search-edit'] !!}</svg>
                        <span class="label">Inquire / Modify</span>
                    </a>
                @endif
                @if ($isHeadSecretary || ($authenticatedUser['tarheta_status_allow'] ?? false))
                    <a data-feature="tarheta_status_allow" href="{{ route('tarheta-status') }}" class="{{ request()->routeIs('tarheta-status*') ? 'active' : '' }}">
                        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">{!! $icons['search-edit'] !!}</svg>
                        <span class="label">Tarheta Status Checker</span>
                    </a>
                @endif
                @if ($isHeadSecretary || ($authenticatedUser['filter_bnh_helper_allow'] ?? false))
                    <a data-feature="filter_bnh_helper_allow" href="{{ route('filter-bnh-helper') }}" class="{{ request()->routeIs('filter-bnh-helper*') ? 'active' : '' }}">
                        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">{!! $icons['filter'] !!}</svg>
                        <span class="label">Filter &amp; BNH Helper</span>
                    </a>
                @endif

                @if ($isHeadSecretary || ($authenticatedUser['Delistment_allow'] ?? false))
                    <a data-feature="Delistment_allow" href="{{ route('de-listment') }}" class="{{ request()->routeIs('de-listment') ? 'active' : '' }}">
                        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">{!! $icons['user-x'] !!}</svg>
                        <span class="label">De-Listment</span>
                    </a>
                @endif
            </nav>

            @if ($isHeadSecretary)
                <div class="sidebar-admin" data-feature="head_secretary">
                    <div class="sidebar-section-label">Administration</div>
                    <a href="{{ route('superadmin-dashboard') }}" class="{{ request()->routeIs('superadmin-dashboard') ? 'active' : '' }}">
                        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">{!! $icons['shield'] !!}</svg>
                        <span class="label">Superadmin Dashboard</span>
                    </a>
                </div>
            @endif
        </aside>

        <div class="main" id="mainArea">
            <header class="topbar">
                <div style="display:flex; align-items:center; gap:0.8rem;">
                    <button class="menu-toggle" id="menuToggle" aria-label="Open menu">
                        <svg width="22" height="22" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M3 5h14M3 10h14M3 15h14"/></svg>
                    </button>
                    <span class="topbar-title">@yield('title', 'Dashboard')</span>
                </div>

                @php
                    $displayName = trim(($authenticatedUser['first_name'] ?? '') . ' ' . ($authenticatedUser['last_name'] ?? ''));
                    $displayName = $displayName !== '' ? $displayName : ($authenticatedUser['gmail'] ?? 'User');
                @endphp

                <div class="user-menu">
                    <button class="user-chip" id="userChipBtn">
                        <img src="{{ !empty($authenticatedUser['profile_picture']) ? asset('storage/' . $authenticatedUser['profile_picture']) : asset('images/avatar-default.svg') }}" alt="">
                        <span>
                            <div class="user-chip-name">{{ $displayName }}</div>
                            <div class="user-chip-role">{{ $authenticatedUser['tungkulin'] ?? '' }}</div>
                        </span>
                    </button>

                    <div class="user-dropdown" id="userDropdown">
                        <a href="{{ route('profile') }}">
                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">{!! $icons['profile'] !!}</svg>
                            Profile
                        </a>
                        <div class="theme-switch-row">
                            <span id="themeSwitchLabel">Dark mode</span>
                            <label class="theme-switch" aria-label="Toggle dark mode">
                                <input type="checkbox" id="themeSwitch" role="switch" aria-labelledby="themeSwitchLabel" @checked(($authenticatedUser['dark_mode'] ?? false))>
                                <span class="theme-switch-track"></span>
                            </label>
                        </div>
                        <div class="theme-switch-status" id="themeSwitchStatus" role="status" aria-live="polite"></div>
                        <div class="divider"></div>
                        <form method="POST" action="{{ url('/logout') }}">
                            @csrf
                            <button type="submit" class="logout-item">
                                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">{!! $icons['logout'] !!}</svg>
                                Log out
                            </button>
                        </form>
                    </div>
                </div>
            </header>

            <main class="content">
                @yield('content')
            </main>
        </div>

        @unless (request()->routeIs('messages.*'))
            <section class="chat-widget" id="chatWidget"
                data-unread-url="{{ route('messages.unread-count') }}"
                data-user-id="{{ $authenticatedUser['id'] }}"
                data-auth-url="{{ route('broadcasting.auth') }}"
                data-csrf="{{ csrf_token() }}"
                data-reverb-configured="{{ config('broadcasting.default') === 'reverb' && filled(config('broadcasting.connections.reverb.key')) && filled(config('broadcasting.connections.reverb.secret')) && filled(config('broadcasting.connections.reverb.app_id')) ? 'true' : 'false' }}"
                aria-label="Direct messages">
                <div class="chat-window" id="chatWindow" role="dialog" aria-label="Direct messages" hidden>
                    <header class="chat-window-header">
                        <span>Chats</span>
                        <button class="chat-window-minimize" id="chatMinimize" type="button" aria-label="Minimize chat" title="Minimize">&minus;</button>
                    </header>
                    <iframe title="Direct messages" data-src="{{ route('messages.index', ['widget' => 1]) }}" loading="lazy"></iframe>
                </div>
                <button class="chat-head-launcher" id="chatLauncher" type="button" aria-label="Open messages" aria-controls="chatWindow" aria-expanded="false">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M20 11.5a7.5 7.5 0 0 1-7.5 7.5H5l1.8-3.2A7.5 7.5 0 1 1 20 11.5Z"/>
                        <path d="M8.5 11.5h7M8.5 14.5h4"/>
                    </svg>
                    <span class="chat-unread-badge" id="chatUnreadBadge" aria-live="polite" aria-atomic="true" hidden></span>
                </button>
            </section>
        @endunless

        <script>
            const sidebar = document.getElementById('sidebar');
            const mainArea = document.getElementById('mainArea');
            const overlay = document.getElementById('sidebarOverlay');
            const menuToggle = document.getElementById('menuToggle');
            const collapseToggle = document.getElementById('collapseToggle');
            const userChipBtn = document.getElementById('userChipBtn');
            const userDropdown = document.getElementById('userDropdown');
            const chatLauncher = document.getElementById('chatLauncher');
            const chatWindow = document.getElementById('chatWindow');
            const chatMinimize = document.getElementById('chatMinimize');
            const chatFrame = chatWindow?.querySelector('iframe');

            function notifyChatPanelOpened() {
                if (!chatWindow || chatWindow.hidden || !chatFrame?.contentWindow) return;
                chatFrame.contentWindow.postMessage({ type: 'chat:panel-opened' }, window.location.origin);
            }

            function setChatOpen(isOpen) {
                if (!chatWindow || !chatLauncher) return;
                chatWindow.hidden = !isOpen;
                chatLauncher.setAttribute('aria-expanded', String(isOpen));
                chatLauncher.setAttribute('aria-label', isOpen ? 'Minimize messages' : 'Open messages');
                if (isOpen && chatFrame && !chatFrame.hasAttribute('src')) chatFrame.src = chatFrame.dataset.src;
                else if (isOpen) notifyChatPanelOpened();
            }

            chatFrame?.addEventListener('load', notifyChatPanelOpened);
            chatLauncher?.addEventListener('click', () => setChatOpen(chatWindow.hidden));
            chatMinimize?.addEventListener('click', () => {
                setChatOpen(false);
                chatLauncher?.focus();
            });
            const themeSwitch = document.getElementById('themeSwitch');
            const themeSwitchStatus = document.getElementById('themeSwitchStatus');
            let themeSaveController = null;
            themeSwitch?.addEventListener('change', async () => {
                themeSaveController?.abort();
                themeSaveController = new AbortController();
                const darkMode = themeSwitch.checked;
                const previousTheme = document.documentElement.dataset.theme;
                document.documentElement.dataset.theme = darkMode ? 'dark' : 'light';
                const chatDocument = chatFrame?.contentDocument;
                if (chatDocument?.documentElement) chatDocument.documentElement.dataset.theme = darkMode ? 'dark' : 'light';
                themeSwitch.disabled = true;
                themeSwitchStatus.textContent = 'Saving…';
                try {
                    const response = await fetch(@json(route('settings.update')), {
                        method: 'PUT',
                        credentials: 'same-origin',
                        signal: themeSaveController.signal,
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                        body: JSON.stringify({ dark_mode: darkMode ? 1 : 0 }),
                    });
                    const result = await response.json().catch(() => ({}));
                    if (!response.ok) throw new Error(result.message || 'Could not save the theme preference.');
                    themeSwitchStatus.textContent = 'Saved';
                } catch (error) {
                    document.documentElement.dataset.theme = previousTheme;
                    if (chatDocument?.documentElement) chatDocument.documentElement.dataset.theme = previousTheme;
                    themeSwitch.checked = previousTheme === 'dark';
                    themeSwitchStatus.textContent = error.name === 'AbortError' ? '' : error.message;
                } finally {
                    themeSwitch.disabled = false;
                }
            });
            document.addEventListener('keydown', event => {
                if (event.key === 'Escape' && chatWindow && !chatWindow.hidden) setChatOpen(false);
            });

            function openSidebar() {
                sidebar.classList.add('open');
                overlay.classList.add('open');
            }
            function closeSidebar() {
                sidebar.classList.remove('open');
                overlay.classList.remove('open');
            }

            menuToggle?.addEventListener('click', openSidebar);
            overlay?.addEventListener('click', closeSidebar);

            // Desktop collapse, persisted across page loads
            try {
                if (localStorage.getItem('sidebarCollapsed') === '1') {
                    sidebar.classList.add('collapsed');
                    mainArea.classList.add('collapsed');
                }
            } catch (e) {}

            collapseToggle?.addEventListener('click', () => {
                const isCollapsed = sidebar.classList.toggle('collapsed');
                mainArea.classList.toggle('collapsed', isCollapsed);
                try { localStorage.setItem('sidebarCollapsed', isCollapsed ? '1' : '0'); } catch (e) {}
            });

            // User dropdown
            userChipBtn?.addEventListener('click', (e) => {
                e.stopPropagation();
                userDropdown.classList.toggle('open');
            });
            document.addEventListener('click', (e) => {
                if (!userDropdown.contains(e.target) && e.target !== userChipBtn) {
                    userDropdown.classList.remove('open');
                }
            });
        </script>
        <script>
            (() => {
                const checkSession = async () => {
                    try {
                        const response = await fetch(@json(route('session.status')), {
                            headers: { 'Accept': 'application/json' },
                            cache: 'no-store',
                            credentials: 'same-origin',
                        });
                        if (response.redirected) window.location.assign(response.url);
                        else if (!response.ok) {
                            const result = await response.json().catch(() => ({}));
                            const reason = result.reason === 'password_changed' ? 'password_changed' : 'revoked';
                            window.location.assign(`/login?access=${reason}`);
                        }
                        else {
                            const { permissions } = await response.json();
                            document.querySelectorAll('[data-feature]').forEach(link => {
                                const allowed = Boolean(permissions[link.dataset.feature]);
                                link.hidden = !allowed;
                                link.style.display = allowed ? '' : 'none';
                            });
                            const currentFeature = Object.entries({
                                '/dashboard': 'dashboard_allow',
                                '/de-listment': 'Delistment_allow',
                                '/form-generator': 'form_generator_allow',
                                '/forms/monthly': 'form_generator_allow',
                                '/forms/weekly': 'form_generator_allow',
                                '/inquire-modify': 'Inquire_Allow',
                                '/tarheta-status': 'tarheta_status_allow',
                                '/filter-bnh-helper': 'filter_bnh_helper_allow',
                                '/superadmin-dashboard': 'head_secretary',
                            }).find(([path]) => window.location.pathname === path)?.[1];
                            if (currentFeature && !permissions[currentFeature]) window.location.assign('/home');
                        }
                    } catch (error) {
                        console.error('Session access check failed:', error);
                    }
                };
                window.setInterval(() => {
                    if (!document.hidden) checkSession();
                }, 10000);
            })();
        </script>
        @stack('scripts')
        @unless (request()->routeIs('messages.*'))
            @vite('resources/js/chat-notifications.js')
        @endunless
    </body>
</html>