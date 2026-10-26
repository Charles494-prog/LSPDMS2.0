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
        'card' => '<rect x="2.5" y="4.5" width="15" height="11" rx="2"/><path d="M6 9h4M6 12h2.5"/><circle cx="14" cy="10" r="1.6"/>',
        'notes' => '<path d="M5 2.5h10a1.5 1.5 0 0 1 1.5 1.5v13l-3-1.5-3 1.5-3-1.5-3 1.5V4A1.5 1.5 0 0 1 5 2.5z"/><path d="M7 7h6M7 10h6M7 13h3"/>',
        'checklist' => '<rect x="3.5" y="2.5" width="13" height="15" rx="1.5"/><path d="M7 7h6M7 10.5h6M7 14h3"/><path d="m12.5 14 1 1 2-2.2"/>',
        'chevron' => '<path d="M7.5 5 12.5 10l-5 5"/>',
        'tasks' => '<path d="M7 5h10M7 10h10M7 15h6"/><path d="m2.5 5 1 1 1.5-1.8M2.5 10l1 1 1.5-1.8M2.5 15l1 1 1.5-1.8"/>',
    ];
    $role = mb_strtolower(trim(preg_replace('/\s+/', ' ', (string) ($authenticatedUser['tungkulin'] ?? '')) ?? ''));
    $isHeadSecretary = in_array($role, ['head secretary', 'secretary head'], true);
    $can = fn (?string $feature) => $feature === null || $isHeadSecretary || ($authenticatedUser[$feature] ?? false);

    // Same destinations and permission keys as before, grouped by purpose.
    // [route, label, icon, active pattern, permission key (null = everyone)]
    $navGroups = [
        ['label' => 'Overview', 'items' => [
            ['home', 'Home', 'home', 'home', null],
            ['dashboard', 'Dashboard', 'grid', 'dashboard', 'dashboard_allow'],
        ]],
        ['label' => 'Records', 'items' => [
            ['inquire-modify', 'Inquire / Modify', 'search-edit', 'inquire-modify', 'Inquire_Allow'],
            ['de-listment', 'De-Listment', 'user-x', 'de-listment', 'Delistment_allow'],
        ]],
        ['label' => 'Tools', 'items' => [
            ['note-checker', 'Note Checker', 'notes', 'note-checker', 'note_checker_allow'],
            ['tarheta-status', 'Tarheta Status Checker', 'card', 'tarheta-status*', 'tarheta_status_allow'],
            ['filter-bnh-helper', 'Filter & BNH Helper', 'filter', 'filter-bnh-helper*', 'filter_bnh_helper_allow'],
        ]],
    ];

    // Tasks (assigned by the Head Secretary) is for secretaries and the Head Secretary; no extra permission.
    $canUseTasks = $isHeadSecretary || $role === 'secretary';
    if ($canUseTasks) {
        $navGroups[0]['items'][] = ['tasks', 'Tasks', 'tasks', 'tasks', null];
    }

    // Form Generator submenus (all need form_generator_allow). Each child: [label, url, active?, breadcrumb trail].
    $isDeliberation = request()->routeIs('forms.monthly.deliberation');
    $isSubtract = $isDeliberation && request()->query('mode') === 'subtract';
    $formsHub = route('form-generator');
    $formMenus = [
        ['key' => 'generate', 'label' => 'Generate Forms', 'icon' => 'file', 'url' => $formsHub.'#generate-forms', 'sections' => [
            ['label' => 'Weekly', 'url' => $formsHub.'#generate-weekly', 'items' => [
                ['R4-02 Generator', route('forms.weekly.r4-02'), request()->routeIs('forms.weekly.r4-02*')],
                ['R4-03 Generator', route('forms.weekly.r4-03'), request()->routeIs('forms.weekly.r4-03*')],
            ]],
            ['label' => 'Monthly', 'url' => $formsHub.'#generate-monthly', 'items' => [
                ['Resumen & R4-01 (This Month)', route('forms.monthly.generators'), request()->routeIs('forms.monthly.generators', 'forms.monthly.resumen*', 'forms.monthly.r4-01', 'forms.monthly.r4-01.download')],
                ['R4-01 Snapshots', route('forms.monthly.r4-01.snapshots'), request()->routeIs('forms.monthly.r4-01.snapshots*')],
            ]],
        ]],
        ['key' => 'deliberation', 'label' => 'Deliberation Form', 'icon' => 'checklist', 'url' => $formsHub.'#deliberation', 'sections' => [
            ['label' => null, 'url' => null, 'items' => [
                ['Add', route('forms.monthly.deliberation'), $isDeliberation && ! $isSubtract, 'add'],
                ['Subtract', route('forms.monthly.deliberation', ['mode' => 'subtract']), $isSubtract, 'subtract'],
                ['Modify', route('forms.monthly.modify'), request()->routeIs('forms.monthly.modify*')],
            ]],
        ]],
    ];

    // Breadcrumb: Form Generator › menu › section › page, built from the menus above.
    $breadcrumbs = [];
    if (request()->routeIs('form-generator')) {
        $breadcrumbs = [['Form Generator', null]];
    } elseif (request()->routeIs('forms.monthly.session*')) {
        $breadcrumbs = [['Form Generator', $formsHub], ['Deliberation Form', $formsHub.'#deliberation'], ['Monthly Session', null]];
    } else {
        foreach ($formMenus as $menu) {
            foreach ($menu['sections'] as $section) {
                foreach ($section['items'] as $item) {
                    if ($item[2]) {
                        $breadcrumbs = array_values(array_filter([
                            ['Form Generator', $formsHub],
                            [$menu['label'], $menu['url']],
                            $section['label'] ? [$section['label'], $section['url']] : null,
                            [$item[0], null],
                        ]));
                    }
                }
            }
        }
    }
    $formMenuOpen = fn (array $menu): bool => collect($menu['sections'])->flatMap(fn ($section) => $section['items'])->contains(fn ($item) => $item[2])
        || (request()->routeIs('forms.monthly.session*') && $menu['key'] === 'deliberation');

    $displayName = trim(($authenticatedUser['first_name'] ?? '') . ' ' . ($authenticatedUser['last_name'] ?? ''));
    $displayName = $displayName !== '' ? $displayName : ($authenticatedUser['gmail'] ?? 'User');
    $initials = mb_strtoupper(mb_substr(trim((string) ($authenticatedUser['first_name'] ?? '')), 0, 1) . mb_substr(trim((string) ($authenticatedUser['last_name'] ?? '')), 0, 1));
    $initials = $initials !== '' ? $initials : mb_strtoupper(mb_substr($displayName, 0, 1));
    $avatarUrl = \App\Http\Controllers\ProfilePictureController::urlFor($authenticatedUser['id'] ?? null, $authenticatedUser['profile_picture'] ?? null);
@endphp
<!DOCTYPE html>
<html lang="en" data-theme="{{ (($authenticatedUser['dark_mode'] ?? false)) ? 'dark' : 'light' }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="lspdms-user" content="{{ (int) ($authenticatedUser['id'] ?? 0) }}:{{ (int) ($authenticatedUser['auth_version'] ?? 0) }}">
        {{-- Idle sign-out (resources/js/idle-session.js): limit in seconds and its endpoints. --}}
        <meta name="lspdms-idle" content="{{ \App\Support\IdleTimeout::seconds() }}" data-heartbeat-url="{{ route('session.heartbeat') }}" data-logout-url="{{ route('session.idle-logout') }}" data-signed-out-url="{{ route('session.signed-out') }}">
        <title>@yield('title', 'Dashboard') - {{ config('app.name', 'LSPDMS') }}</title>
        @include('components.brand-meta')
        {{-- Apply the saved sidebar state before first paint so the layout never jumps on load. --}}
        <script>try { if (localStorage.getItem('sidebarCollapsed') === '1') document.documentElement.classList.add('sb-collapsed'); } catch (e) {}</script>
        <style>
            :root {
                color-scheme: light;
                --bg: #fafafa;
                --surface: #ffffff;
                --border: #eaeaea;
                --text: #171717;
                --text-muted: #666666;
                --accent: #0070f3;
                --accent-hover: #0060d1;
                --danger: #e5484d;
                --hover: #f5f5f5;
                --sidebar-bg: #fafafa;
                --sidebar-text: #666666;
                --sidebar-label: #737373;
                --sidebar-text-active: #171717;
                --sidebar-active-bg: #eaeaea;
                --sidebar-hover-bg: #f2f2f2;
                --sidebar-border: #eaeaea;
                --sidebar-logo-filter: brightness(0);
                --sidebar-width: 256px;
                --sidebar-width-collapsed: 68px;
                --bar-height: 56px;
                --inset-top: env(safe-area-inset-top, 0px);
            }
            html[data-theme="dark"] {
                color-scheme: dark;
                --bg: #000000;
                --surface: #0a0a0a;
                --border: #262626;
                --text: #ededed;
                --text-muted: #a1a1a1;
                --accent: #3291ff;
                --accent-hover: #52a8ff;
                --danger: #ff6369;
                --hover: #1a1a1a;
                --sidebar-bg: #000000;
                --sidebar-text: #a1a1a1;
                --sidebar-label: #8a8a8a;
                --sidebar-text-active: #ededed;
                --sidebar-active-bg: #1f1f1f;
                --sidebar-hover-bg: #161616;
                --sidebar-border: #262626;
                --sidebar-logo-filter: none;
            }
            * { box-sizing: border-box; }
            html.nav-open { overflow: hidden; }
            body {
                margin: 0;
                font-family: 'Geist', -apple-system, 'Segoe UI', 'Helvetica Neue', Arial, sans-serif;
                -webkit-font-smoothing: antialiased;
                background: var(--bg);
                color: var(--text);
                display: flex;
                min-height: 100vh;
            }
            .sr-only { position: absolute; width: 1px; height: 1px; margin: -1px; padding: 0; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; border: 0; }

            /* ---------- Sidebar ---------- */
            .sidebar {
                width: var(--sidebar-width);
                flex-shrink: 0;
                background: var(--sidebar-bg);
                border-right: 1px solid var(--sidebar-border);
                position: fixed;
                top: 0; bottom: 0; left: 0;
                display: flex;
                flex-direction: column;
                padding-bottom: env(safe-area-inset-bottom, 0px);
                padding-left: env(safe-area-inset-left, 0px);
                transition: transform 0.2s ease, width 0.2s ease, visibility 0s;
                z-index: 40;
            }
            .sidebar-top {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 0.5rem;
                height: calc(var(--bar-height) + var(--inset-top));
                padding: var(--inset-top) 0.75rem 0 1rem;
                border-bottom: 1px solid var(--sidebar-border);
                flex: none;
            }
            .sidebar a.sidebar-brand { display: flex; align-items: center; flex: 1; min-width: 0; height: auto; overflow: hidden; padding: 0; margin: 0; }
            .sidebar a.sidebar-brand::before { display: none; }
            .sidebar a.sidebar-brand:hover { background: none; }
            .sidebar-brand img { display: block; width: 104px; max-width: 100%; max-height: 40px; object-fit: contain; object-position: left center; filter: var(--sidebar-logo-filter, none); }
            .collapse-btn {
                background: transparent;
                border: 1px solid var(--sidebar-border);
                width: 28px; height: 28px;
                border-radius: 6px;
                color: var(--sidebar-text);
                display: flex; align-items: center; justify-content: center;
                cursor: pointer;
                flex-shrink: 0;
                transition: background 0.15s ease, color 0.15s ease;
            }
            .collapse-btn:hover { background: var(--sidebar-hover-bg); color: var(--sidebar-text-active); }
            .collapse-btn svg { transition: transform 0.2s ease; }

            .sidebar nav { flex: 1; overflow-y: auto; overflow-x: hidden; padding: 0.5rem 0.5rem 0.25rem; }
            .nav-group + .nav-group { margin-top: 0.35rem; }
            .sidebar-section-label {
                font-size: 0.72rem;
                letter-spacing: 0.02em;
                color: var(--sidebar-label);
                padding: 0.65rem 0.75rem 0.3rem;
                font-weight: 600;
            }
            .sidebar a {
                position: relative;
                display: flex;
                align-items: center;
                gap: 0.75rem;
                height: 36px;
                padding: 0 0.75rem;
                margin-bottom: 2px;
                border-radius: 6px;
                color: var(--sidebar-text);
                text-decoration: none;
                font-size: 0.875rem;
                font-weight: 500;
                white-space: nowrap;
                transition: background 0.15s ease, color 0.15s ease;
            }
            .sidebar a svg { flex-shrink: 0; width: 18px; height: 18px; }
            .sidebar a:hover { background: var(--sidebar-hover-bg); color: var(--sidebar-text-active); }
            .sidebar a.active { background: var(--sidebar-active-bg); color: var(--sidebar-text-active); }
            .sidebar a.active::before { content: ""; position: absolute; left: -0.5rem; top: 8px; bottom: 8px; width: 2px; border-radius: 0 2px 2px 0; background: var(--sidebar-text-active); }
            .sidebar a:focus-visible, .collapse-btn:focus-visible { outline: 2px solid var(--accent); outline-offset: -2px; }
            .sidebar-admin { border-top: 1px solid var(--sidebar-border); padding: 0.35rem 0.5rem 0.75rem; }

            /* Count bubble on a sidebar link (Tasks) */
            .nav-badge { display: grid; place-items: center; min-width: 20px; height: 20px; margin-left: auto; padding: 0 6px; border-radius: 999px; background: #e41e3f; color: #fff; font-size: .68rem; font-weight: 800; line-height: 1; }
            .nav-badge[hidden] { display: none; }
            html.sb-collapsed .nav-badge { position: absolute; top: 3px; right: 6px; min-width: 16px; height: 16px; padding: 0 4px; font-size: .6rem; }

            /* Expandable menus (Form Generator): parent link + chevron, children slide open */
            .nav-tree-row { position: relative; display: flex; align-items: center; }
            .nav-tree-row > a { flex: 1; min-width: 0; padding-right: 2.25rem; }
            .sidebar a.active-parent { color: var(--sidebar-text-active); }
            .nav-tree-toggle {
                position: absolute; right: 4px; top: 4px;
                display: grid; place-items: center; width: 28px; height: 28px; padding: 0;
                border: 0; border-radius: 6px; background: transparent; color: var(--sidebar-text); cursor: pointer;
            }
            .nav-tree-toggle:hover { background: var(--sidebar-active-bg); color: var(--sidebar-text-active); }
            .nav-tree-toggle:focus-visible { outline: 2px solid var(--accent); outline-offset: -2px; }
            .nav-tree-toggle svg { width: 14px; height: 14px; transition: transform .2s ease; }
            .nav-tree.open .nav-tree-toggle svg { transform: rotate(90deg); }
            .nav-tree-children { display: grid; grid-template-rows: 0fr; transition: grid-template-rows .22s ease; }
            .nav-tree.open .nav-tree-children { grid-template-rows: 1fr; }
            .nav-tree-inner { overflow: hidden; min-height: 0; margin-left: 1.3rem; padding-left: .6rem; border-left: 1px solid var(--sidebar-border); }
            .nav-tree:not(.open) .nav-tree-inner { visibility: hidden; }
            .nav-tree-section { padding: .45rem .5rem .2rem; color: var(--sidebar-label); font-size: .68rem; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; }
            .sidebar a.nav-child { height: 30px; padding: 0 .6rem; font-size: .82rem; }
            .sidebar a.nav-child.active::before { left: calc(-.6rem - 1px); top: 6px; bottom: 6px; }
            html.sb-collapsed .nav-tree-toggle, html.sb-collapsed .nav-tree-children { display: none; }
            html.sb-collapsed .nav-tree-row > a { padding-right: 0; }

            /* Breadcrumb trail in the top bar */
            .breadcrumbs { min-width: 0; overflow: hidden; }
            .breadcrumbs ol { display: flex; align-items: center; min-width: 0; margin: 0; padding: 0; list-style: none; font-size: .88rem; white-space: nowrap; }
            .breadcrumbs li { display: flex; align-items: center; min-width: 0; }
            .breadcrumbs li + li::before { content: "/"; margin: 0 .5rem; color: var(--border); font-weight: 400; }
            html[data-theme="dark"] .breadcrumbs li + li::before { color: var(--text-muted); opacity: .5; }
            .breadcrumbs a { color: var(--text-muted); text-decoration: none; transition: color .15s ease; }
            .breadcrumbs a:hover { color: var(--text); }
            .breadcrumbs a:focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; border-radius: 3px; }
            .breadcrumbs [aria-current="page"] { overflow: hidden; text-overflow: ellipsis; color: var(--text); font-weight: 600; }
            @media (max-width: 720px) {
                /* Phones: only the page itself and its parent */
                .breadcrumbs li:not(:nth-last-child(-n+2)) { display: none; }
                .breadcrumbs li:nth-last-child(2)::before { display: none; }
            }

            /* ---------- Main area ---------- */
            .main { margin-left: var(--sidebar-width); flex: 1; min-width: 0; display: flex; flex-direction: column; transition: margin-left 0.2s ease; }
            .has-messages-page { height: 100vh; overflow: hidden; }
            .has-messages-page .main { height: 100vh; min-height: 0; overflow: hidden; }
            .has-messages-page .topbar { flex: 0 0 auto; }
            .has-messages-page .content { display: flex; min-height: 0; overflow: hidden; }

            /* ---------- Topbar ---------- */
            .topbar {
                position: sticky;
                top: 0;
                z-index: 20;
                height: calc(var(--bar-height) + var(--inset-top));
                padding: var(--inset-top) 1.5rem 0;
                background: var(--surface);
                border-bottom: 1px solid var(--border);
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 1rem;
            }
            .topbar-left { display: flex; align-items: center; gap: 0.6rem; min-width: 0; }
            .topbar-title { font-weight: 600; font-size: 0.95rem; letter-spacing: -0.01em; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
            .menu-toggle {
                display: none;
                align-items: center; justify-content: center;
                width: 40px; height: 40px;
                background: none;
                border: 1px solid transparent;
                border-radius: 8px;
                cursor: pointer;
                color: var(--text);
            }
            .menu-toggle:hover { background: var(--hover); border-color: var(--border); }
            .menu-toggle:focus-visible, .user-chip:focus-visible, .user-dropdown a:focus-visible, .user-dropdown button:focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; }

            /* Avatar: photo, or initials when there is no photo or it fails to load */
            .avatar { flex: none; width: 32px; height: 32px; border-radius: 50%; object-fit: cover; background: var(--border); }
            .avatar-initials { display: inline-grid; place-items: center; border: 1px solid var(--border); background: var(--hover); color: var(--text); font-size: 0.72rem; font-weight: 700; letter-spacing: 0.02em; }

            /* ---------- User menu ---------- */
            .user-menu { position: relative; flex: none; }
            .user-chip {
                display: flex; align-items: center; gap: 0.65rem;
                background: none; border: 1px solid transparent; cursor: pointer;
                padding: 0.25rem 0.6rem 0.25rem 0.25rem;
                border-radius: 8px;
                color: inherit;
                font: inherit;
                transition: background 0.15s ease, border-color 0.15s ease;
            }
            .user-chip:hover, .user-chip[aria-expanded="true"] { background: var(--hover); border-color: var(--border); }
            .user-chip-text { display: grid; max-width: 180px; text-align: left; }
            .user-chip-name { font-size: 0.82rem; font-weight: 600; line-height: 1.2; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
            .user-chip-role { font-size: 0.72rem; color: var(--text-muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

            .user-dropdown {
                position: absolute;
                top: calc(100% + 8px);
                right: 0;
                width: 248px;
                background: var(--surface);
                border: 1px solid var(--border);
                border-radius: 10px;
                box-shadow: 0 8px 30px rgba(0,0,0,0.18);
                padding: 0.3rem;
                display: none;
                z-index: 50;
            }
            .user-dropdown.open { display: block; }
            .user-dropdown-head { display: grid; gap: 0.1rem; padding: 0.55rem 0.6rem 0.6rem; }
            .user-dropdown-name { font-size: 0.85rem; font-weight: 600; overflow-wrap: anywhere; }
            .user-dropdown-sub { color: var(--text-muted); font-size: 0.75rem; overflow-wrap: anywhere; }
            .user-dropdown a, .user-dropdown button {
                display: flex; align-items: center; gap: 0.65rem;
                width: 100%; text-align: left;
                background: none; border: none; cursor: pointer;
                padding: 0.5rem 0.6rem;
                min-height: 36px;
                border-radius: 6px;
                font-size: 0.85rem;
                color: var(--text);
                text-decoration: none;
                font-family: inherit;
            }
            .user-dropdown a:hover, .user-dropdown button:hover { background: var(--hover); }
            /* Name / email / role block is a link to the profile page */
            .user-dropdown a.user-dropdown-head { display: grid; align-items: start; gap: 0.1rem; padding: 0.55rem 0.6rem 0.6rem; min-height: 0; }
            .user-dropdown svg { width: 16px; height: 16px; flex-shrink: 0; color: var(--text-muted); }
            .user-dropdown .divider { height: 1px; background: var(--border); margin: 0.3rem 0; }
            .theme-switch-row { display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; padding: 0.55rem 0.6rem; color: var(--text); font-size: 0.85rem; font-weight: 500; }
            .theme-switch { position: relative; display: inline-flex; width: 42px; height: 24px; flex: none; }
            .theme-switch input { position: absolute; width: 1px; height: 1px; opacity: 0; }
            .theme-switch-track { position: absolute; inset: 0; border: 1px solid var(--border); border-radius: 999px; background: var(--border); cursor: pointer; transition: background .15s, border-color .15s; }
            .theme-switch-track::before { position: absolute; top: 2px; left: 2px; width: 18px; height: 18px; border-radius: 50%; background: var(--surface); content: ""; transition: transform .15s; }
            .theme-switch input:checked + .theme-switch-track { border-color: var(--accent); background: var(--accent); }
            .theme-switch input:checked + .theme-switch-track::before { transform: translateX(18px); }
            .theme-switch input:focus-visible + .theme-switch-track { outline: 3px solid color-mix(in srgb, var(--accent) 35%, transparent); outline-offset: 2px; }
            .theme-switch input:disabled + .theme-switch-track { opacity: .6; cursor: default; }
            .theme-switch-status { padding: 0 0.6rem 0.45rem; color: var(--danger); font-size: 0.72rem; }
            .theme-switch-status:empty { display: none; }
            .user-dropdown button.logout-item, .user-dropdown button.logout-item svg { color: var(--danger); }

            .content { padding: 1.6rem; padding-left: max(1.6rem, env(safe-area-inset-left, 0px)); padding-right: max(1.6rem, env(safe-area-inset-right, 0px)); flex: 1; }

            /* ---------- Desktop: collapsed sidebar (class lives on <html> so it is applied before first paint) ---------- */
            @media (min-width: 901px) {
                html.sb-collapsed .sidebar { width: var(--sidebar-width-collapsed); }
                html.sb-collapsed .main { margin-left: var(--sidebar-width-collapsed); }
                html.sb-collapsed .sidebar-brand { display: none; }
                html.sb-collapsed .sidebar-top { justify-content: center; padding-left: 0; padding-right: 0; }
                html.sb-collapsed .collapse-btn svg { transform: rotate(180deg); }
                html.sb-collapsed .sidebar-section-label { display: none; }
                html.sb-collapsed .nav-group + .nav-group { margin-top: 0.35rem; padding-top: 0.35rem; border-top: 1px solid var(--sidebar-border); }
                html.sb-collapsed .sidebar a { justify-content: center; padding: 0; }
                html.sb-collapsed .sidebar a.active::before { left: -0.5rem; }
                /* Hidden visually, still announced by screen readers */
                html.sb-collapsed .sidebar a .label { position: absolute; width: 1px; height: 1px; margin: -1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; }
            }

            /* ---------- Chat widget: floating button + window (chats, to-do, tasks) ---------- */
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
                width: min(420px, calc(100vw - 32px));
                height: min(620px, calc(100dvh - 112px));
                min-height: min(380px, calc(100dvh - 112px));
                flex-direction: column;
                overflow: hidden;
                border: 1px solid var(--border);
                border-radius: 18px;
                background: var(--surface);
                box-shadow: 0 24px 70px -18px rgba(15, 23, 42, 0.45), 0 2px 8px rgba(15, 23, 42, 0.08);
                transform-origin: bottom right;
                animation: chat-window-in .26s cubic-bezier(.22, .9, .32, 1);
            }
            @keyframes chat-window-in { from { opacity: 0; transform: translateY(12px) scale(.96); } }
            .chat-window[hidden] { display: none; }
            .chat-window-header {
                display: flex;
                flex: none;
                align-items: center;
                gap: 0.25rem;
                min-height: 56px;
                padding: 0.5rem 0.6rem 0.5rem 1rem;
                border-bottom: 1px solid var(--border);
                color: var(--text);
            }
            .chat-window-title { display: grid; flex: 1; min-width: 0; line-height: 1.2; }
            .chat-window-title strong { font-size: 1rem; font-weight: 750; letter-spacing: -0.01em; }
            .chat-window-title span { color: var(--text-muted); font-size: 0.74rem; }
            .chat-window-btn {
                display: grid;
                width: 34px;
                height: 34px;
                place-items: center;
                padding: 0;
                border: 0;
                border-radius: 50%;
                background: transparent;
                color: var(--text-muted);
                cursor: pointer;
            }
            .chat-window-btn svg { width: 18px; height: 18px; }
            .chat-window-btn:hover { background: var(--hover); color: var(--text); }
            .chat-window-btn:focus-visible { outline: 2px solid var(--accent); outline-offset: 1px; }
            .chat-window iframe { width: 100%; height: 0; min-height: 0; flex: 1; border: 0; background: var(--bg); }
            .chat-head-launcher {
                position: relative;
                display: grid;
                width: 58px;
                height: 58px;
                place-items: center;
                padding: 0;
                border: 0;
                border-radius: 50%;
                background: linear-gradient(145deg, #3291ff 0%, #0060d1 100%);
                box-shadow: 0 10px 24px -8px rgba(0, 96, 209, 0.65), inset 0 1px 0 rgba(255, 255, 255, 0.25);
                color: #fff;
                cursor: pointer;
                transition: transform 0.2s cubic-bezier(.22, .9, .32, 1), box-shadow 0.2s ease;
            }
            .chat-head-launcher:hover { transform: translateY(-2px) scale(1.04); box-shadow: 0 16px 32px -10px rgba(0, 96, 209, 0.7), inset 0 1px 0 rgba(255, 255, 255, 0.25); }
            .chat-head-launcher:active { transform: scale(.96); }
            .chat-head-launcher:focus-visible { outline: 3px solid color-mix(in srgb, var(--accent) 45%, transparent); outline-offset: 3px; }
            .chat-head-launcher svg { position: absolute; width: 28px; height: 28px; transition: opacity .2s ease, transform .25s cubic-bezier(.22, .9, .32, 1); }
            .chat-head-launcher .chat-icon-close { opacity: 0; transform: rotate(-90deg) scale(.6); }
            .chat-head-launcher[aria-expanded="true"] .chat-icon-open { opacity: 0; transform: rotate(90deg) scale(.6); }
            .chat-head-launcher[aria-expanded="true"] .chat-icon-close { opacity: 1; transform: none; }
            /* A soft ring pulses while there is something unread */
            .chat-head-launcher:has(.chat-unread-badge:not([hidden]))::before {
                content: "";
                position: absolute;
                inset: 0;
                border-radius: 50%;
                box-shadow: 0 0 0 0 rgba(50, 145, 255, .55);
                animation: chat-ring 2.2s ease-out infinite;
            }
            @keyframes chat-ring { to { box-shadow: 0 0 0 16px rgba(50, 145, 255, 0); } }
            .chat-unread-badge {
                position: absolute;
                top: -4px;
                right: -4px;
                display: grid;
                min-width: 22px;
                height: 22px;
                place-items: center;
                padding: 0 5px;
                border: 2px solid var(--bg);
                border-radius: 999px;
                background: #e41e3f;
                color: #fff;
                font-size: 0.68rem;
                font-weight: 800;
                line-height: 1;
            }
            .chat-unread-badge[hidden] { display: none; }
            @media (prefers-reduced-motion: reduce) {
                .chat-window, .chat-head-launcher::before { animation: none !important; }
            }
            .has-smart-helper .chat-widget { bottom: 94px; }
            .has-smart-helper .chat-window { height: min(560px, calc(100dvh - 190px)); }

            .overlay {
                display: none;
                position: fixed; inset: 0;
                background: rgba(0,0,0,0.4);
                z-index: 30;
            }

            /* ---------- Tablet / mobile: drawer ---------- */
            @media (max-width: 900px) {
                .sidebar { transform: translateX(-100%); visibility: hidden; transition: transform 0.2s ease, visibility 0s 0.2s; width: min(var(--sidebar-width), 86vw); box-shadow: none; }
                .sidebar.open { transform: translateX(0); visibility: visible; transition: transform 0.2s ease, visibility 0s; box-shadow: 0 0 40px rgba(0,0,0,0.3); }
                .main { margin-left: 0; }
                .menu-toggle { display: inline-flex; }
                .overlay.open { display: block; }
                .collapse-btn { display: none; }
                .sidebar a { height: 44px; }
                .sidebar a.active::before { top: 10px; bottom: 10px; }
                .topbar { padding-left: max(1rem, env(safe-area-inset-left, 0px)); padding-right: max(1rem, env(safe-area-inset-right, 0px)); }
                .content { padding: 1.2rem; padding-left: max(1.2rem, env(safe-area-inset-left, 0px)); padding-right: max(1.2rem, env(safe-area-inset-right, 0px)); }
                .user-dropdown a, .user-dropdown button { min-height: 44px; }
                .user-dropdown a.user-dropdown-head { min-height: 0; }
            }
            @media (max-width: 640px) {
                .user-chip-text { display: none; }
                .user-chip { padding: 0.25rem; }
                .user-dropdown { width: min(248px, calc(100vw - 2rem)); }
                .content { padding: 1rem; padding-left: max(1rem, env(safe-area-inset-left, 0px)); padding-right: max(1rem, env(safe-area-inset-right, 0px)); }
            }
            @media (max-width: 600px) {
                .chat-widget { right: 12px; bottom: 16px; }
                .has-smart-helper .chat-widget { bottom: 84px; }
                .chat-window { width: calc(100vw - 24px); height: min(560px, calc(100dvh - 112px)); }
                .has-smart-helper .chat-window { height: min(560px, calc(100dvh - 190px)); }
                .chat-head-launcher { width: 56px; height: 56px; }
            }
            @media (prefers-reduced-motion: reduce) {
                .sidebar, .main, .collapse-btn svg, .theme-switch-track, .theme-switch-track::before, .chat-head-launcher { transition: none !important; }
            }
        </style>
        @stack('styles')
    </head>
    <body class="{{ request()->routeIs('inquire-modify') ? 'has-smart-helper' : '' }}{{ request()->routeIs('messages.*') ? ' has-messages-page' : '' }}">
        <x-flash-toast :message="session('status')" />
        <div class="overlay" id="sidebarOverlay"></div>

        <aside class="sidebar" id="sidebar" aria-label="Main navigation">
            <div class="sidebar-top">
                <a class="sidebar-brand" href="{{ route('home') }}" aria-label="LSPDMS home">
                    <img src="{{ asset('images/LSP_logo_white.png') }}" alt="LSPDMS — LSP's Data Management System">
                </a>
                <button class="collapse-btn" id="collapseToggle" type="button" aria-label="Collapse sidebar" aria-controls="sidebar" aria-expanded="true">
                    <svg width="14" height="14" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 4l-6 6 6 6"/></svg>
                </button>
            </div>

            <nav>
                @foreach ($navGroups as $group)
                    @php $visibleItems = array_values(array_filter($group['items'], fn ($item) => $can($item[4]))); @endphp
                    @if (count($visibleItems))
                        <div class="nav-group" data-nav-group>
                            <div class="sidebar-section-label">{{ $group['label'] }}</div>
                            @foreach ($visibleItems as [$routeName, $label, $icon, $pattern, $feature])
                                @php $isActive = request()->routeIs($pattern); @endphp
                                <a @if ($feature) data-feature="{{ $feature }}" @endif
                                   href="{{ route($routeName) }}"
                                   class="{{ $isActive ? 'active' : '' }}"
                                   title="{{ $label }}"
                                   @if ($isActive) aria-current="page" @endif>
                                    <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $icons[$icon] !!}</svg>
                                    <span class="label">{{ $label }}</span>
                                    @if ($routeName === 'tasks')
                                        <span class="nav-badge" id="tasksNavBadge" aria-label="New tasks" hidden></span>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    @endif
                    @if ($group['label'] === 'Records' && $can('form_generator_allow'))
                        {{-- Form Generator: two expandable menus; the parent link opens that section of the hub. --}}
                        <div class="nav-group" data-nav-group data-feature="form_generator_allow">
                            <div class="sidebar-section-label">Form Generator</div>
                            @foreach ($formMenus as $menu)
                                @php $open = $formMenuOpen($menu); @endphp
                                <div class="nav-tree {{ $open ? 'open' : '' }}" data-nav-tree="{{ $menu['key'] }}">
                                    <div class="nav-tree-row">
                                        <a href="{{ $menu['url'] }}" class="{{ $open ? 'active-parent' : '' }}" title="{{ $menu['label'] }}">
                                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $icons[$menu['icon']] !!}</svg>
                                            <span class="label">{{ $menu['label'] }}</span>
                                        </a>
                                        <button type="button" class="nav-tree-toggle" aria-expanded="{{ $open ? 'true' : 'false' }}" aria-controls="nav-tree-{{ $menu['key'] }}" aria-label="Show {{ $menu['label'] }} pages">
                                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $icons['chevron'] !!}</svg>
                                        </button>
                                    </div>
                                    <div class="nav-tree-children" id="nav-tree-{{ $menu['key'] }}">
                                        <div class="nav-tree-inner">
                                            @foreach ($menu['sections'] as $section)
                                                @if ($section['label'])
                                                    <div class="nav-tree-section">{{ $section['label'] }}</div>
                                                @endif
                                                @foreach ($section['items'] as $item)
                                                    <a href="{{ $item[1] }}" class="nav-child {{ $item[2] ? 'active' : '' }}" title="{{ $item[0] }}"
                                                       @if (isset($item[3])) data-delib-mode="{{ $item[3] }}" @endif
                                                       @if ($item[2]) aria-current="page" @endif>
                                                        <span class="label">{{ $item[0] }}</span>
                                                    </a>
                                                @endforeach
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                @endforeach
            </nav>

            @if ($isHeadSecretary)
                <div class="sidebar-admin" data-feature="head_secretary">
                    <div class="sidebar-section-label">Administration</div>
                    <a href="{{ route('superadmin-dashboard') }}" title="Superadmin Dashboard" class="{{ request()->routeIs('superadmin-dashboard') ? 'active' : '' }}" @if (request()->routeIs('superadmin-dashboard')) aria-current="page" @endif>
                        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $icons['shield'] !!}</svg>
                        <span class="label">Superadmin Dashboard</span>
                    </a>
                </div>
            @endif
        </aside>

        <div class="main" id="mainArea">
            <header class="topbar">
                <div class="topbar-left">
                    <button class="menu-toggle" id="menuToggle" type="button" aria-label="Open menu" aria-controls="sidebar" aria-expanded="false">
                        <svg width="22" height="22" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M3 5h14M3 10h14M3 15h14"/></svg>
                    </button>
                    @if (count($breadcrumbs))
                        <nav class="breadcrumbs" aria-label="Breadcrumb">
                            <ol>
                                @foreach ($breadcrumbs as [$crumb, $crumbUrl])
                                    <li @if ($loop->last) data-crumb-current @endif>
                                        @if ($crumbUrl)
                                            <a href="{{ $crumbUrl }}">{{ $crumb }}</a>
                                        @else
                                            <span aria-current="page">{{ $crumb }}</span>
                                        @endif
                                    </li>
                                @endforeach
                            </ol>
                        </nav>
                    @else
                        <span class="topbar-title">@yield('title', 'Dashboard')</span>
                    @endif
                </div>

                <div class="user-menu">
                    <button class="user-chip" id="userChipBtn" type="button" aria-haspopup="true" aria-expanded="false" aria-controls="userDropdown" aria-label="Account menu for {{ $displayName }}">
                        @if ($avatarUrl)
                            <img class="avatar" src="{{ $avatarUrl }}" alt="" width="32" height="32" data-avatar data-initials="{{ $initials }}" decoding="async">
                        @else
                            <span class="avatar avatar-initials" aria-hidden="true">{{ $initials }}</span>
                        @endif
                        <span class="user-chip-text">
                            <span class="user-chip-name">{{ $displayName }}</span>
                            <span class="user-chip-role">{{ $authenticatedUser['tungkulin'] ?? '' }}</span>
                        </span>
                    </button>

                    <div class="user-dropdown" id="userDropdown" role="group" aria-label="Account">
                        <a class="user-dropdown-head" href="{{ route('profile') }}" title="Open profile">
                            <span class="user-dropdown-name">{{ $displayName }}</span>
                            @if (!empty($authenticatedUser['gmail']) && $displayName !== $authenticatedUser['gmail'])
                                <span class="user-dropdown-sub">{{ $authenticatedUser['gmail'] }}</span>
                            @endif
                            @if (!empty($authenticatedUser['tungkulin']))
                                <span class="user-dropdown-sub">{{ $authenticatedUser['tungkulin'] }}</span>
                            @endif
                        </a>
                        <div class="divider"></div>
                        <div class="theme-switch-row">
                            <span id="themeSwitchLabel">Dark mode</span>
                            <label class="theme-switch">
                                <input type="checkbox" id="themeSwitch" role="switch" aria-labelledby="themeSwitchLabel" @checked(($authenticatedUser['dark_mode'] ?? false))>
                                <span class="theme-switch-track"></span>
                            </label>
                        </div>
                        <div class="theme-switch-status" id="themeSwitchStatus" role="status" aria-live="polite"></div>
                        <div class="divider"></div>
                        <form method="POST" action="{{ url('/logout') }}" data-lspdms-logout>
                            @csrf
                            <button type="submit" class="logout-item">
                                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $icons['logout'] !!}</svg>
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

        @if (! request()->routeIs('messages.*') && ! empty($authenticatedUser['id']))
            <section class="chat-widget" id="chatWidget"
                data-unread-url="{{ route('messages.unread-count') }}"
                data-user-id="{{ $authenticatedUser['id'] ?? '' }}"
                data-auth-url="{{ route('broadcasting.auth') }}"
                data-csrf="{{ csrf_token() }}"
                data-reverb-configured="{{ config('broadcasting.default') === 'reverb' && filled(config('broadcasting.connections.reverb.key')) && filled(config('broadcasting.connections.reverb.secret')) && filled(config('broadcasting.connections.reverb.app_id')) ? 'true' : 'false' }}"
                aria-label="Direct messages">
                <div class="chat-window" id="chatWindow" role="dialog" aria-label="Messages and to-do" hidden>
                    <header class="chat-window-header">
                        <div class="chat-window-title">
                            <strong>Messages</strong>
                            <span>Chats and your to-do list</span>
                        </div>
                        <a class="chat-window-btn" href="{{ route('messages.index') }}" title="Open in full page" aria-label="Open messages in a full page">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 4h6v6"/><path d="M20 4l-8 8"/><path d="M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/></svg>
                        </a>
                        <button class="chat-window-btn" id="chatMinimize" type="button" aria-label="Minimize chat" title="Minimize">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 12h12"/></svg>
                        </button>
                    </header>
                    <iframe title="Messages and to-do" data-src="{{ route('messages.index', ['widget' => 1]) }}" loading="lazy"></iframe>
                </div>
                <button class="chat-head-launcher" id="chatLauncher" type="button" aria-label="Open messages" aria-controls="chatWindow" aria-expanded="false">
                    <svg class="chat-icon-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M20 11.5a7.5 7.5 0 0 1-7.5 7.5H5l1.8-3.2A7.5 7.5 0 1 1 20 11.5Z"/>
                        <path d="M8.5 11.5h7M8.5 14.5h4"/>
                    </svg>
                    <svg class="chat-icon-close" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M6 9l6 6 6-6"/>
                    </svg>
                    <span class="chat-unread-badge" id="chatUnreadBadge" aria-live="polite" aria-atomic="true" hidden></span>
                </button>
            </section>
        @endif

        <script>
            const root = document.documentElement;
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

            /* ---------- Chat panel (unchanged) ---------- */
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

            /* ---------- Theme switch (unchanged behaviour) ---------- */
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
                themeSwitchStatus.textContent = '';
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
                    themeSwitchStatus.textContent = '';
                } catch (error) {
                    document.documentElement.dataset.theme = previousTheme;
                    if (chatDocument?.documentElement) chatDocument.documentElement.dataset.theme = previousTheme;
                    themeSwitch.checked = previousTheme === 'dark';
                    themeSwitchStatus.textContent = error.name === 'AbortError' ? '' : error.message;
                } finally {
                    themeSwitch.disabled = false;
                }
            });

            /* ---------- Mobile drawer ---------- */
            const visible = nodes => [...nodes].filter(node => node.offsetParent !== null);
            const drawerOpen = () => sidebar.classList.contains('open');
            function openSidebar() {
                sidebar.classList.add('open');
                overlay.classList.add('open');
                root.classList.add('nav-open');
                menuToggle?.setAttribute('aria-expanded', 'true');
                visible(sidebar.querySelectorAll('a'))[0]?.focus();
            }
            function closeSidebar(restoreFocus = false) {
                const wasOpen = drawerOpen();
                sidebar.classList.remove('open');
                overlay.classList.remove('open');
                root.classList.remove('nav-open');
                menuToggle?.setAttribute('aria-expanded', 'false');
                if (wasOpen && restoreFocus) menuToggle?.focus();
            }
            menuToggle?.addEventListener('click', () => (drawerOpen() ? closeSidebar(true) : openSidebar()));
            overlay?.addEventListener('click', () => closeSidebar(true));
            // Selecting a destination closes the drawer so it can never stay stuck behind the overlay.
            sidebar.addEventListener('click', event => { if (event.target.closest('a')) closeSidebar(); });
            // Restoring the page from the back/forward cache must not show an open drawer.
            window.addEventListener('pageshow', () => { closeSidebar(); setUserMenu(false); });
            window.matchMedia('(min-width: 901px)').addEventListener('change', event => { if (event.matches) closeSidebar(); });

            /* ---------- Desktop collapse (state is applied to <html> before first paint) ---------- */
            const syncCollapseButton = () => {
                const collapsed = root.classList.contains('sb-collapsed');
                collapseToggle?.setAttribute('aria-label', collapsed ? 'Expand sidebar' : 'Collapse sidebar');
                collapseToggle?.setAttribute('aria-expanded', String(!collapsed));
            };
            syncCollapseButton();
            collapseToggle?.addEventListener('click', () => {
                const collapsed = root.classList.toggle('sb-collapsed');
                try { localStorage.setItem('sidebarCollapsed', collapsed ? '1' : '0'); } catch (e) {}
                syncCollapseButton();
            });

            /* ---------- Account menu ---------- */
            const menuItems = () => [...userDropdown.querySelectorAll('a, button:not(:disabled), input:not(:disabled)')];
            function setUserMenu(open, focusFirst = false) {
                if (!userDropdown || !userChipBtn) return;
                userDropdown.classList.toggle('open', open);
                userChipBtn.setAttribute('aria-expanded', String(open));
                if (open && focusFirst) menuItems()[0]?.focus();
            }
            userChipBtn?.addEventListener('click', event => {
                event.stopPropagation();
                setUserMenu(!userDropdown.classList.contains('open'));
            });
            userChipBtn?.addEventListener('keydown', event => {
                if (event.key === 'ArrowDown') { event.preventDefault(); setUserMenu(true, true); }
            });
            userDropdown?.addEventListener('keydown', event => {
                const items = menuItems();
                const index = items.indexOf(document.activeElement);
                if (event.key === 'ArrowDown') { event.preventDefault(); items[(index + 1) % items.length]?.focus(); }
                else if (event.key === 'ArrowUp') { event.preventDefault(); items[(index - 1 + items.length) % items.length]?.focus(); }
                else if (event.key === 'Home') { event.preventDefault(); items[0]?.focus(); }
                else if (event.key === 'End') { event.preventDefault(); items[items.length - 1]?.focus(); }
                else if (event.key === 'Tab' && ((!event.shiftKey && index === items.length - 1) || (event.shiftKey && index === 0))) setUserMenu(false);
            });
            userDropdown?.addEventListener('click', event => { if (event.target.closest('a')) setUserMenu(false); });
            document.addEventListener('click', event => {
                if (!userDropdown.contains(event.target) && !userChipBtn.contains(event.target)) setUserMenu(false);
            });

            /* ---------- Escape closes whatever is open (account menu, drawer, chat) ---------- */
            document.addEventListener('keydown', event => {
                if (event.key !== 'Escape') return;
                if (userDropdown?.classList.contains('open')) { setUserMenu(false); userChipBtn.focus(); }
                else if (drawerOpen()) closeSidebar(true);
                else if (chatWindow && !chatWindow.hidden) setChatOpen(false);
            });
            // Keep Tab inside the drawer while it is open.
            document.addEventListener('keydown', event => {
                if (event.key !== 'Tab' || !drawerOpen()) return;
                const focusable = visible(sidebar.querySelectorAll('a, button'));
                if (!focusable.length) return;
                const first = focusable[0], last = focusable[focusable.length - 1];
                if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
                else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
            });

            /* ---------- Avatar fallback: show initials if the photo is missing or fails to load ---------- */
            document.querySelectorAll('img[data-avatar]').forEach(img => {
                const useInitials = () => {
                    const badge = document.createElement('span');
                    badge.className = 'avatar avatar-initials';
                    badge.textContent = img.dataset.initials || '';
                    badge.setAttribute('aria-hidden', 'true');
                    img.replaceWith(badge);
                };
                if (img.complete && img.naturalWidth === 0) useInitials();
                else img.addEventListener('error', useInitials, { once: true });
            });
        </script>
        <script>
            (() => {
                // Expandable sidebar menus. The chevron opens/closes; the open state is remembered per menu.
                document.querySelectorAll('[data-nav-tree]').forEach(tree => {
                    const toggle = tree.querySelector('.nav-tree-toggle');
                    const key = `navTree:${tree.dataset.navTree}`;
                    try {
                        // A menu holding the current page always starts open; otherwise use the remembered state.
                        if (!tree.classList.contains('open') && localStorage.getItem(key) === '1') tree.classList.add('open');
                    } catch (error) { /* storage blocked */ }
                    toggle.setAttribute('aria-expanded', String(tree.classList.contains('open')));
                    toggle.addEventListener('click', () => {
                        const open = tree.classList.toggle('open');
                        toggle.setAttribute('aria-expanded', String(open));
                        try { localStorage.setItem(key, open ? '1' : '0'); } catch (error) { /* storage blocked */ }
                    });
                });

                // The Deliberation page switches Add / Subtract without reloading; keep the menu and breadcrumb in step.
                window.addEventListener('deliberation-mode', event => {
                    const mode = event.detail?.mode === 'subtract' ? 'subtract' : 'add';
                    document.querySelectorAll('[data-delib-mode]').forEach(link => {
                        const active = link.dataset.delibMode === mode;
                        link.classList.toggle('active', active);
                        if (active) link.setAttribute('aria-current', 'page');
                        else link.removeAttribute('aria-current');
                    });
                    const current = document.querySelector('.breadcrumbs [data-crumb-current] [aria-current]');
                    if (current) current.textContent = mode === 'subtract' ? 'Subtract' : 'Add';
                });
            })();
        </script>
        @if ($canUseTasks)
            <script>
                (() => {
                    // New-task reminder: the sidebar badge counts tasks this person has not opened yet, and a
                    // centered pop-up keeps coming back (every page load, then every 2 minutes) until they open Tasks.
                    const unseenUrl = @json(route('assigned-tasks.unseen'));
                    const tasksUrl = @json(route('tasks'));
                    const onTasksPage = @json(request()->routeIs('tasks'));
                    const badge = document.getElementById('tasksNavBadge');
                    const REMIND_EVERY = 120000;
                    let lastReminder = 0;
                    let checking = false;
                    let checkAgain = false;

                    const showBadge = count => {
                        if (!badge) return;
                        badge.textContent = count > 99 ? '99+' : String(count);
                        badge.hidden = !count;
                        badge.setAttribute('aria-label', count === 1 ? '1 new task' : `${count} new tasks`);
                    };
                    const noticeOpen = () => {
                        const notice = document.getElementById('appNotice');
                        return notice && !notice.hidden;
                    };
                    // Due-soon reminder (critical, red): at most every 30 minutes, shared by all tabs and page loads.
                    const DUE_EVERY = 30 * 60000;
                    const DUE_KEY = 'lspdmsTaskDueReminderAt';
                    const dueReminderAllowed = () => {
                        try { return Date.now() - Number(localStorage.getItem(DUE_KEY) || 0) >= DUE_EVERY; } catch (error) { return Date.now() - lastReminder >= DUE_EVERY; }
                    };
                    const markDueReminded = () => { try { localStorage.setItem(DUE_KEY, String(Date.now())); } catch (error) { /* storage blocked */ } };
                    const dueText = days => days < 0 ? `Overdue by ${-days} day${days === -1 ? '' : 's'}` : days === 0 ? 'Due today' : days === 1 ? 'Due tomorrow' : `Due in ${days} days`;
                    function remindDue(tasks) {
                        markDueReminded();
                        const first = tasks[0].days_left;
                        const title = tasks.length > 1
                            ? `${tasks.length} tasks are due soon!`
                            : first < 0 ? 'A task is overdue!' : first === 0 ? 'A task is due today!' : 'A task is due soon!';
                        window.lspdmsNotify?.({
                            type: 'error',
                            title,
                            message: 'Finish it before the due date, or tell the Head Secretary in the comments.',
                            changes: tasks.slice(0, 5).map(task => ({ label: task.title, to: dueText(task.days_left) })),
                            action: { label: 'Open Tasks Menu', href: tasksUrl },
                            dismissLabel: 'Later',
                        });
                    }

                    // Don't interrupt someone typing; the reminder waits for the next check.
                    const typing = () => {
                        const active = document.activeElement;
                        return Boolean(active && (active.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(active.tagName)));
                    };

                    async function check() {
                        if (checking) { checkAgain = true; return; }
                        if (document.hidden || !navigator.onLine) return;
                        checking = true;
                        try {
                            const response = await fetch(unseenUrl, { headers: { Accept: 'application/json', 'X-LSPDMS-Background': '1' }, cache: 'no-store', credentials: 'same-origin' });
                            if (!response.ok) return;
                            const { count, title, message, due_soon: dueSoon = [] } = await response.json();
                            showBadge(count);
                            // Never on the Tasks page itself (it shows its own Due soon block), never on top of another message.
                            if (onTasksPage || noticeOpen() || typing()) return;
                            if (count && Date.now() - lastReminder >= REMIND_EVERY) {
                                lastReminder = Date.now();
                                window.lspdmsNotify?.({
                                    type: 'info',
                                    title,
                                    message,
                                    action: { label: 'Open Tasks Menu', href: tasksUrl },
                                    dismissLabel: 'Later',
                                    onClose: () => { lastReminder = Date.now(); },
                                });
                                return;
                            }
                            if (dueSoon.length && dueReminderAllowed()) remindDue(dueSoon);
                        } catch (error) {
                            // a reminder is best-effort; the next check tries again
                        } finally {
                            checking = false;
                            // A check asked for while one was running (e.g. right after tasks were seen) runs now.
                            if (checkAgain) { checkAgain = false; check(); }
                        }
                    }

                    check();
                    window.setInterval(check, 30000);
                    document.addEventListener('visibilitychange', () => { if (!document.hidden) check(); });
                    // The Tasks page marks tasks as seen; refresh the badge right away.
                    window.addEventListener('lspdms:tasks-seen', () => { lastReminder = Date.now(); check(); });
                    // Live (Reverb): a task was just posted or shared with you -> remind right away, no 2-minute wait.
                    window.addEventListener('lspdms:task-updated', event => {
                        if (['created', 'done'].includes(event.detail?.kind)) lastReminder = 0;
                        check();
                    });
                })();
            </script>
        @endif
        <script>
            (() => {
                // Logging out in one tab signs out every open tab of this browser at once.
                const rendered = document.querySelector('meta[name="lspdms-user"]')?.content || '0:0';
                const goToLogin = () => (window.top || window).location.assign('/login');
                let authChannel = null;
                try { authChannel = new BroadcastChannel('lspdms-auth'); } catch (error) { authChannel = null; }
                authChannel?.addEventListener('message', event => { if (event.data?.type === 'logout') goToLogin(); });
                window.addEventListener('storage', event => { if (event.key === 'lspdms-logout') goToLogin(); });
                let loggingOut = false;
                document.querySelectorAll('form[data-lspdms-logout]').forEach(form => form.addEventListener('submit', () => {
                    loggingOut = true; // our own logout: never report it as revoked access
                    try { authChannel?.postMessage({ type: 'logout' }); } catch (error) { /* channel closed */ }
                    try { localStorage.setItem('lspdms-logout', String(Date.now())); } catch (error) { /* storage blocked */ }
                }));

                let checking = false;
                const checkSession = async () => {
                    if (checking || loggingOut || !navigator.onLine) return; // never overlap requests
                    checking = true;
                    try {
                        const response = await fetch(@json(route('session.status')), {
                            // A background check: it never counts as activity for idle sign-out.
                            headers: { 'Accept': 'application/json', 'X-LSPDMS-Background': '1' },
                            cache: 'no-store',
                            credentials: 'same-origin',
                        });
                        if (response.redirected) window.location.assign(response.url);
                        else if (!response.ok) {
                            if (loggingOut) return;
                            const result = await response.json().catch(() => ({}));
                            // Idle sign-out: every tab goes to the sign-in page with the inactivity notice.
                            if (result.reason === 'idle') {
                                if (window.lspdmsIdle) window.lspdmsIdle.signedOut();
                                else window.location.assign(@json(route('session.signed-out')));
                                return;
                            }
                            // Only a disabled account is "revoked"; a logout (here or in another tab) just returns to sign-in.
                            const target = {
                                account_disabled: '/login?access=revoked',
                                password_changed: '/login?access=password_changed',
                            }[result.reason] || '/login';
                            window.location.assign(target);
                        }
                        else {
                            const { permissions, user_id: userId, auth_version: authVersion, idle_remaining: idleRemaining } = await response.json();
                            // The browser now belongs to another login (or a fresh one): reload instead of acting on stale data.
                            if (userId !== undefined && `${userId}:${authVersion}` !== rendered) {
                                window.location.reload();
                                return;
                            }
                            if (idleRemaining !== null && idleRemaining !== undefined) {
                                window.dispatchEvent(new CustomEvent('lspdms:idle-remaining', { detail: idleRemaining }));
                            }
                            document.querySelectorAll('[data-feature]').forEach(link => {
                                const allowed = Boolean(permissions[link.dataset.feature]);
                                link.hidden = !allowed;
                                link.style.display = allowed ? '' : 'none';
                            });
                            // Hide a group's heading when none of its links are allowed any more.
                            document.querySelectorAll('[data-nav-group]').forEach(group => {
                                if (group.hasAttribute('data-feature')) return; // whole group follows its own permission
                                const anyAllowed = [...group.querySelectorAll('a')].some(link => !link.closest('[hidden]'));
                                group.hidden = !anyAllowed;
                                group.style.display = anyAllowed ? '' : 'none';
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
                                '/note-checker': 'note_checker_allow',
                                '/superadmin-dashboard': 'head_secretary',
                            }).find(([path]) => window.location.pathname === path || window.location.pathname.startsWith(`${path}/`))?.[1];
                            if (currentFeature && !permissions[currentFeature]) window.location.assign('/home');
                        }
                    } catch (error) {
                        console.error('Session access check failed:', error);
                    } finally {
                        checking = false;
                    }
                };
                window.setInterval(() => {
                    if (!document.hidden) checkSession();
                }, 10000);
                // Re-check right away when the tab becomes visible again instead of waiting for the next tick.
                document.addEventListener('visibilitychange', () => { if (!document.hidden) checkSession(); });
                // A page restored from the back/forward cache must not show a signed-out session.
                window.addEventListener('pageshow', event => { if (event.persisted) checkSession(); });
            })();
        </script>
        @stack('scripts')
        @vite('resources/js/motion.js')
        @unless (request()->routeIs('messages.*'))
            @vite('resources/js/chat-notifications.js')
        @endunless
    </body>
</html>