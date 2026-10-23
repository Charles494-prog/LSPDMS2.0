@extends('layouts.app')

@section('title', 'Superadmin Dashboard')

@section('content')
<style>
    /* =====================================================================
       Superadmin Dashboard — monochrome / Vercel-style theme
       Colors come from your layout's theme variables (--bg, --surface,
       --border, --text, --text-muted) so light/dark still follows the app.
       Color is used ONLY for success / danger states.
       ===================================================================== */
    :root {
        --sa-fb-fg: #0a0a0a; --sa-fb-bg: #fafafa; --sa-fb-surface: #ffffff; --sa-fb-border: #eaeaea; --sa-fb-muted: #666666;
        --sa-fg: var(--text, var(--sa-fb-fg));
        --sa-on-fg: var(--bg, var(--sa-fb-surface));
        --sa-accent: var(--sa-fg);
        --sa-accent-hover: color-mix(in srgb, var(--sa-fg) 80%, var(--sa-on-fg));
        --sa-navy: var(--sa-fg);
        --sa-ink-soft: var(--text-muted, var(--sa-fb-muted));
        --sa-surface: var(--surface, var(--sa-fb-surface));
        --sa-border: var(--border, var(--sa-fb-border));
        --sa-bg: var(--bg, var(--sa-fb-bg));
        --sa-hover: color-mix(in srgb, var(--sa-fg) 5%, transparent);
        --sa-danger: #d92d20;
        --sa-danger-bg: color-mix(in srgb, var(--sa-danger) 10%, var(--sa-surface));
        --sa-success: #12803f;
        --sa-success-bg: color-mix(in srgb, var(--sa-success) 10%, var(--sa-surface));
        --sa-radius: 6px;
        --sa-radius-lg: 0px;
        --sa-mono: ui-monospace, SFMono-Regular, 'Geist Mono', Consolas, monospace;
    }
    html[data-theme="dark"] {
        --sa-fb-fg: #ededed; --sa-fb-bg: #000000; --sa-fb-surface: #0a0a0a; --sa-fb-border: #262626; --sa-fb-muted: #a1a1a1;
        --sa-danger: #ff6369;
        --sa-success: #3ecf8e;
    }
    .sa-page {
        font-family: 'Geist', 'Inter', -apple-system, 'Helvetica Neue', Arial, sans-serif;
        -webkit-font-smoothing: antialiased;
    }

    .sa-header {
        margin-bottom: 1.5rem;
        padding-bottom: 1.25rem;
        border-bottom: 1px solid var(--sa-border);
    }
    .sa-header h1 { font-size: 1.5rem; font-weight: 600; letter-spacing: -0.03em; margin: 0 0 0.3rem; color: var(--sa-navy); }
    .sa-header p { margin: 0; color: var(--sa-ink-soft); font-size: 0.9rem; }

    /* ===== Tabs ===== */
    .sa-tabs {
        display: flex;
        gap: 0.2rem;
        border-bottom: 1px solid var(--sa-border);
        margin-bottom: 1.6rem;
        overflow-x: auto;
    }
    .sa-tab-btn {
        font-family: inherit;
        font-size: 0.875rem;
        font-weight: 500;
        color: var(--sa-ink-soft);
        background: none;
        border: none;
        border-bottom: 2px solid transparent;
        margin-bottom: -1px;
        padding: 0.8rem 0.95rem;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        white-space: nowrap;
        transition: color .12s ease, border-color .12s ease;
    }
    .sa-tab-btn:hover { color: var(--sa-navy); }
    .sa-tab-btn.active { color: var(--sa-navy); border-bottom-color: var(--sa-navy); }
    .sa-tab-panel { display: none; }
    .sa-tab-panel.active { display: block; }

    /* ===== SMTP alert ===== */
    .smtp-setup-alert {
        display:flex; align-items:center; justify-content:space-between; gap:1rem; margin:0 0 1.2rem;
        padding:1rem 1.15rem; border:1px solid var(--sa-border);
        border-left:2px solid var(--sa-navy); border-radius:var(--sa-radius-lg); background:var(--sa-surface); color:var(--sa-navy);
    }
    .smtp-setup-alert[hidden], .smtp-security-alert[hidden] { display:none !important; }
    .smtp-setup-alert-copy { display:flex; align-items:flex-start; gap:.8rem; min-width:0; }
    .smtp-setup-alert-icon { display:grid; width:1.6rem; height:1.6rem; flex:0 0 1.6rem; place-items:center; border-radius:50%; border:1px solid var(--sa-border); color:var(--sa-navy); font-size:.8rem; font-weight:700; }
    .smtp-setup-alert strong { display:block; margin-bottom:.15rem; font-size:.9rem; font-weight:600; }
    .smtp-setup-alert p { margin:0; color:var(--sa-ink-soft); font-size:.82rem; line-height:1.45; }
    .smtp-setup-alert .sa-btn { flex:none; }
    .smtp-security-alert { margin:0 0 1rem; padding:.8rem .95rem; border:1px solid var(--sa-border); border-left:2px solid var(--sa-navy); border-radius:var(--sa-radius-lg); background:var(--sa-bg); color:var(--sa-navy); font-size:.82rem; line-height:1.5; }
    .smtp-security-alert button { margin-left:.25rem; padding:0; border:0; background:none; color:var(--sa-navy); font:inherit; font-weight:600; text-decoration:underline; text-underline-offset:3px; cursor:pointer; }
    @media(max-width:760px) {
        .smtp-setup-alert { align-items:flex-start; flex-direction:column; }
    }

    /* ===== SMTP form fields (used inside the popup) ===== */
    .smtp-field { display:grid; gap:.4rem; margin-bottom:1rem; }
    .smtp-field label { color:var(--sa-navy); font-size:.8rem; font-weight:500; }
    .smtp-field input { width:100%; box-sizing:border-box; min-height:40px; padding:.55rem .75rem; border:1px solid var(--sa-border); border-radius:var(--sa-radius); background:var(--sa-surface); color:var(--sa-navy); font:inherit; font-size:.87rem; }
    .smtp-field input:focus-visible { outline:none; border-color:var(--sa-navy); box-shadow:0 0 0 1px var(--sa-navy); }
    .smtp-field-hint { color:var(--sa-ink-soft); font-size:.74rem; line-height:1.45; }
    .smtp-code-input { width: 11ch; padding: .55rem .75rem; border: 1px solid var(--sa-border); border-radius: var(--sa-radius); background: var(--sa-bg); color: var(--sa-navy); font-family: var(--sa-mono); font-size: 1.2rem; font-weight: 600; letter-spacing: .3em; text-align: center; }
    .smtp-code-input:focus { outline: none; border-color: var(--sa-navy); box-shadow: 0 0 0 1px var(--sa-navy); }

    /* ===== BNH reset ===== */
    .bnh-reset-grid { grid-template-columns:1fr; }
    .bnh-reset-field { width:100%; }
    .bnh-reset-state[hidden], .bnh-reset-code-panel[hidden] { display:none !important; }
    .bnh-reset-state { display:flex; width:100%; min-height:300px; box-sizing:border-box; flex-direction:column; align-items:center; justify-content:center; gap:1rem; padding:2rem; border:1px solid var(--sa-border); border-radius:var(--sa-radius-lg); background:var(--sa-surface); color:var(--sa-navy); text-align:center; }
    .bnh-reset-state-copy { width:min(100%,680px); }
    .bnh-reset-state-title { margin:0 0 .4rem; color:var(--sa-navy); font-size:1.05rem; font-weight:600; line-height:1.35; }
    .bnh-reset-state p { margin:0; color:var(--sa-ink-soft); font-size:.9rem; line-height:1.6; }
    .bnh-reset-state-icon { display:grid; width:3rem; height:3rem; flex:0 0 3rem; place-items:center; border-radius:50%; border:1px solid var(--sa-border); background:var(--sa-bg); color:var(--sa-navy); }
    .bnh-reset-state-icon svg { width:1.25rem; height:1.25rem; }
    .bnh-reset-code-panel { display:flex; width:100%; min-height:300px; box-sizing:border-box; flex-direction:column; align-items:center; justify-content:center; padding:clamp(1.25rem,3vw,2rem); border:1px solid var(--sa-border); border-radius:var(--sa-radius-lg); background:var(--sa-bg); text-align:center; }
    .bnh-reset-code-title { width:min(100%,680px); margin:0 auto 1.5rem; color:var(--sa-navy); font-size:1.1rem; font-weight:600; line-height:1.45; }
    .bnh-reset-code-content { display:flex; width:100%; flex-direction:column; gap:1.25rem; align-items:center; }
    .bnh-reset-code-display { width:min(100%,560px); min-width:0; }
    .bnh-reset-code-label { display:block; margin-bottom:.55rem; color:var(--sa-ink-soft); font-size:.78rem; font-weight:500; }
    #bnhResetGeneratedCode { display:block; width:100%; box-sizing:border-box; margin:0; padding:1rem 1.1rem; border:1px solid var(--sa-border); border-radius:var(--sa-radius); background:var(--sa-surface); color:var(--sa-navy); font-family:var(--sa-mono); font-size:clamp(1.25rem,3vw,1.75rem); font-weight:700; letter-spacing:.18em; text-align:center; user-select:none; -webkit-user-select:none; }
    .bnh-reset-countdown { height:3px; margin-top:.85rem; overflow:hidden; background:var(--sa-border); }
    .bnh-reset-countdown > div { width:100%; height:100%; background:var(--sa-navy); transition:width .12s linear; }
    .bnh-reset-code-timer { margin:.45rem 0 0; color:var(--sa-ink-soft); font-size:.8rem; text-align:center; }
    .bnh-reset-confirmation { width:min(100%,420px); }
    .bnh-reset-confirmation label { display:block; margin-bottom:.55rem; color:var(--sa-navy); font-size:.84rem; font-weight:500; text-align:center; }
    .bnh-reset-confirmation input { box-sizing:border-box; width:100%; min-height:44px; padding:.65rem .8rem; border:1px solid var(--sa-border); border-radius:var(--sa-radius); background:var(--sa-surface); color:var(--sa-navy); font:inherit; letter-spacing:.08em; text-align:center; }
    .bnh-reset-confirmation input:focus { outline:none; border-color:var(--sa-navy); box-shadow:0 0 0 1px var(--sa-navy); }
    .bnh-reset-confirmation input:disabled { opacity:.6; }
    @media (max-width:700px) { .bnh-reset-state, .bnh-reset-code-panel { min-height:240px; padding:1.25rem; } }
    #tab-maintenance .sa-card-head { justify-content:center; text-align:center; }
    #tab-maintenance.sa-tab-panel.active { min-height:calc(100vh - 14rem); display:flex; align-items:center; justify-content:center; }
    #tab-maintenance > .sa-card { width:min(100%,760px); margin:0 auto; box-sizing:border-box; }
    #tab-maintenance .bnh-reset-grid { grid-template-columns:minmax(0,1fr); }
    #tab-maintenance .bnh-reset-field { grid-column:1 / -1; }

    /* ===== R4-02 Hakbang ===== */
    .hakbang-status-tabs { display:inline-flex; gap:0; margin:0 0 1rem; border:1px solid var(--sa-border); border-radius:var(--sa-radius); overflow:hidden; }
    .hakbang-status-btn { border:0; border-right:1px solid var(--sa-border); padding:.5rem 1.1rem; color:var(--sa-ink-soft); background:transparent; font:inherit; font-size:.85rem; font-weight:500; cursor:pointer; }
    .hakbang-status-btn:last-child { border-right:0; }
    .hakbang-status-btn:hover { color:var(--sa-navy); background:var(--sa-hover); }
    .hakbang-status-btn.active { color:var(--sa-on-fg); background:var(--sa-accent); }
    .hakbang-add-form { display:grid; grid-template-columns:minmax(110px, .7fr) minmax(220px, 2fr) auto; gap:.7rem; align-items:end; margin-bottom:1.2rem; padding-bottom:1.2rem; border-bottom:1px solid var(--sa-border); }
    .hakbang-field label { display:block; margin-bottom:.35rem; color:var(--sa-ink-soft); font-size:.8rem; font-weight:500; }
    .hakbang-field input { width:100%; box-sizing:border-box; padding:.55rem .7rem; border:1px solid var(--sa-border); border-radius:var(--sa-radius); background:var(--sa-bg); color:var(--sa-navy); font:inherit; }
    .hakbang-row-input { width:100%; min-width:100px; box-sizing:border-box; padding:.45rem .6rem; border:1px solid var(--sa-border); border-radius:var(--sa-radius); background:var(--sa-bg); color:var(--sa-navy); font:inherit; }
    .hakbang-field input:focus, .hakbang-row-input:focus { outline:none; border-color:var(--sa-navy); box-shadow:0 0 0 1px var(--sa-navy); }
    .hakbang-description-input { min-width:240px; }
    .hakbang-actions { display:flex; gap:.4rem; }
    @media (max-width:700px) { .hakbang-add-form { grid-template-columns:1fr; } }

    /* ===== Cards ===== */
    .sa-card {
        background: var(--sa-surface);
        border: 1px solid var(--sa-border);
        border-radius: var(--sa-radius-lg);
        padding: 1.5rem 1.7rem;
        margin-bottom: 1.5rem;
    }
    .sa-card-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 1.3rem;
        padding-bottom: 1.1rem;
        border-bottom: 1px solid var(--sa-border);
        gap: 1rem;
        flex-wrap: wrap;
    }
    .sa-card-head h2 { font-size: 1.05rem; font-weight: 600; letter-spacing: -0.02em; margin: 0; color: var(--sa-navy); }
    .sa-card-head p { margin: 0.25rem 0 0; font-size: 0.85rem; color: var(--sa-ink-soft); }
    .sa-card-head-actions { display: flex; align-items: center; gap: 0.6rem; flex-wrap: wrap; }

    .head-secretary-transfer-controls {
        display: grid;
        grid-template-columns: minmax(260px, 1.3fr) minmax(220px, 1fr) auto;
        align-items: end;
        gap: 0.8rem;
    }
    .head-secretary-transfer-controls .gv-field { min-width: 0; margin: 0; }
    .head-secretary-transfer-controls .gv-field > span { display: block; margin-bottom: 0.4rem; color: var(--sa-navy); font-size: 0.83rem; font-weight: 500; }
    .head-secretary-transfer-controls .sa-btn { min-height: 40px; }
    .head-secretary-transfer-controls .sa-select { box-sizing: border-box; width: 100%; height: 40px; }
    .head-secretary-transfer-controls input[type="password"] {
        box-sizing: border-box; width: 100%; height: 40px; padding: 0.55rem 0.8rem;
        border: 1px solid var(--sa-border); border-radius: var(--sa-radius);
        background: var(--sa-bg); color: var(--sa-navy); font: inherit;
    }
    .head-secretary-transfer-controls input[type="password"]:focus { outline: none; border-color: var(--sa-navy); box-shadow: 0 0 0 1px var(--sa-navy); background: var(--sa-surface); }
    @media (max-width: 760px) {
        .head-secretary-transfer-controls { grid-template-columns: 1fr; }
        .head-secretary-transfer-controls .sa-btn { justify-content: center; }
    }

    .sa-page-actions { display: flex; justify-content: flex-start; margin-bottom: 0.9rem; }
    .sa-audit-filters {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
        gap: 0.8rem;
        align-items: end;
        margin-bottom: 1rem;
        padding: 1rem;
        border: 1px solid var(--sa-border);
        border-radius: var(--sa-radius-lg);
        background: var(--sa-bg);
    }
    .sa-audit-filters label span { display: block; margin-bottom: 0.35rem; color: var(--sa-ink-soft); font-size: 0.8rem; font-weight: 500; }
    .sa-audit-filters input {
        box-sizing: border-box; width: 100%; min-width: 0; padding: 0.5rem 0.65rem;
        border: 1px solid var(--sa-border); border-radius: var(--sa-radius);
        background: var(--sa-surface); color: var(--sa-navy); font: inherit;
    }
    .sa-audit-filters input:focus { outline: none; border-color: var(--sa-navy); box-shadow: 0 0 0 1px var(--sa-navy); }
    .sa-audit-filter-error { grid-column: 1 / -1; margin: 0; color: var(--sa-danger); font-size: 0.85rem; }

    /* ===== Buttons ===== */
    .sa-btn {
        font-family: inherit;
        font-size: 0.85rem;
        font-weight: 500;
        border-radius: var(--sa-radius);
        padding: 0.5rem 1rem;
        cursor: pointer;
        border: 1px solid transparent;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.45rem;
        white-space: nowrap;
        text-decoration: none;
        transition: background .12s ease, border-color .12s ease, color .12s ease;
    }
    .sa-btn:focus-visible { outline: 2px solid var(--sa-navy); outline-offset: 2px; }
    .sa-btn-primary { background: var(--sa-accent); color: var(--sa-on-fg); border-color: var(--sa-accent); }
    .sa-btn-primary:hover { background: var(--sa-accent-hover); border-color: var(--sa-accent-hover); }
    .sa-btn-ghost { background: transparent; color: var(--sa-navy); border-color: var(--sa-border); }
    .sa-btn-ghost:hover { background: var(--sa-hover); border-color: var(--sa-ink-soft); }
    .sa-btn-danger { background: transparent; color: var(--sa-danger); border-color: color-mix(in srgb, var(--sa-danger) 40%, var(--sa-border)); }
    .sa-btn-danger:hover { background: var(--sa-danger-bg); border-color: var(--sa-danger); }
    .sa-btn:disabled { opacity: .45; cursor: not-allowed; }
    .sa-btn-sm { padding: 0.35rem 0.7rem; font-size: 0.78rem; }

    .sa-search {
        width: 100%; max-width: 280px; padding: 0.5rem 0.8rem;
        border-radius: var(--sa-radius); border: 1px solid var(--sa-border);
        font-size: 0.87rem; font-family: inherit; background: var(--sa-bg); color: var(--sa-navy);
    }
    .sa-search:focus { outline: none; border-color: var(--sa-navy); box-shadow: 0 0 0 1px var(--sa-navy); background: var(--sa-surface); }

    /* ===== Tables ===== */
    .sa-table-wrap { overflow-x: auto; }
    table.sa-table { width: 100%; border-collapse: collapse; font-size: 0.88rem; }
    table.sa-table th {
        text-align: left; font-size: 0.78rem; font-weight: 500; color: var(--sa-ink-soft);
        padding: 0.65rem 0.7rem; border-bottom: 1px solid var(--sa-border); white-space: nowrap;
    }
    table.sa-table th.center, table.sa-table td.center { text-align: center; }
    table.sa-table td { padding: 0.75rem 0.7rem; border-bottom: 1px solid var(--sa-border); color: var(--sa-navy); vertical-align: middle; }
    table.sa-table tbody tr:hover td { background: var(--sa-hover); }
    table.sa-table tr:last-child td { border-bottom: none; }
    .sa-user-cell { display: flex; align-items: center; gap: 0.65rem; }
    .sa-user-cell img { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; background: var(--sa-border); flex-shrink: 0; }
    .sa-user-avatar { display: grid; place-items: center; width: 32px; height: 32px; border-radius: 50%; background: var(--sa-accent); color: var(--sa-on-fg); font-size: .85rem; font-weight: 600; flex-shrink: 0; }
    .sa-user-name { font-weight: 500; font-size: 0.88rem; }
    .sa-user-email { font-size: 0.78rem; color: var(--sa-ink-soft); }

    .sa-select {
        font-family: inherit; font-size: 0.85rem; padding: 0.4rem 0.6rem;
        border-radius: var(--sa-radius); border: 1px solid var(--sa-border);
        background: var(--sa-bg); color: var(--sa-navy); cursor: pointer;
    }
    .sa-select:focus { outline: none; border-color: var(--sa-navy); box-shadow: 0 0 0 1px var(--sa-navy); }

    .sa-status-pill {
        display: inline-flex; align-items: center; gap: 0.4rem;
        font-size: 0.76rem; font-weight: 500; padding: 0.22rem 0.6rem;
        border-radius: var(--sa-radius); border: 1px solid var(--sa-border); background: transparent;
    }
    .sa-status-pill.on { color: var(--sa-navy); }
    .sa-status-pill.on svg { color: var(--sa-success); }
    .sa-status-pill.off { color: var(--sa-ink-soft); }
    .sa-status-pill.off svg { color: var(--sa-danger); }
    .sa-status-pill svg { width: 7px; height: 7px; }

    /* Toggle switch */
    .sa-toggle { position: relative; display: inline-block; width: 38px; height: 22px; flex-shrink: 0; }
    .sa-toggle input { opacity: 0; width: 0; height: 0; }
    .sa-toggle-track {
        position: absolute; inset: 0; background: transparent; border: 1px solid var(--sa-border);
        border-radius: 999px; cursor: pointer; transition: background .15s ease, border-color .15s ease;
    }
    .sa-toggle-track::before {
        content: ""; position: absolute; width: 14px; height: 14px; left: 3px; top: 3px;
        background: var(--sa-ink-soft); border-radius: 50%; transition: transform .15s ease, background .15s ease;
    }
    .sa-toggle input:checked + .sa-toggle-track { background: var(--sa-accent); border-color: var(--sa-accent); }
    .sa-toggle input:checked + .sa-toggle-track::before { transform: translateX(16px); background: var(--sa-on-fg); }
    .sa-toggle input:focus-visible + .sa-toggle-track { outline: 2px solid var(--sa-navy); outline-offset: 2px; }

    .sa-checkbox { width: 16px; height: 16px; accent-color: var(--sa-accent); cursor: pointer; }
    .sa-actions-cell { display: flex; gap: 0.5rem; align-items: center; }

    /* ===== Signatories (Purok) ===== */
    .purok-list { display: flex; flex-direction: column; gap: 1rem; }
    .purok-card { border: 1px solid var(--sa-border); border-radius: var(--sa-radius-lg); overflow: hidden; }
    .purok-head {
        display: flex; align-items: center; justify-content: space-between;
        padding: 0.8rem 1.1rem; background: var(--sa-bg); border-bottom: 1px solid var(--sa-border); gap: 0.8rem;
    }
    .purok-head-left { display: flex; align-items: center; gap: 0.7rem; flex: 1; min-width: 0; }
    .purok-name-wrap { flex: 1; min-width: 0; }
    .purok-name-view { display: flex; align-items: center; gap: 0.4rem; }
    .purok-name-display { font-weight: 600; font-size: 0.95rem; color: var(--sa-navy); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .purok-edit-btn {
        background: none; border: 1px solid transparent; color: var(--sa-ink-soft); border-radius: var(--sa-radius);
        width: 28px; height: 28px; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; flex-shrink: 0;
    }
    .purok-edit-btn:hover { background: var(--sa-surface); border-color: var(--sa-border); color: var(--sa-navy); }
    .purok-name-edit { display: none; align-items: center; gap: 0.5rem; flex-wrap: wrap; }
    .purok-name-edit.active { display: flex; }
    .purok-name-input {
        font-family: inherit; font-weight: 600; font-size: 0.95rem; color: var(--sa-navy);
        background: var(--sa-surface); border: 1px solid var(--sa-border); border-radius: var(--sa-radius);
        padding: 0.35rem 0.55rem; width: 200px; max-width: 100%;
    }
    .purok-name-input:focus { outline: none; border-color: var(--sa-navy); }
    .purok-name-input.invalid { border-color: var(--sa-danger); }
    .purok-body { padding: 1.1rem; display: flex; flex-direction: column; gap: 0.9rem; }

    .signatory-row { display: grid; grid-template-columns: 150px 1fr; gap: 0.7rem; align-items: center; }
    @media (max-width: 640px) {
        .signatory-row { grid-template-columns: 1fr; }
        .sakop-range-inputs { grid-template-columns: 1fr; }
    }
    .signatory-role-badge {
        font-size: 0.8rem; font-weight: 500; color: var(--sa-ink-soft);
        background: transparent; padding: 0.4rem 0; text-align: left;
    }
    .signatory-input {
        font-family: inherit; font-size: 0.88rem; padding: 0.5rem 0.75rem;
        border-radius: var(--sa-radius); border: 1px solid var(--sa-border);
        background: var(--sa-bg); color: var(--sa-navy); width: 100%; box-sizing: border-box;
    }
    .signatory-input:focus { outline: none; border-color: var(--sa-navy); box-shadow: 0 0 0 1px var(--sa-navy); background: var(--sa-surface); }

    .sakop-row { display: grid; grid-template-columns: 150px 1fr; gap: 0.7rem; align-items: start; transition: opacity 0.15s ease; }
    @media (max-width: 640px) { .sakop-row { grid-template-columns: 1fr; } }
    .sakop-row.disabled { opacity: 0.5; }
    .sakop-row.disabled .signatory-input { cursor: not-allowed; color: var(--sa-ink-soft); }
    .sakop-row.disabled .sakop-preview { color: var(--sa-ink-soft); }

    .complete-row {
        display: flex; align-items: flex-start; gap: 0.65rem; padding: 0.8rem 0.9rem; margin-top: 0.2rem;
        border: 1px solid var(--sa-border); border-radius: var(--sa-radius); background: var(--sa-bg); cursor: pointer;
    }
    .complete-row input[type="checkbox"] { width: 16px; height: 16px; margin-top: 0.1rem; accent-color: var(--sa-accent); cursor: pointer; flex-shrink: 0; }
    .complete-row-text strong { display: block; font-size: 0.88rem; font-weight: 600; color: var(--sa-navy); }
    .complete-row-text span { font-size: 0.78rem; color: var(--sa-ink-soft); }
    .sakop-range-inputs { display: grid; grid-template-columns: 1fr auto 1fr; gap: 0.6rem; align-items: center; }
    .sakop-range-inputs span { color: var(--sa-ink-soft); font-size: 0.85rem; text-align: center; }
    .sakop-preview {
        margin-top: 0.5rem; font-family: var(--sa-mono); font-size: 0.78rem; font-weight: 500; color: var(--sa-navy);
        background: transparent; border: 1px dashed var(--sa-border); padding: 0.4rem 0.7rem;
        border-radius: var(--sa-radius); display: inline-block;
    }

    .add-purok-btn {
        width: 100%; border: 1px dashed var(--sa-border); background: transparent; color: var(--sa-ink-soft);
        border-radius: var(--sa-radius-lg); padding: 0.9rem; font-family: inherit; font-weight: 500; font-size: 0.88rem;
        cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 0.5rem;
    }
    .add-purok-btn:hover { border-color: var(--sa-navy); color: var(--sa-navy); background: var(--sa-hover); }

    /* ===== Global variables ===== */
    .gv-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.1rem 1.4rem; }
    @media (max-width: 720px) { .gv-grid { grid-template-columns: 1fr; } }
    .gv-field label { display: block; font-size: 0.83rem; font-weight: 500; color: var(--sa-navy); margin-bottom: 0.4rem; }
    .gv-field input[type="text"],
    .gv-field input[type="date"],
    .gv-field select {
        width: 100%; box-sizing: border-box; padding: 0.55rem 0.8rem; border-radius: var(--sa-radius);
        border: 1px solid var(--sa-border); font-size: 0.88rem; font-family: inherit; color: var(--sa-navy); background: var(--sa-bg);
    }
    .gv-field input:focus, .gv-field select:focus { outline: none; border-color: var(--sa-navy); box-shadow: 0 0 0 1px var(--sa-navy); background: var(--sa-surface); }
    .gv-field.disabled label { color: var(--sa-ink-soft); }
    .gv-field.disabled input, .gv-field.disabled select { background: var(--sa-bg); color: var(--sa-ink-soft); cursor: not-allowed; opacity: 0.6; }
    .gv-hint { font-size: 0.76rem; color: var(--sa-ink-soft); margin-top: 0.35rem; }

    .sql-dropzone {
        box-sizing: border-box; min-height: 240px; display: flex; flex-direction: column; align-items: center; justify-content: center;
        gap: .65rem; padding: 2rem; border: 1px dashed var(--sa-border); border-radius: var(--sa-radius-lg);
        background: var(--sa-bg); color: var(--sa-navy); cursor: pointer; text-align: center;
        transition: border-color .15s ease, background-color .15s ease;
    }
    .sql-dropzone:hover, .sql-dropzone.is-dragging { border-color: var(--sa-navy); background: var(--sa-hover); }
    .sql-dropzone:focus-within { outline: 2px solid var(--sa-navy); outline-offset: 2px; }
    .sql-dropzone-icon {
        width: 48px; height: 48px; display: grid; place-items: center; border-radius: var(--sa-radius);
        border: 1px solid var(--sa-border); background: var(--sa-surface); color: var(--sa-navy);
    }
    .sql-dropzone-icon svg { width: 26px; height: 26px; }
    .sql-dropzone-title { font-size: 1rem; font-weight: 600; }
    .sql-dropzone-hint, .sql-file-status { color: var(--sa-ink-soft); font-size: .88rem; }
    .sql-dropzone-hint strong { color: var(--sa-navy); font-family: var(--sa-mono); font-weight: 600; }
    .sql-file-status { min-height: 1.25rem; }
    @media (max-width: 600px) { .sql-dropzone { min-height: 200px; padding: 1.5rem 1rem; } }
    .sql-file-input { position: absolute; width: 1px; height: 1px; padding: 0; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; clip-path: inset(50%); }
    .sql-file-error { margin: .5rem 0 0; color: var(--sa-danger); font-size: .8rem; }

    .gv-subsection { margin-top: 1.4rem; padding-top: 1.3rem; border-top: 1px solid var(--sa-border); }
    .gv-subsection-title { font-size: 0.95rem; font-weight: 600; color: var(--sa-navy); margin: 0 0 0.2rem; }
    .gv-subsection-sub { font-size: 0.83rem; color: var(--sa-ink-soft); margin: 0 0 1rem; }

    .delist-checks { display: flex; flex-direction: column; gap: 0.7rem; }
    .delist-check-row {
        display: flex; align-items: flex-start; gap: 0.65rem; padding: 0.8rem 0.9rem;
        border: 1px solid var(--sa-border); border-radius: var(--sa-radius); background: var(--sa-bg);
    }
    .delist-check-row input[type="checkbox"] { width: 16px; height: 16px; margin-top: 0.1rem; accent-color: var(--sa-accent); cursor: pointer; flex-shrink: 0; }
    .delist-check-text label { font-weight: 600; font-size: 0.88rem; color: var(--sa-navy); display: block; cursor: pointer; }
    .delist-check-text span, .delist-check-text small { font-size: 0.78rem; color: var(--sa-ink-soft); }
    .delist-check-text strong { font-weight: 600; }
    .delist-check-text small { display: block; }

    /* Security settings: one bordered list with divider lines */
    .settings-list { display: flex; flex-direction: column; border: 1px solid var(--sa-border); border-radius: var(--sa-radius-lg); }
    .settings-row {
        display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: 1rem 1.1rem;
        border-bottom: 1px solid var(--sa-border); background: transparent;
    }
    .settings-row:last-child { border-bottom: none; }
    .settings-row-text label { font-weight: 600; font-size: 0.88rem; color: var(--sa-navy); display: block; }
    .settings-row-text span { font-size: 0.78rem; color: var(--sa-ink-soft); }

    .sa-save-bar { display: flex; justify-content: flex-end; gap: 0.7rem; margin-top: 1.3rem; padding-top: 1.1rem; border-top: 1px solid var(--sa-border); }
    #saveSignatoriesBtn:disabled { background: transparent; color: var(--sa-ink-soft); border-color: var(--sa-border); cursor: not-allowed; opacity: .6; }

    .sa-empty { text-align: center; padding: 2rem 1rem; color: var(--sa-ink-soft); font-size: 0.88rem; }
    .sa-note {
        font-size: 0.82rem; color: var(--sa-ink-soft); background: var(--sa-bg);
        border: 1px solid var(--sa-border); border-radius: var(--sa-radius); padding: 0.7rem 0.9rem; margin-bottom: 1.2rem;
    }

    /* ===== Toasts ===== */
    .sa-toast-container {
        position: fixed; top: max(1rem, env(safe-area-inset-top)); right: max(1rem, env(safe-area-inset-right));
        z-index: 100100; display: grid; gap: 0.65rem; width: min(420px, calc(100vw - 2rem)); pointer-events: none;
    }
    .sa-toast {
        display: flex; align-items: flex-start; gap: 0.75rem; padding: 0.85rem 1rem;
        border: 1px solid var(--sa-border); border-left: 2px solid var(--sa-navy); border-radius: var(--sa-radius);
        background: var(--sa-surface); color: var(--sa-navy); box-shadow: 0 8px 24px rgba(0, 0, 0, 0.25);
        pointer-events: auto; animation: sa-toast-in 200ms ease-out both;
    }
    .sa-toast[data-type="success"] { border-left-color: var(--sa-success); }
    .sa-toast[data-type="error"] { border-left-color: var(--sa-danger); }
    .sa-toast-icon {
        display: grid; place-items: center; width: 1.3rem; height: 1.3rem; flex: 0 0 1.3rem; border-radius: 50%;
        border: 1px solid var(--sa-border); background: transparent; color: var(--sa-navy); font-size: .75rem; font-weight: 700;
    }
    .sa-toast[data-type="success"] .sa-toast-icon { color: var(--sa-success); }
    .sa-toast[data-type="error"] .sa-toast-icon { color: var(--sa-danger); }
    .sa-toast-message { flex: 1; font-size: 0.88rem; line-height: 1.45; overflow-wrap: anywhere; }
    .sa-toast-close { border: 0; padding: 0; background: transparent; color: var(--sa-ink-soft); font-size: 1.2rem; line-height: 1; cursor: pointer; }
    .sa-toast-close:hover { color: var(--sa-navy); }
    .sa-toast.sa-toast-leaving { animation: sa-toast-out 160ms ease-in both; }
    @media (max-width: 600px) {
        .sa-toast-container { top: max(.65rem, env(safe-area-inset-top)); right: max(.65rem, env(safe-area-inset-right)); left: max(.65rem, env(safe-area-inset-left)); width: auto; }
        .sa-modal-overlay { align-items: flex-start; padding: max(.75rem, env(safe-area-inset-top)) .65rem; }
        .sa-modal { max-height: calc(100dvh - 1.5rem); padding: 1.1rem; }
        .sa-modal-save-bar { flex-wrap: wrap; }
        .sa-modal-save-bar .sa-btn { flex: 1 1 auto; justify-content: center; }
    }
    @keyframes sa-toast-in { from { opacity: 0; transform: translateY(-6px); } to { opacity: 1; transform: translateY(0); } }
    @keyframes sa-toast-out { to { opacity: 0; transform: translateY(-4px); } }

    /* ===== Modals ===== */
    .sa-modal-overlay {
        position: fixed !important; top: 0 !important; left: 0 !important; right: 0 !important; bottom: 0 !important;
        width: 100% !important; height: 100% !important; margin: 0 !important;
        background: rgba(0, 0, 0, 0.65) !important;
        display: none; align-items: flex-start; justify-content: center; padding: 3.5rem 1rem;
        z-index: 99999 !important; overflow-y: auto; box-sizing: border-box;
    }
    .sa-modal-overlay.active { display: flex !important; }
    .sa-modal {
        background: var(--sa-surface); border: 1px solid var(--sa-border); border-radius: var(--sa-radius-lg);
        width: 100%; max-width: 820px; max-height: calc(100vh - 5rem); overflow-y: auto; box-sizing: border-box;
        padding: 1.5rem 1.7rem; box-shadow: 0 24px 60px rgba(0,0,0,0.5);
        font-family: 'Geist', 'Inter', -apple-system, 'Helvetica Neue', Arial, sans-serif;
    }
    .sa-modal-header {
        display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem;
        margin-bottom: 1.2rem; padding-bottom: 1.1rem; border-bottom: 1px solid var(--sa-border);
    }
    .sa-modal-header h2 { font-size: 1.05rem; font-weight: 600; letter-spacing: -0.02em; margin: 0; color: var(--sa-navy); }
    .sa-modal-header p { margin: 0.25rem 0 0; font-size: 0.85rem; color: var(--sa-ink-soft); }
    .sa-modal-close {
        background: transparent; border: 1px solid var(--sa-border); color: var(--sa-ink-soft); border-radius: var(--sa-radius);
        width: 30px; height: 30px; font-size: 1.1rem; line-height: 1; cursor: pointer; flex-shrink: 0;
        display: flex; align-items: center; justify-content: center;
    }
    .sa-modal-close:hover { background: var(--sa-hover); color: var(--sa-navy); }
    .sa-modal-search-row { margin-bottom: 1rem; }
    .sa-modal-save-bar { display: flex; justify-content: flex-end; gap: 0.7rem; margin-top: 1.2rem; padding-top: 1.1rem; border-top: 1px solid var(--sa-border); }

    .database-import-modal { max-width: 680px; }
    .database-import-warning {
        margin: 0 0 1rem; padding: .85rem 1rem; border: 1px solid var(--sa-danger); border-radius: var(--sa-radius);
        background: var(--sa-danger-bg); color: var(--sa-danger); font-size: .88rem; font-weight: 500;
    }
    .database-import-summary { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin: 1rem 0; }
    .database-import-summary section { min-width: 0; padding: .9rem; border: 1px solid var(--sa-border); border-radius: var(--sa-radius); background: var(--sa-bg); }
    .database-import-summary h3 { margin: 0 0 .5rem; color: var(--sa-navy); font-size: .85rem; font-weight: 600; }
    .database-import-summary ul { max-height: 150px; overflow: auto; margin: 0; padding-left: 1.2rem; color: var(--sa-ink-soft); font-family: var(--sa-mono); font-size: .78rem; }
    .database-import-confirm-fields { display: grid; grid-template-columns: 1fr 1fr; gap: .9rem; margin-top: 1rem; }
    .database-import-confirm-fields label { display: grid; align-content: start; gap: .4rem; color: var(--sa-navy); font-size: .82rem; font-weight: 500; }
    .database-import-confirm-fields input {
        box-sizing: border-box; width: 100%; min-height: 40px; padding: .55rem .75rem; border: 1px solid var(--sa-border);
        border-radius: var(--sa-radius); background: var(--sa-bg); color: var(--sa-navy); font: inherit;
    }
    .database-import-confirm-fields input:focus { outline: none; border-color: var(--sa-navy); box-shadow: 0 0 0 1px var(--sa-navy); background: var(--sa-surface); }
    .database-import-error { margin: .8rem 0 0; color: var(--sa-danger); font-size: .85rem; font-weight: 500; }
    @media (max-width: 620px) { .database-import-summary, .database-import-confirm-fields { grid-template-columns: 1fr; } }

    /* =====================================================================
       Layout shell: grouped sidebar navigation + content column
       ===================================================================== */
    .sa-shell { display: grid; grid-template-columns: 232px minmax(0, 1fr); gap: 2rem; align-items: start; }
    .sa-content { min-width: 0; }
    .sa-tabs {
        flex-direction: column; gap: 2px; margin: 0; overflow: visible;
        position: sticky; top: 1rem; padding: 0 1.25rem 0 0;
        border-bottom: 0; border-right: 1px solid var(--sa-border);
    }
    .sa-nav-group { margin: 1rem 0 .3rem; padding: 0 .65rem; color: var(--sa-ink-soft); font-size: .72rem; font-weight: 500; letter-spacing: .02em; }
    .sa-nav-group:first-child { margin-top: 0; }
    .sa-tab-btn {
        width: 100%; margin: 0; border: 0; border-radius: var(--sa-radius);
        padding: .5rem .65rem; font-size: .85rem; font-weight: 500; text-align: left;
    }
    .sa-tab-btn:hover { background: var(--sa-hover); }
    .sa-tab-btn.active { background: color-mix(in srgb, var(--sa-fg) 9%, transparent); color: var(--sa-navy); border: 0; }
    .sa-tab-btn:focus-visible { outline: 2px solid var(--sa-navy); outline-offset: -2px; }
    .sa-tab-btn svg { flex: none; }
    .sa-panel-head { margin-bottom: 1.25rem; padding-bottom: 1rem; border-bottom: 1px solid var(--sa-border); }
    .sa-panel-head h2 { margin: 0; font-size: 1.2rem; font-weight: 600; letter-spacing: -.02em; color: var(--sa-navy); }
    .sa-panel-head p { margin: .3rem 0 0; font-size: .87rem; color: var(--sa-ink-soft); }
    #tab-security > .sa-card > .sa-card-head,
    #tab-signatories > .sa-card > .sa-card-head,
    #tab-r402-hakbangs > .sa-card > .sa-card-head,
    #tab-maintenance > .sa-card > .sa-card-head { display: none; }
    #tab-maintenance.sa-tab-panel.active { display: block; min-height: 0; }
    #tab-maintenance > .sa-card { width: 100%; margin: 0 0 1.5rem; }
    @media (max-width: 900px) {
        .sa-shell { grid-template-columns: minmax(0, 1fr); gap: 1.25rem; }
        .sa-tabs { position: static; flex-direction: row; overflow-x: auto; padding: 0 0 .5rem; border-right: 0; border-bottom: 1px solid var(--sa-border); }
        .sa-nav-group { display: none; }
        .sa-tab-btn { width: auto; flex: none; }
    }

    /* ===== Controls: custom select arrow, loading, inline errors ===== */
    .sa-select, select.signatory-input, .gv-field select {
        appearance: none; -webkit-appearance: none; padding-right: 2rem;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12' fill='none' stroke='%23888' stroke-width='1.6' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M3 4.5l3 3 3-3'/%3E%3C/svg%3E");
        background-repeat: no-repeat; background-position: right .65rem center;
    }
    .sa-btn.is-loading { cursor: progress; }
    .sa-btn.is-loading:disabled { opacity: .8; }
    .sa-btn.is-loading::before {
        content: ""; width: 12px; height: 12px; flex: none; border: 2px solid currentColor;
        border-right-color: transparent; border-radius: 50%; animation: sa-spin .7s linear infinite;
    }
    @keyframes sa-spin { to { transform: rotate(360deg); } }
    @media (prefers-reduced-motion: reduce) {
        .sa-btn.is-loading::before { animation-duration: 2s; }
        .sa-toast, .sa-toast.sa-toast-leaving { animation: none; }
    }
    .sa-inline-error {
        margin: 1rem 0 0; padding: .65rem .85rem; border: 1px solid var(--sa-danger);
        border-radius: var(--sa-radius); background: var(--sa-danger-bg); color: var(--sa-danger); font-size: .85rem;
    }
    .sa-inline-error[hidden] { display: none; }
    .sa-table-meta { display: block; margin: 0 0 .6rem; color: var(--sa-ink-soft); font-size: .78rem; }
    .sa-empty { border: 1px dashed var(--sa-border); margin-top: .75rem; }
    table.sa-table td.sa-empty { border: 0; }
    .sa-audit-pagination { display: flex; justify-content: flex-end; margin-top: 1rem; font-size: .82rem; color: var(--sa-ink-soft); }
    .sa-audit-pagination svg { width: 16px; height: 16px; }
    .sa-audit-pagination a { color: var(--sa-navy); }
    .sa-confirm { max-width: 460px; }
    #confirmDialog { align-items: center; }

    /* ===== Settings groups, R4-01 effect, eligibility ===== */
    .sa-card-head h2 + p { max-width: 70ch; }
    .sa-effects { display: grid; grid-template-columns: 1fr 1fr; gap: .75rem; margin-top: .9rem; }
    .sa-effect { padding: .75rem .9rem; border: 1px solid var(--sa-border); border-radius: var(--sa-radius); color: var(--sa-ink-soft); font-size: .8rem; line-height: 1.5; transition: border-color .12s ease, color .12s ease; }
    .sa-effect strong { display: block; margin-bottom: .15rem; color: var(--sa-navy); font-size: .8rem; font-weight: 600; }
    .sa-effect.is-active { border-color: var(--sa-navy); color: var(--sa-navy); }
    .sa-effect.is-active strong::after { content: " · active"; color: var(--sa-ink-soft); font-weight: 400; }
    @media (max-width: 640px) { .sa-effects { grid-template-columns: 1fr; } }
    .sa-subfield { margin: 0; padding: 1rem 1.1rem; border-top: 1px solid var(--sa-border); }
    .sa-subfield .gv-field { max-width: 280px; }
    .sa-list-card { padding: 0; }
    .sa-list-card > .sa-card-head { margin: 0; padding: 1.1rem 1.4rem; }
    .sa-list-card .settings-list { border: 0; border-radius: 0; }

    /* ===== R4-02 Hakbang cards ===== */
    .hakbang-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1rem; }
    .hakbang-card { display: flex; flex-direction: column; gap: .85rem; padding: 1rem; border: 1px solid var(--sa-border); border-radius: var(--sa-radius-lg); background: var(--sa-surface); }
    .hakbang-card[hidden] { display: none; }
    .hakbang-card:focus-within { border-color: var(--sa-navy); }
    .hakbang-card-head { display: flex; align-items: center; justify-content: space-between; gap: .75rem; padding-bottom: .75rem; border-bottom: 1px solid var(--sa-border); }
    .hakbang-badge { padding: .15rem .5rem; border: 1px solid var(--sa-border); border-radius: var(--sa-radius); color: var(--sa-ink-soft); font-family: var(--sa-mono); font-size: .72rem; }
    .hakbang-label { display: block; margin-bottom: .3rem; color: var(--sa-ink-soft); font-size: .76rem; font-weight: 500; }
    .hakbang-code-input { font-family: var(--sa-mono); }

    /* ===== Add Filter popover (Secretary activity) ===== */
    .sa-filterbar { margin-bottom: 1.1rem; }
    .sa-filter-row { display: flex; align-items: center; flex-wrap: wrap; gap: .6rem; }
    .sa-filter-menu { position: relative; }
    .sa-filter-pop {
        position: absolute; z-index: 50; top: calc(100% + .4rem); left: 0; width: min(340px, calc(100vw - 2rem));
        border: 1px solid var(--sa-border); border-radius: 10px; background: var(--sa-surface);
        box-shadow: 0 12px 32px rgba(0, 0, 0, .35); overflow: hidden;
    }
    .sa-filter-pop[hidden] { display: none; }
    .sa-filter-search {
        display: block; box-sizing: border-box; width: 100%; padding: .85rem 1rem; border: 0;
        border-bottom: 1px solid var(--sa-border); background: transparent; color: var(--sa-navy); font: inherit; font-size: .92rem;
    }
    .sa-filter-search:focus { outline: none; }
    .sa-filter-list { list-style: none; margin: 0; padding: .35rem; }
    .sa-filter-item {
        display: flex; width: 100%; align-items: center; justify-content: space-between; gap: .75rem;
        padding: .6rem .65rem; border: 0; border-radius: var(--sa-radius); background: transparent;
        color: var(--sa-navy); font: inherit; font-size: .9rem; text-align: left; cursor: pointer;
    }
    .sa-filter-item:hover, .sa-filter-item:focus-visible { background: var(--sa-hover); outline: none; }
    .sa-filter-item-value { display: inline-flex; align-items: center; gap: .35rem; max-width: 55%; color: var(--sa-ink-soft); font-size: .82rem; }
    .sa-filter-item-value span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .sa-filter-empty { padding: .85rem 1rem; color: var(--sa-ink-soft); font-size: .85rem; }
    .sa-filter-editor-head { display: flex; align-items: center; gap: .5rem; padding: .6rem .75rem; border-bottom: 1px solid var(--sa-border); font-size: .9rem; font-weight: 500; }
    .sa-filter-back { display: grid; place-items: center; width: 26px; height: 26px; border: 0; border-radius: var(--sa-radius); background: transparent; color: var(--sa-ink-soft); cursor: pointer; }
    .sa-filter-back:hover { background: var(--sa-hover); color: var(--sa-navy); }
    .sa-filter-editor-body { display: grid; gap: .75rem; padding: .9rem 1rem; }
    .sa-filter-editor-body label { display: block; margin-bottom: .3rem; color: var(--sa-ink-soft); font-size: .78rem; font-weight: 500; }
    .sa-filter-editor-body input {
        box-sizing: border-box; width: 100%; padding: .5rem .7rem; border: 1px solid var(--sa-border);
        border-radius: var(--sa-radius); background: var(--sa-bg); color: var(--sa-navy); font: inherit; font-size: .88rem;
    }
    .sa-filter-editor-body input:focus { outline: none; border-color: var(--sa-navy); box-shadow: 0 0 0 1px var(--sa-navy); }
    .sa-filter-editor-actions { display: flex; justify-content: flex-end; gap: .5rem; padding: 0 1rem 1rem; }
    .sa-filter-chips { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; }
    .sa-chip { display: inline-flex; align-items: stretch; border: 1px solid var(--sa-border); border-radius: 999px; background: var(--sa-surface); font-size: .82rem; overflow: hidden; }
    .sa-chip button { border: 0; background: transparent; color: inherit; font: inherit; cursor: pointer; }
    .sa-chip-label { padding: .4rem .5rem .4rem .8rem; color: var(--sa-ink-soft); }
    .sa-chip-label strong { margin-left: .25rem; color: var(--sa-navy); font-weight: 500; }
    .sa-chip-label:hover strong { text-decoration: underline; text-underline-offset: 2px; }
    .sa-chip-remove { display: grid; place-items: center; padding: 0 .6rem 0 .3rem; color: var(--sa-ink-soft); font-size: 1rem; line-height: 1; }
    .sa-chip-remove:hover { color: var(--sa-danger); }
    .sa-chip button:focus-visible { outline: 2px solid var(--sa-navy); outline-offset: -2px; }
    .sa-chip-clear { border: 0; background: transparent; color: var(--sa-ink-soft); font: inherit; font-size: .82rem; text-decoration: underline; text-underline-offset: 2px; cursor: pointer; }
    .sa-chip-clear:hover { color: var(--sa-navy); }

    /* ===== SQL dropzone: selected-file indicator ===== */
    .sql-dropzone.has-file { border-style: solid; border-color: var(--sa-navy); background: var(--sa-hover); }
    .sql-dropzone.has-file .sql-dropzone-icon { background: var(--sa-accent); border-color: var(--sa-accent); color: var(--sa-on-fg); }
    .sql-file-selected {
        display: flex; align-items: center; gap: 1rem; margin-top: .9rem; padding: 1rem 1.1rem;
        border: 1px solid var(--sa-navy); border-radius: var(--sa-radius); background: var(--sa-surface);
    }
    .sql-file-selected[hidden] { display: none; }
    .sql-file-check { display: grid; place-items: center; width: 44px; height: 44px; flex: none; border-radius: 50%; background: var(--sa-accent); color: var(--sa-on-fg); }
    .sql-file-check svg { width: 22px; height: 22px; }
    .sql-file-meta { min-width: 0; flex: 1; }
    .sql-file-meta small { display: block; margin-bottom: .15rem; color: var(--sa-ink-soft); font-size: .75rem; font-weight: 500; }
    .sql-file-meta strong { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: var(--sa-navy); font-size: 1.05rem; font-weight: 600; }
    .sql-file-meta span { color: var(--sa-ink-soft); font-size: .85rem; }

    /* ===== Import review modal (redesign) ===== */
    .sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0; }
    .sa-link-btn { padding: 0; border: 0; background: none; color: var(--sa-navy); font: inherit; font-weight: 500; text-decoration: underline; text-underline-offset: 3px; cursor: pointer; }
    .sa-link-btn:hover { color: var(--sa-ink-soft); }
    .database-import-modal { max-width: 720px; }
    .dim-title { display: flex; align-items: flex-start; gap: .85rem; }
    .dim-icon { display: grid; place-items: center; width: 40px; height: 40px; flex: none; border: 1px solid var(--sa-border); border-radius: var(--sa-radius); color: var(--sa-navy); }
    .dim-icon svg { width: 20px; height: 20px; }
    .dim-notice { display: flex; flex-wrap: wrap; gap: .25rem .6rem; margin: 0 0 .9rem; padding: .8rem 1rem; border: 1px solid var(--sa-border); border-left: 3px solid var(--sa-danger); border-radius: var(--sa-radius); background: var(--sa-bg); color: var(--sa-navy); font-size: .88rem; }
    .dim-notice span { color: var(--sa-ink-soft); }
    .dim-backup { display: flex; align-items: flex-start; gap: .75rem; margin: 0 0 1.1rem; padding: .8rem 1rem; border: 1px solid var(--sa-border); border-radius: var(--sa-radius); font-size: .85rem; color: var(--sa-ink-soft); line-height: 1.5; }
    .dim-backup p { margin: 0; }
    .dim-backup strong { color: var(--sa-navy); font-weight: 600; }
    .dim-backup-icon { display: grid; place-items: center; flex: none; color: var(--sa-navy); }
    .dim-backup-icon svg { width: 18px; height: 18px; margin-top: 2px; }
    .database-import-summary { margin: 0 0 1.1rem; }
    .database-import-summary section { display: flex; flex-direction: column; padding: .9rem 1rem; background: var(--sa-surface); }
    .database-import-summary h3 { display: flex; align-items: center; justify-content: space-between; margin: 0 0 .65rem; padding-bottom: .6rem; border-bottom: 1px solid var(--sa-border); font-size: .85rem; font-weight: 600; }
    .dim-count { min-width: 1.6rem; padding: .1rem .5rem; border-radius: 999px; background: var(--sa-accent); color: var(--sa-on-fg); font-size: .75rem; font-weight: 600; text-align: center; }
    .database-import-summary section:last-child .dim-count { background: transparent; border: 1px solid var(--sa-border); color: var(--sa-ink-soft); }
    .database-import-summary ul { display: flex; flex-wrap: wrap; align-content: flex-start; gap: .35rem; max-height: 150px; margin: 0; padding: 0 .25rem 0 0; list-style: none; overflow: auto; }
    .database-import-summary li { padding: .15rem .5rem; border: 1px solid var(--sa-border); border-radius: var(--sa-radius); color: var(--sa-navy); font-family: var(--sa-mono); font-size: .74rem; }
    .dim-confirm { padding-top: 1rem; border-top: 1px solid var(--sa-border); }
    .dim-confirm h3 { margin: 0 0 .75rem; font-size: .85rem; font-weight: 600; color: var(--sa-navy); }
    .database-import-confirm-fields { grid-template-columns: 1fr; margin-top: 0; }
    .dim-phrase { padding: .1rem .4rem; border: 1px solid var(--sa-border); border-radius: var(--sa-radius); background: var(--sa-bg); font-family: var(--sa-mono); font-size: .78rem; font-weight: 600; user-select: all; }
    .dim-match { min-height: 1rem; color: var(--sa-success); font-size: .75rem; font-weight: 500; }

    /* ===== Import result banner + backups list ===== */
    .sa-result { display: flex; align-items: center; gap: 1rem; margin-bottom: 1.25rem; padding: 1rem 1.1rem; border: 1px solid var(--sa-navy); border-radius: var(--sa-radius); background: var(--sa-surface); }
    .sa-result[hidden] { display: none; }
    .sa-result-check { display: grid; place-items: center; width: 36px; height: 36px; flex: none; border-radius: 50%; background: var(--sa-accent); color: var(--sa-on-fg); }
    .sa-result-check svg { width: 18px; height: 18px; }
    .sa-result-body { flex: 1; min-width: 0; display: grid; gap: .15rem; font-size: .85rem; color: var(--sa-ink-soft); }
    .sa-result-body strong { color: var(--sa-navy); font-size: .92rem; font-weight: 600; }
    .sa-result-body code { font-family: var(--sa-mono); color: var(--sa-navy); overflow-wrap: anywhere; }
    .sa-badge { margin-left: .5rem; padding: .1rem .45rem; border: 1px solid var(--sa-border); border-radius: var(--sa-radius); color: var(--sa-ink-soft); font-size: .68rem; font-weight: 500; }
    .sa-mono { font-family: var(--sa-mono); font-size: .82rem; overflow-wrap: anywhere; }
    .sa-skeleton { display: block; height: .8rem; border-radius: 4px; background: linear-gradient(90deg, var(--sa-border), color-mix(in srgb, var(--sa-border) 40%, transparent), var(--sa-border)); background-size: 200% 100%; animation: sa-shimmer 1.2s linear infinite; }
    @keyframes sa-shimmer { to { background-position: -200% 0; } }
    @media (prefers-reduced-motion: reduce) { .sa-skeleton { animation: none; } }

    /* ===== Backups: sources, rename, manual upload ===== */
    .sa-badge.is-manual { background: var(--sa-accent); border-color: var(--sa-accent); color: var(--sa-on-fg); }
    .sa-badge.is-source { margin-left: 0; }
    .backup-upload { display: flex; flex-wrap: wrap; align-items: center; gap: .75rem; }
    .backup-file-input { flex: 1 1 280px; min-width: 0; padding: .35rem; border: 1px solid var(--sa-border); border-radius: var(--sa-radius); background: var(--sa-bg); color: var(--sa-ink-soft); font: inherit; font-size: .85rem; }
    .backup-file-input::file-selector-button { margin-right: .75rem; padding: .4rem .8rem; border: 1px solid var(--sa-border); border-radius: var(--sa-radius); background: var(--sa-surface); color: var(--sa-navy); font: inherit; font-weight: 500; cursor: pointer; }
    .backup-file-input:focus-visible { outline: 2px solid var(--sa-navy); outline-offset: 2px; }
    .backup-actions { display: flex; justify-content: flex-end; gap: .4rem; }
    .backup-rename { display: grid; gap: .35rem; min-width: 260px; }
    .backup-rename-row { display: flex; align-items: center; gap: .4rem; }
    .backup-rename-row input { flex: 1; min-width: 0; padding: .35rem .55rem; border: 1px solid var(--sa-border); border-radius: var(--sa-radius); background: var(--sa-bg); color: var(--sa-navy); font-family: var(--sa-mono); font-size: .82rem; }
    .backup-rename-row input:focus { outline: none; border-color: var(--sa-navy); box-shadow: 0 0 0 1px var(--sa-navy); }
    .backup-rename-row span { color: var(--sa-ink-soft); font-family: var(--sa-mono); font-size: .82rem; }
    .backup-rename-error { margin: 0; color: var(--sa-danger); font-size: .78rem; }

    .sa-badge.is-import { border-color: var(--sa-navy); color: var(--sa-navy); }
    .sa-badge.is-new { background: var(--sa-success-bg); border-color: var(--sa-success); color: var(--sa-success); }
    tr.sa-row-new td { background: color-mix(in srgb, var(--sa-success) 7%, transparent); }

    /* ===== SMTP: sender card + popup ===== */
    .smtpx-card { display: flex; align-items: center; gap: 1.6rem; padding: 1.75rem; }
    .smtpx-logo-wrap { display: grid; place-items: center; flex: none; width: 96px; height: 96px; border: 1px solid var(--sa-border); border-radius: var(--sa-radius); background: var(--sa-bg); }
    .smtpx-logo { width: 60px; height: 60px; object-fit: contain; }
    .smtpx-modal-logo { width: 22px; height: 22px; object-fit: contain; }
    .smtpx-body { min-width: 0; flex: 1; }
    .smtpx-body[hidden] { display: none; }
    .smtpx-label { margin: 0 0 .35rem; color: var(--sa-ink-soft); font-size: .72rem; font-weight: 500; letter-spacing: .02em; }
    .smtpx-title { margin: 0; color: var(--sa-navy); font-size: 1.15rem; font-weight: 600; letter-spacing: -.02em; overflow-wrap: anywhere; }
    .smtpx-email-row { display: flex; align-items: center; gap: .6rem; }
    .smtpx-mark { display: grid; place-items: center; width: 22px; height: 22px; flex: none; border-radius: 50%; font-size: .8rem; font-weight: 700; }
    .smtpx-mark svg { width: 13px; height: 13px; }
    .smtpx-mark.is-ok { background: var(--sa-success); color: #fff; }
    .smtpx-mark.is-warn { border: 1px solid var(--sa-danger); color: var(--sa-danger); }
    .smtpx-status { margin: .35rem 0 0; color: var(--sa-ink-soft); font-size: .82rem; font-weight: 500; }
    .smtpx-text { margin: .7rem 0 0; max-width: 62ch; color: var(--sa-ink-soft); font-size: .85rem; line-height: 1.55; }
    .smtpx-actions { display: flex; flex-wrap: wrap; align-items: center; gap: .6rem; margin-top: 1.2rem; }
    .smtpx-remove { color: var(--sa-ink-soft); font-size: .82rem; margin-left: .35rem; }
    .smtpx-remove:hover { color: var(--sa-danger); }
    .smtpx-modal { max-width: 460px; }
    #smtpModal { align-items: center; }
    .smtpx-warn { margin: 0 0 1rem; padding: .7rem .9rem; border: 1px solid var(--sa-border); border-left: 3px solid var(--sa-danger); border-radius: var(--sa-radius); color: var(--sa-navy); font-size: .82rem; line-height: 1.5; }
    .smtpx-warn[hidden] { display: none; }
    #smtpModal .smtp-code-input { width: 100%; box-sizing: border-box; }
    #smtpModal [hidden] { display: none !important; }
    .smtp-field-hint a { color: var(--sa-navy); font-weight: 500; text-underline-offset: 3px; }
    .smtp-verify-code label { display: block; margin-bottom: .4rem; color: var(--sa-navy); font-size: .8rem; font-weight: 500; }
    .smtp-done { display: grid; justify-items: center; gap: .5rem; padding: .5rem 0 0; text-align: center; }
    .smtp-done h2 { margin: .4rem 0 0; font-size: 1.1rem; font-weight: 600; color: var(--sa-navy); }
    .smtp-done p { margin: 0; color: var(--sa-ink-soft); font-size: .88rem; line-height: 1.5; overflow-wrap: anywhere; }
    @media (max-width: 560px) { .smtpx-card { flex-direction: column; align-items: flex-start; } }
    .smtp-verify-label { display: block; margin-bottom: .55rem; color: var(--sa-navy); font-size: .8rem; font-weight: 500; }
    .otp { display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); gap: .5rem; }
    .otp-box {
        width: 100%; box-sizing: border-box; aspect-ratio: 1 / 1.12; padding: 0; text-align: center;
        border: 1px solid var(--sa-border); border-radius: var(--sa-radius); background: var(--sa-bg); color: var(--sa-navy);
        font-family: var(--sa-mono); font-size: 1.5rem; font-weight: 600; caret-color: var(--sa-navy);
        transition: border-color .12s ease, box-shadow .12s ease;
    }
    .otp-box:focus { outline: none; border-color: var(--sa-navy); box-shadow: 0 0 0 1px var(--sa-navy); background: var(--sa-surface); }
    .otp-box.filled { border-color: var(--sa-ink-soft); }
    .otp.is-error .otp-box { border-color: var(--sa-danger); animation: otp-shake .3s ease; }
    @keyframes otp-shake { 25% { transform: translateX(-3px); } 75% { transform: translateX(3px); } }
    @media (prefers-reduced-motion: reduce) { .otp.is-error .otp-box { animation: none; } }
</style>

<div class="sa-page">

    <div class="sa-header">
        <h1>Superadmin Dashboard</h1>
        <p>Manage secretary access, document settings, and system configuration.</p>
    </div>

    @unless ($smtpConfigured)
        <aside class="smtp-setup-alert" id="smtpSetupAlert" role="status">
            <div class="smtp-setup-alert-copy">
                <span class="smtp-setup-alert-icon" aria-hidden="true">!</span>
                <div>
                    <strong>{{ $smtpNeedsVerification ? 'Email delivery is not verified' : 'Email delivery is not configured' }}</strong>
                    <p id="smtpSetupAlertText">
                        @if ($smtpNeedsVerification)
                            The SMTP account is saved but has not been confirmed. Send a verification code to the Head Secretary's Gmail to finish setup.
                        @elseif ($smtpRequiredForAuthentication)
                            Email verification and password recovery are enabled, but these emails cannot be delivered until SMTP is set up.
                        @else
                            Configure SMTP to enable system email delivery, including account verification and password recovery.
                        @endif
                    </p>
                </div>
            </div>
            <button class="sa-btn sa-btn-primary" type="button" data-open-smtp>Configure SMTP</button>
        </aside>
    @endunless

    <div class="sa-shell">
    <nav class="sa-tabs" aria-label="Superadmin sections">
        <div class="sa-nav-group">Access</div>
        <button type="button" class="sa-tab-btn active" data-tab="users"><svg width="16" height="16" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="7" cy="6.5" r="3"/><path d="M1.5 17c.8-3 3-5 5.5-5s4.7 2 5.5 5"/><circle cx="15" cy="7" r="2.3"/><path d="M13 11.3c2 .2 3.6 1.9 4.2 4.2"/></svg> Secretaries management</button>
        <button type="button" class="sa-tab-btn" data-tab="record-audit"><svg width="16" height="16" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="10" cy="10" r="7.3"/><path d="M10 6v4l2.5 1.5"/></svg> Secretary activity</button>
        <div class="sa-nav-group">Documents</div>
        <button type="button" class="sa-tab-btn" data-tab="globals"><svg width="16" height="16" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h9M15 6h2M3 14h2M8 14h9"/><circle cx="13.5" cy="6" r="1.8"/><circle cx="6.5" cy="14" r="1.8"/></svg> Document settings</button>
        <button type="button" class="sa-tab-btn" data-tab="signatories"><svg width="16" height="16" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M13.5 3.5l3 3L7 16H4v-3z"/><path d="M12 5l3 3"/></svg> Purok signatories</button>
        <button type="button" class="sa-tab-btn" data-tab="r402-hakbangs"><svg width="16" height="16" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 5h10M7 10h10M7 15h10M3 5h.01M3 10h.01M3 15h.01"/></svg> R4-02 Hakbang</button>
        <div class="sa-nav-group">Eligibility</div>
        <button type="button" class="sa-tab-btn" data-tab="eligibility"><svg width="16" height="16" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="8" cy="6.5" r="3"/><path d="M2 17c.8-3 3-5 6-5s4 1 5 3"/><path d="M13 12l2 2 3-3.5"/></svg> R2-10 eligibility</button>
        <div class="sa-nav-group">System</div>
        <button type="button" class="sa-tab-btn" data-tab="security"><svg width="16" height="16" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 2l7 3v5c0 4.5-3 7.5-7 8-4-.5-7-3.5-7-8V5l7-3z"/><path d="M7.2 10l1.9 1.9L13 8"/></svg> Security</button>
        <button type="button" class="sa-tab-btn" data-tab="smtp-settings"><svg width="16" height="16" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="14" height="10" rx="1"/><path d="M3 6l7 5 7-5"/></svg> Email delivery</button>
        <button type="button" class="sa-tab-btn" data-tab="database"><svg width="16" height="16" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><ellipse cx="10" cy="5" rx="6" ry="2.5"/><path d="M4 5v10c0 1.4 2.7 2.5 6 2.5s6-1.1 6-2.5V5M4 10c0 1.4 2.7 2.5 6 2.5s6-1.1 6-2.5"/></svg> Database configuration</button>
        <button type="button" class="sa-tab-btn" data-tab="backups"><svg width="16" height="16" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="14" height="4"/><path d="M4 8v8h12V8M8 12h4"/></svg> Database backups</button>
        <button type="button" class="sa-tab-btn" data-tab="maintenance"><svg width="16" height="16" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 10a6 6 0 1 1-2-4.5M16 3v3.5h-3.5"/></svg> BNH form reset</button>
    </nav>
    <div class="sa-content">

    <!-- ============ SECRETARIES MANAGEMENT ============ -->
    <div class="sa-tab-panel active" id="tab-users">
        <header class="sa-panel-head"><h2>Secretaries management</h2><p>Assign Tungkulin, control access, and transfer the Head Secretary role.</p></header>
        <div class="sa-page-actions">
            <button type="button" class="sa-btn sa-btn-ghost" id="editPermissionsBtn">
                <svg width="15" height="15" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M10 2l7 3v5c0 4.5-3 7.5-7 8-4-.5-7-3.5-7-8V5l7-3z"/><path d="M7.2 10l1.9 1.9L13 8"/></svg>
                Edit Permissions of Secretaries
            </button>
        </div>
        <div class="sa-card">
            <div class="sa-card-head">
                <div>
                    <h2>Transfer Head Secretary role</h2>
                    <p>Transfers the role atomically: the current account becomes Secretary and the selected verified Secretary becomes Head Secretary.</p>
                </div>
            </div>
            <div class="head-secretary-transfer-controls">
                <label class="gv-field" for="headSecretaryTransferTarget">
                    <span>Verified Secretary</span>
                    <select id="headSecretaryTransferTarget" class="sa-select" @disabled($headSecretaryTransferCandidates->isEmpty())>
                        <option value="">Select an account</option>
                        @foreach ($headSecretaryTransferCandidates as $candidate)
                            <option value="{{ $candidate->id }}">{{ trim(($candidate->first_name ?? '').' '.($candidate->last_name ?? '')) ?: $candidate->gmail }} · {{ $candidate->gmail }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="gv-field" for="headSecretaryTransferPassword">
                    <span>Confirm with your password</span>
                    <input type="password" id="headSecretaryTransferPassword" autocomplete="current-password" required @disabled($headSecretaryTransferCandidates->isEmpty())>
                </label>
                <button type="button" class="sa-btn sa-btn-danger" id="transferHeadSecretaryBtn" @disabled($headSecretaryTransferCandidates->isEmpty())>Transfer role</button>
            </div>
            @if ($headSecretaryTransferCandidates->isEmpty())
                <p class="gv-hint">There are no other verified Secretary accounts available to receive the role.</p>
            @endif
        </div>
        <div class="sa-card">
            <div class="sa-card-head">
                <div>
                    <h2>Secretaries</h2>
                    <p>Assign roles and grant or revoke system access.</p>
                </div>
                <div class="sa-card-head-actions">
                    <select id="userStatusFilter" class="sa-select" aria-label="Filter by status">
                        <option value="">All statuses</option>
                        <option value="1">Verified</option>
                        <option value="0">Not verified</option>
                    </select>
                    <input type="search" class="sa-search" id="userSearch" aria-label="Search secretaries" placeholder="Search by name or email...">
                </div>
            </div>

            <span class="sa-table-meta" id="usersCount" aria-live="polite"></span>
            <div class="sa-table-wrap">
                <table class="sa-table" id="usersTable">
                    <thead>
                        <tr>
                            <th scope="col">Secretary</th>
                            <th scope="col">Tungkulin</th>
                            <th scope="col">Access</th>
                            <th scope="col">Status</th>
                        </tr>
                    </thead>
                    <tbody id="usersTableBody">
                        @forelse ($users as $user)
                            @php
                                $userName = trim(($user->first_name ?? '').' '.($user->last_name ?? '')) ?: $user->gmail;
                                $roleOptions = ['Secretary', 'Head Secretary'];
                                if ($user->tungkulin && !in_array($user->tungkulin, $roleOptions, true)) {
                                    $roleOptions[] = $user->tungkulin;
                                }
                            @endphp
                            <tr data-user-id="{{ $user->id }}" data-name="{{ strtolower($userName) }}" data-email="{{ strtolower($user->gmail) }}" data-verified="{{ $user->is_verified ? 1 : 0 }}">
                                <td>
                                    <div class="sa-user-cell">
                                        <span class="sa-user-avatar" aria-hidden="true">{{ strtoupper(substr($userName, 0, 1)) }}</span>
                                        <div>
                                            <div class="sa-user-name">{{ $userName }}</div>
                                            <div class="sa-user-email">{{ $user->gmail }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <select class="sa-select tungkulin-select" data-previous-value="{{ $user->tungkulin }}" aria-label="Tungkulin for {{ $userName }}">
                                        @foreach ($roleOptions as $role)
                                            <option value="{{ $role }}" @selected($user->tungkulin === $role)>{{ $role }}</option>
                                        @endforeach
                                        @if (!$user->tungkulin)
                                            <option value="" selected>Not assigned</option>
                                        @endif
                                    </select>
                                </td>
                                <td>
                                    <label class="sa-toggle">
                                        <input type="checkbox" class="access-toggle" @checked($user->is_verified)>
                                        <span class="sa-toggle-track"></span>
                                    </label>
                                </td>
                                <td>
                                    <span class="sa-status-pill {{ $user->is_verified ? 'on' : 'off' }} status-pill">
                                        <svg viewBox="0 0 8 8" fill="currentColor"><circle cx="4" cy="4" r="4"/></svg>
                                        {{ $user->is_verified ? 'Verified' : 'Not Verified' }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="sa-empty">No secretaries found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="sa-empty" id="usersEmptyState" style="display:none;">No secretaries match your filters.</div>
            </div>
        </div>
    </div>

    <div class="sa-tab-panel" id="tab-record-audit">
        <div class="sa-card">
            <div class="sa-card-head">
                <div>
                    <h2>Secretary activity</h2>
                    <p>Record changes with the acting account, date and changed values. Newest actions appear first.</p>
                </div>
                <a class="sa-btn sa-btn-primary" href="{{ route('superadmin.record-activity.download', array_filter($auditFilters, fn ($value) => $value !== null && $value !== '')) }}">
                    Download Word
                </a>
            </div>
            <form method="GET" action="{{ route('superadmin-dashboard') }}#record-audit" class="sa-filterbar" id="recordAuditFilters">
                <input type="hidden" name="tab" value="record-audit">
                <input type="hidden" name="date_from" value="{{ $auditFilters['date_from'] ?? '' }}">
                <input type="hidden" name="date_to" value="{{ $auditFilters['date_to'] ?? '' }}">
                <input type="hidden" name="record_name" value="{{ $auditFilters['record_name'] ?? '' }}">
                <input type="hidden" name="actor_email" value="{{ $auditFilters['actor_email'] ?? '' }}">
                <div class="sa-filter-row">
                    <div class="sa-filter-menu">
                        <button type="button" class="sa-btn sa-btn-ghost" id="addFilterBtn" aria-haspopup="true" aria-expanded="false" aria-controls="filterPopover">
                            <svg width="16" height="16" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" aria-hidden="true"><path d="M3 5h14M6 10h8M8.5 15h3"/></svg>
                            Add Filter
                        </button>
                        <div class="sa-filter-pop" id="filterPopover" role="dialog" aria-label="Add filter" hidden>
                            <div id="filterListView">
                                <input type="search" id="filterSearch" class="sa-filter-search" placeholder="Filter by…" aria-label="Filter by" autocomplete="off">
                                <ul class="sa-filter-list" id="filterList"></ul>
                            </div>
                            <div id="filterEditor" hidden></div>
                        </div>
                    </div>
                    <div class="sa-filter-chips" id="filterChips" aria-live="polite"></div>
                </div>
                @if ($errors->any())
                    <p class="sa-audit-filter-error" role="alert">{{ $errors->first() }}</p>
                @endif
            </form>
            <div class="sa-table-wrap">
                <table class="sa-table">
                    <thead>
                        <tr>
                            <th scope="col">Date of action</th>
                            <th scope="col">Account</th>
                            <th scope="col">Record</th>
                            <th scope="col">Action details</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recordAuditLogs as $auditLog)
                            <tr>
                                <td>{{ $auditLog->created_at?->timezone('Asia/Manila')->format('M d, Y h:i:s A') }}</td>
                                <td>{{ $auditLog->actor_email ?: 'System / background task' }}</td>
                                <td>{{ $auditLog->record_name ?: 'Deleted record' }}{{ $auditLog->record_id ? ' (#'.$auditLog->record_id.')' : '' }}</td>
                                <td>{{ $auditLog->summary }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="sa-empty">No record changes have been logged yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($recordAuditLogs->hasPages())
                <div class="sa-audit-pagination">{{ $recordAuditLogs->links() }}</div>
            @endif
        </div>
    </div>

    <!-- ============ EMAIL DELIVERY (single Gmail sender) ============ -->
    <div class="sa-tab-panel" id="tab-smtp-settings">
        <header class="sa-panel-head"><h2>Email delivery</h2><p>The Gmail account LSPDMS uses for sign-in codes and password recovery. Only one account can be connected.</p></header>

        <div class="sa-card smtpx-card">
            <div class="smtpx-logo-wrap"><svg class="smtpx-logo" viewBox="0 0 48 48" aria-hidden="true"><path fill="#4caf50" d="M45,16.2l-5,2.75l-5,4.75L35,40h7c1.657,0,3-1.343,3-3V16.2z"/><path fill="#1e88e5" d="M3,16.2l3.614,1.71L13,23.7V40H6c-1.657,0-3-1.343-3-3V16.2z"/><polygon fill="#e53935" points="35,11.2 24,19.45 13,11.2 12,17 13,23.7 24,31.95 35,23.7 36,17"/><path fill="#c62828" d="M3,12.298V16.2l10,7.5V11.2L9.876,8.859C9.132,8.301,8.228,8,7.298,8h0C4.924,8,3,9.924,3,12.298z"/><path fill="#fbc02d" d="M45,12.298V16.2l-10,7.5V11.2l3.124-2.341C38.868,8.301,39.772,8,40.702,8h0C43.076,8,45,9.924,45,12.298z"/></svg></div>

            {{-- No account connected --}}
            <div class="smtpx-body" id="smtpEmpty" hidden>
                <p class="smtpx-label">Sender</p>
                <h2 class="smtpx-title">No Gmail connected</h2>
                <p class="smtpx-text">
                    Connect a Gmail account to send sign-in codes and password recovery emails.
                    @if ($smtpConfiguredFromEnvironment)
                        Emails currently send using the server's environment settings.
                    @endif
                </p>
                <div class="smtpx-actions">
                    <button type="button" class="sa-btn sa-btn-primary" id="smtpConnectBtn">Connect Gmail</button>
                </div>
            </div>

            {{-- Account connected --}}
            <div class="smtpx-body" id="smtpAccountView" hidden>
                <p class="smtpx-label">Current sender</p>
                <div class="smtpx-email-row">
                    <span class="smtpx-mark" id="smtpAccountIcon" aria-hidden="true"></span>
                    <h2 class="smtpx-title" id="smtpAccountEmail"></h2>
                </div>
                <p class="smtpx-status" id="smtpAccountStatus"></p>
                <p class="smtpx-text" id="smtpAccountNote"></p>
                <div class="smtpx-actions">
                    <button type="button" class="sa-btn sa-btn-primary" id="smtpCodeBtn" hidden>Enter code</button>
                    <button type="button" class="sa-btn sa-btn-ghost" id="smtpChangeBtn">Change Gmail</button>
                    <button type="button" class="sa-link-btn smtpx-remove" id="smtpRemoveBtn">Remove</button>
                </div>
            </div>
        </div>
    </div>

    <div class="sa-tab-panel" id="tab-database">
        <header class="sa-panel-head"><h2>Database configuration</h2><p>Export, import, and back up LSPDMS data.</p></header>
        <div class="sa-card">
            <div class="sa-card-head">
                <div>
                    <h2>Export Database</h2>
                    <p>Download the database as one portable SQL file. Optionally exclude account or message tables.</p>
                </div>
            </div>
            <form method="GET" action="{{ route('superadmin.database.export-sql') }}">
                <div class="gv-grid">
                    <label class="delist-check-row" for="excludeLoginUser">
                        <input id="excludeLoginUser" type="checkbox" name="exclude_tables[]" value="login_user">
                        <span class="delist-check-text"><strong>Exclude login_user</strong><small>Omit account and sign-in records.</small></span>
                    </label>
                    <label class="delist-check-row" for="excludeMessages">
                        <input id="excludeMessages" type="checkbox" name="exclude_tables[]" value="messages">
                        <span class="delist-check-text"><strong>Exclude messages</strong><small>Omit private message records and attachments metadata.</small></span>
                    </label>
                </div>
                <div class="sa-save-bar">
                    <button type="submit" class="sa-btn sa-btn-primary">Download entire database (lspdms.sql)</button>
                </div>
            </form>
            <p class="sa-note" style="margin-top:1.2rem;">Unchecked tables are included. SMTP credentials are always excluded.</p>
        </div>

        <div class="sa-card">
            <div class="sa-card-head">
                <div>
                    <h2>Import Database</h2>
                    <p>Restore an LSPDMS SQL backup from your computer.</p>
                </div>
            </div>
            <p class="sa-note">Import accepts LSPDMS SQL backups for the same database engine. Run Laravel migrations on the destination PC first.</p>
            <form id="databaseImportForm" enctype="multipart/form-data">
                @csrf
                <label class="sql-dropzone" id="databaseSqlDropzone" for="databaseSqlFile">
                    <span class="sql-dropzone-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 16V4m0 0L7.5 8.5M12 4l4.5 4.5"/><path d="M5 14v4a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-4"/></svg>
                    </span>
                    <span class="sql-dropzone-title">Drop your SQL backup here</span>
                    <span class="sql-dropzone-hint">Drag and drop an <strong>.sql</strong> file, or click to browse your computer</span>
                    <input class="sql-file-input" id="databaseSqlFile" name="sql_file" type="file" accept=".sql,application/sql" required>
                    <span class="sql-file-status" id="databaseSqlFileStatus" aria-live="polite">No file selected.</span>
                </label>
                <div class="sql-file-selected" id="databaseSqlSelected" role="status" hidden>
                    <span class="sql-file-check" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg></span>
                    <div class="sql-file-meta">
                        <small>File selected</small>
                        <strong id="databaseSqlSelectedName"></strong>
                        <span id="databaseSqlSelectedSize"></span>
                    </div>
                    <button type="button" class="sa-btn sa-btn-ghost sa-btn-sm" id="databaseSqlClear">Remove</button>
                </div>
                <p class="sql-file-error" id="databaseSqlFileError" role="alert" hidden></p>
                <p class="gv-hint">Before applying an import, LSPDMS creates a private safety backup. Data in tables included in the file is replaced. Tables omitted from the file are retained. <button type="button" class="sa-link-btn" data-open-backups>View backups</button></p>
                <div class="sa-save-bar">
                    <button type="submit" class="sa-btn sa-btn-danger" id="importDatabaseBtn" disabled>Review and Import SQL</button>
                </div>
            </form>
        </div>

        <div class="sa-card">
            <div class="sa-card-head">
                <div>
                    <h2>Optional Excel table export</h2>
                    <p>For a partial spreadsheet export, select specific tables. Use Export Database above for the complete backup.</p>
                </div>
            </div>
            <form method="POST" action="{{ route('superadmin.database.export-xlsx') }}" id="databaseXlsxForm">
                @csrf
                <div class="gv-grid">
                    @foreach ($databaseTables as $table)
                        <label class="delist-check-row" for="exportTable{{ $loop->index }}">
                            <input id="exportTable{{ $loop->index }}" type="checkbox" name="tables[]" value="{{ $table }}">
                            <span class="delist-check-text"><strong>{{ $table }}</strong></span>
                        </label>
                    @endforeach
                </div>
                <div class="sa-save-bar">
                    <button type="submit" class="sa-btn sa-btn-primary">Export Selected Tables to XLSX</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ============ DATABASE BACKUPS ============ -->
    <div class="sa-tab-panel" id="tab-backups">
        <header class="sa-panel-head"><h2>Database backups</h2><p>Safety copies made before each import, the monthly automatic backup, and backups you add yourself.</p></header>
        <div class="sa-result" id="databaseImportResult" role="status" hidden>
            <span class="sa-result-check" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg></span>
            <div class="sa-result-body">
                <strong id="databaseImportResultMessage">Database import complete.</strong>
                <span>Safety backup saved. It is listed below as <strong>Before import</strong>: <code id="databaseImportResultFile"></code></span>
            </div>
            <button type="button" class="sa-modal-close" id="dismissImportResult" aria-label="Dismiss">&times;</button>
        </div>

        <div class="sa-card">
            <div class="sa-card-head">
                <div>
                    <h2>Add a manual backup</h2>
                    <p>Export the database yourself, then save the file here. The export must include the <code class="dim-phrase">login_user</code> and <code class="dim-phrase">messages</code> tables. Saving a manual backup does not change the live database.</p>
                </div>
            </div>
            <div class="backup-upload">
                <input class="backup-file-input" id="backupUploadFile" type="file" accept=".sql" aria-label="Choose an .sql export to save as a manual backup">
                <button type="button" class="sa-btn sa-btn-primary" id="backupUploadBtn" disabled>Save as manual backup</button>
            </div>
            <div class="sql-file-selected" id="backupUploadSelected" role="status" hidden>
                <span class="sql-file-check" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg></span>
                <div class="sql-file-meta">
                    <small>File selected</small>
                    <strong id="backupUploadName"></strong>
                    <span id="backupUploadSize"></span>
                </div>
                <button type="button" class="sa-btn sa-btn-ghost sa-btn-sm" id="backupUploadClear">Remove</button>
            </div>
            <p class="sql-file-error" id="backupUploadError" role="alert" hidden></p>
        </div>

        <div class="sa-card">
            <div class="sa-card-head">
                <div>
                    <h2>Saved backups</h2>
                    <p>Stored privately on the server and not publicly accessible. Backups are kept until you remove them; nothing is deleted automatically.</p>
                </div>
                <button type="button" class="sa-btn sa-btn-ghost" id="refreshBackupsBtn">Refresh</button>
            </div>
            <div class="hakbang-status-tabs" role="group" aria-label="Filter backups by source">
                <button type="button" class="hakbang-status-btn active" data-backup-source="" aria-pressed="true">All</button>
                <button type="button" class="hakbang-status-btn" data-backup-source="import" aria-pressed="false">Before import</button>
                <button type="button" class="hakbang-status-btn" data-backup-source="auto" aria-pressed="false">Monthly auto</button>
                <button type="button" class="hakbang-status-btn" data-backup-source="manual" aria-pressed="false">Manual</button>
            </div>
            <span class="sa-table-meta" id="backupsCount" aria-live="polite"></span>
            <div class="sa-table-wrap">
                <table class="sa-table" id="backupsTable">
                    <thead>
                        <tr>
                            <th scope="col">File</th>
                            <th scope="col">Source</th>
                            <th scope="col">Created</th>
                            <th scope="col">Size</th>
                            <th scope="col"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody id="backupsBody"></tbody>
                </table>
                <div class="sa-empty" id="backupsState" role="status" hidden></div>
            </div>
        </div>
    </div>

    <!-- ============ SIGNATORIES ============ -->
    <div class="sa-tab-panel" id="tab-signatories">
        <header class="sa-panel-head"><h2>Purok signatories</h2><p>Assign each Purok header number, Secretary, Manggagawa, and Sakop na Grupo range.</p></header>
        <div class="sa-card">
            <div class="sa-card-head">
                <div>
                    <h2>Purok Signatories</h2>
                    <p>Add Puroks, assign their header number, Secretary and Manggagawa, and define each Sakop na Grupo range.</p>
                </div>
            </div>

            <div class="sa-note">
                The Assigned Number is the shared Purok header, not a unique ID. Multiple Puroks may use the same number when a header is split. Set each Purok's Sakop na Grupo range to distinguish its groups; tick "Complete Purok" only when it includes every group under that header.
            </div>

            <div class="purok-list" id="purokList">
                @foreach ($puroks as $purok)
                    <div class="purok-card" data-id="{{ $purok->id }}">
                        <div class="purok-head">
                            <div class="purok-head-left">
                                <svg width="17" height="17" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" style="color:var(--sa-ink-soft); flex-shrink:0;"><path d="M3 8l7-5 7 5v9H3z"/><path d="M8 17v-5h4v5"/></svg>
                                <div class="purok-name-wrap">
                                    <div class="purok-name-view">
                                        <span class="purok-name-display">{{ $purok->purok_name ?: 'Purok '.$purok->purok_number }}</span>
                                        <button type="button" class="purok-edit-btn" title="Edit Purok name" aria-label="Edit Purok name"><svg width="15" height="15" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M13.5 3.5l3 3L7 16H4v-3z"/><path d="M12 5l3 3"/></svg></button>
                                    </div>
                                    <div class="purok-name-edit">
                                        <input type="text" class="purok-name-input" value="{{ $purok->purok_name ?: 'Purok '.$purok->purok_number }}" maxlength="40">
                                        <button type="button" class="sa-btn sa-btn-primary sa-btn-sm purok-name-save">Save</button>
                                        <button type="button" class="sa-btn sa-btn-ghost sa-btn-sm purok-name-cancel">Cancel</button>
                                    </div>
                                </div>
                            </div>
                            <button type="button" class="sa-btn sa-btn-danger sa-btn-sm remove-purok-btn">Remove</button>
                        </div>
                        <div class="purok-body">
                            <div class="signatory-row">
                                <span class="signatory-role-badge">Header Number</span>
                                <input type="number" class="signatory-input purok-number-input" placeholder="e.g. 1" min="1" value="{{ $purok->purok_number }}">
                            </div>
                            <div class="signatory-row">
                                <span class="signatory-role-badge">Secretary</span>
                                <select class="signatory-input secretary-input">
                                    <option value="">Not assigned</option>
                                    @foreach ($users as $user)
                                        <option value="{{ $user->id }}" @selected($purok->secretary === $user->id)>{{ trim(($user->first_name ?? '').' '.($user->last_name ?? '')) ?: $user->gmail }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="signatory-row">
                                <span class="signatory-role-badge">Manggagawa</span>
                                <input type="text" class="signatory-input manggagawa-input" placeholder="Full name" maxlength="255" value="{{ $purok->manggagawa }}">
                            </div>
                            <div class="signatory-row">
                                <span class="signatory-role-badge">ID Number</span>
                                <input type="text" class="signatory-input purok-id-input" placeholder="Ministerial ID number" value="{{ $purok->ministerial_id }}">
                            </div>
                            <label class="complete-row">
                                <input type="checkbox" class="complete-purok-check" @checked($purok->full_purok)>
                                <div class="complete-row-text">
                                    <strong>Complete Purok</strong>
                                    <span>Includes every group that starts with this Purok number (e.g. 1-1, 1-2, 1-3 ...).</span>
                                </div>
                            </label>
                            <div class="sakop-row {{ $purok->full_purok ? 'disabled' : '' }}">
                                <span class="signatory-role-badge">Sakop na Grupo</span>
                                <div>
                                    <div class="sakop-range-inputs">
                                        <input type="number" class="signatory-input sakop-from" placeholder="From" min="0" value="{{ $purok->grupo_start }}" @disabled($purok->full_purok)>
                                        <span>to</span>
                                        <input type="number" class="signatory-input sakop-to" placeholder="To" min="0" value="{{ $purok->grupo_end }}" @disabled($purok->full_purok)>
                                    </div>
                                    <div class="sakop-preview"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
                @if ($puroks->isEmpty())
                    <div class="sa-empty" id="noPuroksMessage">No Purok signatories are configured yet.</div>
                @endif
            </div>

            <button type="button" class="add-purok-btn" id="addPurokBtn" style="margin-top:1rem;">
                <svg width="16" height="16" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M10 4v12M4 10h12"/></svg>
                Add Purok
            </button>

            <div class="sa-save-bar">
                <button type="button" class="sa-btn sa-btn-ghost" id="discardSignatoriesBtn">Discard</button>
                <button type="button" class="sa-btn sa-btn-primary" id="saveSignatoriesBtn" disabled>Save Signatories</button>
            </div>
        </div>
    </div>

    <!-- ============ SECURITY SETTINGS ============ -->
    <div class="sa-tab-panel" id="tab-security">
        <header class="sa-panel-head"><h2>Security</h2><p>Sign-in and password recovery behavior.</p></header>
        <div class="sa-card">
            <div class="sa-card-head">
                <div>
                    <h2>Security Settings</h2>
                    <p>General on/off settings for the system. Not related to de-listment requirements — rename or add to these as needed.</p>
                </div>
            </div>

            @unless ($smtpConfigured)
                <div class="smtp-security-alert" id="smtpSecurityAlert" @if (!$smtpRequiredForAuthentication) hidden @endif>
                    Email-based security features need a working SMTP account. Configure it before relying on OTP or password recovery.
                    <button type="button" data-open-smtp>Open SMTP settings</button>
                </div>
            @endunless

            <div class="settings-list">
                <div class="settings-row">
                    <div class="settings-row-text">
                        <label for="requireOtpSetting">Require OTP</label>
                        <span>When enabled, every account must verify an emailed code after entering a password.</span>
                    </div>
                    <label class="sa-toggle">
                        <input type="checkbox" id="requireOtpSetting" @checked($globalVariables?->require_otp ?? false)>
                        <span class="sa-toggle-track"></span>
                    </label>
                </div>
                <div class="settings-row">
                    <div class="settings-row-text">
                        <label for="allowForgotPasswordSetting">Allow Forgot Password</label>
                        <span>When disabled, the forgot-password link and password reset endpoints are unavailable.</span>
                    </div>
                    <label class="sa-toggle">
                        <input type="checkbox" id="allowForgotPasswordSetting" @checked($globalVariables?->allow_forgot_password ?? false)>
                        <span class="sa-toggle-track"></span>
                    </label>
                </div>
            </div>

            <div class="sa-save-bar">
                <button type="button" class="sa-btn sa-btn-ghost" id="discardSecuritySettingsBtn">Discard</button>
                <button type="button" class="sa-btn sa-btn-primary" id="saveSecuritySettingsBtn">Save Security Settings</button>
            </div>
        </div>
    </div>

    <!-- ============ DOCUMENT SETTINGS (global variables) ============ -->
    <div class="sa-tab-panel" id="tab-globals">
        <header class="sa-panel-head"><h2>Document settings</h2><p>Values used on generated forms and documents.</p></header>
        <div class="sa-card">
            <div class="sa-card-head">
                <div>
                    <h2>Congregation identity</h2>
                    <p>Locale and district codes printed in form headers.</p>
                </div>
            </div>
            <div class="gv-grid">
                <div class="gv-field">
                    <label for="gvLocale">Locale</label>
                    <input type="text" id="gvLocale" placeholder="Locale" value="{{ $globalVariables?->locale }}">
                </div>
                <div class="gv-field">
                    <label for="gvLcode">Lcode</label>
                    <input type="text" id="gvLcode" placeholder="Lcode" value="{{ $globalVariables?->lcode }}">
                </div>
                <div class="gv-field">
                    <label for="gvDistrict">District</label>
                    <input type="text" id="gvDistrict" placeholder="District" value="{{ $globalVariables?->district }}">
                </div>
                <div class="gv-field">
                    <label for="gvDcode">DCode</label>
                    <input type="text" id="gvDcode" placeholder="DCode" value="{{ $globalVariables?->dcode }}">
                </div>
                <div class="gv-field">
                    <label for="gvSNumber">S Number</label>
                    <input type="text" id="gvSNumber" placeholder="e.g. S-123" value="{{ $globalVariables?->s_number }}">
                </div>
            </div>
        </div>

        <div class="sa-card">
            <div class="sa-card-head">
                <div>
                    <h2>Officers</h2>
                    <p>Names of the local officers used in generated forms.</p>
                </div>
            </div>
            <div class="gv-grid">
                <div class="gv-field">
                    <label for="gvPastor">Pastor / Destinado</label>
                    <input type="text" id="gvPastor" placeholder="Full name" value="{{ $globalVariables?->pastor_destinado }}">
                </div>
                <div class="gv-field">
                    <label for="gvKsp">KSP ng Lokal / Pangulong Diakono</label>
                    <input type="text" id="gvKsp" placeholder="Full name" value="{{ $globalVariables?->ksp_pd }}">
                    <div class="gv-hint">Also used as the Pangulong Diakono in generated forms.</div>
                </div>
                <div class="gv-field">
                    <label for="gvHeadLsp">Head LSP</label>
                    <input type="text" id="gvHeadLsp" placeholder="Full name" value="{{ $globalVariables?->head_lsp }}">
                </div>
            </div>
        </div>

        <div class="sa-card">
            <div class="sa-card-head">
                <div>
                    <h2>R4-01 signatories</h2>
                    <p>Choose who signs each R4-01 page: the global Kalihim names below, or the Secretary assigned to each Purok.</p>
                </div>
            </div>
            <div class="gv-grid">
                <div class="gv-field">
                    <label for="gvKalihimMs">Kalihim ng MS</label>
                    <input type="text" id="gvKalihimMs" placeholder="Full name" value="{{ $globalVariables?->kalihim_ms }}">
                </div>
                <div class="gv-field">
                    <label for="gvKalihimUwp">Kalihim ng UWP</label>
                    <input type="text" id="gvKalihimUwp" placeholder="Full name" value="{{ $globalVariables?->kalihim_uwp }}">
                </div>
            </div>
            <div class="settings-list" style="margin-top:1.1rem;">
                <div class="settings-row">
                    <div class="settings-row-text">
                        <label for="gvR401PurokSecretary">Use each Purok's assigned Secretary</label>
                        <span>Affects R4-01 documents only. Assign Secretaries on the Purok signatories page.</span>
                    </div>
                    <label class="sa-toggle">
                        <input type="checkbox" id="gvR401PurokSecretary" @checked($globalVariables?->r401_use_purok_secretary)>
                        <span class="sa-toggle-track"></span>
                    </label>
                </div>
            </div>
            <div class="sa-effects" aria-live="polite">
                <div class="sa-effect" data-r401-effect="off"><strong>Off</strong>R4-01 uses the Kalihim ng MS and Kalihim ng UWP names set above.</div>
                <div class="sa-effect" data-r401-effect="on"><strong>On</strong>Each R4-01 page uses the Secretary assigned to that Purok.</div>
            </div>
        </div>

        <p class="sa-inline-error" role="alert" data-error-scope="globals" hidden></p>
        <div class="sa-save-bar">
            <button type="button" class="sa-btn sa-btn-ghost" data-discard-globals>Discard</button>
            <button type="button" class="sa-btn sa-btn-primary" data-save-globals>Save document settings</button>
        </div>
    </div>

    <!-- ============ R2-10 ELIGIBILITY ============ -->
    <div class="sa-tab-panel" id="tab-eligibility">
        <header class="sa-panel-head"><h2>R2-10 eligibility</h2><p>Rules that decide which members qualify for de-listment.</p></header>
        <div class="sa-card sa-list-card">
            <div class="sa-card-head">
                <div>
                    <h2>De-listment requirements</h2>
                    <p>These settings apply to R2-10 eligibility only. They do not affect document signatories.</p>
                </div>
            </div>
            <div class="settings-list">
                <div class="settings-row">
                    <div class="settings-row-text">
                        <label for="delistBaptismCheck">Require a date of baptism</label>
                        <span>When on, members must have a baptism date on or before the cutoff below.</span>
                    </div>
                    <label class="sa-toggle">
                        <input type="checkbox" id="delistBaptismCheck" data-controls="gvBaptismField" @checked($globalVariables?->dateofbaptism_requirement_boolean)>
                        <span class="sa-toggle-track"></span>
                    </label>
                </div>
                <div class="sa-subfield">
                    <div class="gv-field {{ $globalVariables?->dateofbaptism_requirement_boolean ? '' : 'disabled' }}" id="gvBaptismField">
                        <label for="gvBaptismDate">Baptism cutoff date</label>
                        <input type="date" id="gvBaptismDate" value="{{ $globalVariables?->baptism_requirement?->format('Y-m-d') }}" @disabled(!$globalVariables?->dateofbaptism_requirement_boolean)>
                        <div class="gv-hint">Enabled only while the baptism requirement is on.</div>
                    </div>
                </div>
                <div class="settings-row">
                    <div class="settings-row-text">
                        <label for="gvEntryYears">Minimum years since date of entry</label>
                        <span>MS and UWP records must have been in the system for this long. MK is always eligible. This is a separate rule from the baptism requirement.</span>
                    </div>
                    <div class="gv-field" id="gvEntryYearsField" style="margin:0;">
                        <select id="gvEntryYears" required style="min-width:120px;">
                            <option value="1" @selected(($globalVariables?->entry_years_requirement ?? 1) === 1)>1 year</option>
                            <option value="2" @selected($globalVariables?->entry_years_requirement === 2)>2 years</option>
                            <option value="3" @selected($globalVariables?->entry_years_requirement === 3)>3 years</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
        <p class="sa-inline-error" role="alert" data-error-scope="globals" hidden></p>
        <div class="sa-save-bar">
            <button type="button" class="sa-btn sa-btn-ghost" data-discard-globals>Discard</button>
            <button type="button" class="sa-btn sa-btn-primary" data-save-globals>Save eligibility settings</button>
        </div>
    </div>

    <div class="sa-tab-panel" id="tab-r402-hakbangs">
        <header class="sa-panel-head"><h2>R4-02 Hakbang</h2><p>Manage hakbang codes and descriptions separately for MS and UWP.</p></header>
        <div class="sa-card">
            <div class="sa-card-head">
                <div>
                    <h2>Mga R4-02 Hakbang</h2>
                    <p>Manage the code and description entries separately for MS and UWP. Codes are unique within each status.</p>
                </div>
            </div>
            <div class="hakbang-status-tabs" role="tablist" aria-label="R4-02 status">
                <button type="button" class="hakbang-status-btn active" data-hakbang-status="MS" role="tab" aria-selected="true">MS</button>
                <button type="button" class="hakbang-status-btn" data-hakbang-status="UWP" role="tab" aria-selected="false">UWP</button>
            </div>
            <form class="hakbang-add-form" id="addHakbangForm">
                <div class="hakbang-field">
                    <label for="newHakbangCode">Code</label>
                    <input id="newHakbangCode" name="code" type="text" placeholder="e.g. 1-7" pattern="[0-9]+-[0-9]+" maxlength="30" required>
                </div>
                <div class="hakbang-field">
                    <label for="newHakbangDescription">Description</label>
                    <input id="newHakbangDescription" name="description" type="text" maxlength="2000" placeholder="e.g. Dinalaw, Pinayuhan, Pinanalangin" required>
                </div>
                <button type="submit" class="sa-btn sa-btn-primary" id="addHakbangBtn">Add Hakbang</button>
            </form>
            <div class="sa-table-wrap">
                <div class="hakbang-grid" id="hakbangRows">
                        @foreach ($r402Hakbangs as $hakbang)
                            <article class="hakbang-card" data-hakbang-id="{{ $hakbang->id }}" data-status="{{ $hakbang->status }}">
                                <div class="hakbang-card-head">
                                    <span class="hakbang-badge">{{ $hakbang->status }}</span>
                                    <div class="hakbang-actions">
                                        <button type="button" class="sa-btn sa-btn-primary sa-btn-sm hakbang-save-btn" disabled>Save</button>
                                        <button type="button" class="sa-btn sa-btn-danger sa-btn-sm hakbang-delete-btn">Delete</button>
                                    </div>
                                </div>
                                <div>
                                    <span class="hakbang-label">Code</span>
                                    <input class="hakbang-row-input hakbang-code-input" type="text" value="{{ $hakbang->code }}" pattern="[0-9]+-[0-9]+" maxlength="30" aria-label="Hakbang code">
                                </div>
                                <div>
                                    <span class="hakbang-label">Description</span>
                                    <input class="hakbang-row-input hakbang-description-input" type="text" value="{{ $hakbang->description }}" maxlength="2000" aria-label="Hakbang description">
                                </div>
                            </article>
                        @endforeach
                    </div>
                <div class="sa-empty" id="hakbangEmptyState" @if ($r402Hakbangs->isNotEmpty()) style="display:none;" @endif>No hakbang codes configured for this status.</div>
            </div>
        </div>
    </div>

    <div class="sa-tab-panel" id="tab-maintenance">
        <header class="sa-panel-head"><h2>BNH form reset</h2><p>Reset the BNH Form Helper checks with a one-time code.</p></header>
        <div class="sa-card">
            <div class="sa-card-head">
                <div>
                    <h2>BNH FORM RESET</h2>
                </div>
            </div>
            <div class="gv-grid bnh-reset-grid">
                <div class="gv-field bnh-reset-field">
                    <div class="bnh-reset-state" id="bnhResetEmptyState" role="status" hidden>
                        <span class="bnh-reset-state-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 3.5 20 7v5.5c0 4.2-3.1 7.1-8 8.5-4.9-1.4-8-4.3-8-8.5V7l8-3.5Z"/>
                                <path d="M12 8v4.5M12 16h.01"/>
                            </svg>
                        </span>
                        <div class="bnh-reset-state-copy">
                            <h3 class="bnh-reset-state-title">No BNH code has been detected</h3>
                            <p>If a code becomes available, it will appear here instead.</p>
                        </div>
                    </div>
                    <div class="bnh-reset-state" id="bnhResetLoadingState" role="status" aria-live="polite" hidden>
                        <div class="bnh-reset-state-copy"><h3 class="bnh-reset-state-title">Generating code…</h3><p>Requesting a one-time code from the server.</p></div>
                    </div>
                    <div class="bnh-reset-code-panel" id="bnhResetCodePanel" hidden>
                        <h3 class="bnh-reset-code-title">Enter the code below to reset the BNH Form Helper</h3>
                        <div class="bnh-reset-code-content">
                            <div class="bnh-reset-code-display">
                                <span class="bnh-reset-code-label">Reset code</span>
                                <code id="bnhResetGeneratedCode" aria-label="Reset code"></code>
                                <div class="bnh-reset-countdown" role="progressbar" aria-label="Code time remaining" aria-valuemin="0" aria-valuemax="45" aria-valuenow="45">
                                    <div id="bnhResetCountdownBar"></div>
                                </div>
                                <p class="bnh-reset-code-timer" id="bnhResetCountdownText">45 seconds remaining</p>
                            </div>
                            <div class="bnh-reset-confirmation">
                                <label for="bnhResetCodeInput">Enter reset code</label>
                                <input id="bnhResetCodeInput" type="text" autocomplete="off" autocapitalize="off" spellcheck="false" maxlength="12" disabled>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    </div>
    </div>
</div>

<div class="sa-toast-container" id="saToastContainer" aria-live="polite" aria-atomic="false"></div>

<div class="sa-modal-overlay" id="confirmDialog" aria-hidden="true">
    <div class="sa-modal sa-confirm" role="alertdialog" aria-modal="true" aria-labelledby="confirmDialogTitle" aria-describedby="confirmDialogMessage">
        <div class="sa-modal-header">
            <div>
                <h2 id="confirmDialogTitle">Confirm</h2>
                <p id="confirmDialogMessage"></p>
            </div>
        </div>
        <div class="sa-modal-save-bar">
            <button type="button" class="sa-btn sa-btn-ghost" id="confirmDialogCancel">Cancel</button>
            <button type="button" class="sa-btn sa-btn-primary" id="confirmDialogOk">Confirm</button>
        </div>
    </div>
</div>

<!-- ============ SMTP POPUP: connect / code / success ============ -->
<div class="sa-modal-overlay" id="smtpModal" aria-hidden="true">
    <div class="sa-modal smtpx-modal" role="dialog" aria-modal="true" aria-labelledby="smtpModalTitle">

        {{-- Step 1: Gmail + app password --}}
        <form id="smtpSettingsForm" autocomplete="off">
            @csrf
            <div class="sa-modal-header">
                <div class="dim-title">
                    <span class="dim-icon" aria-hidden="true"><svg class="smtpx-modal-logo" viewBox="0 0 48 48" aria-hidden="true"><path fill="#4caf50" d="M45,16.2l-5,2.75l-5,4.75L35,40h7c1.657,0,3-1.343,3-3V16.2z"/><path fill="#1e88e5" d="M3,16.2l3.614,1.71L13,23.7V40H6c-1.657,0-3-1.343-3-3V16.2z"/><polygon fill="#e53935" points="35,11.2 24,19.45 13,11.2 12,17 13,23.7 24,31.95 35,23.7 36,17"/><path fill="#c62828" d="M3,12.298V16.2l10,7.5V11.2L9.876,8.859C9.132,8.301,8.228,8,7.298,8h0C4.924,8,3,9.924,3,12.298z"/><path fill="#fbc02d" d="M45,12.298V16.2l-10,7.5V11.2l3.124-2.341C38.868,8.301,39.772,8,40.702,8h0C43.076,8,45,9.924,45,12.298z"/></svg></span>
                    <div>
                        <h2 id="smtpModalTitle">Connect Gmail</h2>
                        <p id="smtpFormSub">We'll email a 6-digit code to your account to confirm it works.</p>
                    </div>
                </div>
                <button type="button" class="sa-modal-close" data-smtp-close aria-label="Close">&times;</button>
            </div>

            <p class="smtpx-warn" id="smtpReplaceNote" hidden>This replaces the current sender. Emails won't send until the new Gmail is verified.</p>

            <div class="smtp-field">
                <label for="smtpEmail">Gmail address</label>
                <input id="smtpEmail" name="email" type="email" maxlength="255" required autocomplete="email" placeholder="name@gmail.com">
            </div>
            <div class="smtp-field">
                <label for="smtpAppPassword">Google App Password</label>
                <input id="smtpAppPassword" name="app_password" type="password" maxlength="255" required autocomplete="new-password" placeholder="16-character app password">
                <span class="smtp-field-hint">Not your normal password. <a href="https://myaccount.google.com/apppasswords" target="_blank" rel="noopener noreferrer">Create one in Google</a> (needs 2-Step Verification). It's stored encrypted and never shown again.</span>
            </div>
            <div class="sa-modal-save-bar">
                <button type="button" class="sa-btn sa-btn-ghost" data-smtp-close>Cancel</button>
                <button type="submit" class="sa-btn sa-btn-primary" id="saveSmtpSettingsBtn">Send code</button>
            </div>
        </form>

        {{-- Step 2: enter code --}}
        <div id="smtpStepCode" hidden>
            <div class="sa-modal-header">
                <div>
                    <h2>We sent you a code</h2>
                    <p id="smtpCodeText"></p>
                </div>
                <button type="button" class="sa-modal-close" data-smtp-close aria-label="Close">&times;</button>
            </div>
            <div class="smtp-verify-code">
                <span class="smtp-verify-label" id="smtpCodeLabel">6-digit code</span>
                <div class="otp" id="smtpCodeBoxes" role="group" aria-labelledby="smtpCodeLabel">
                    <input class="otp-box" type="text" inputmode="numeric" autocomplete="one-time-code" aria-label="Digit 1"><input class="otp-box" type="text" inputmode="numeric" autocomplete="off" aria-label="Digit 2"><input class="otp-box" type="text" inputmode="numeric" autocomplete="off" aria-label="Digit 3"><input class="otp-box" type="text" inputmode="numeric" autocomplete="off" aria-label="Digit 4"><input class="otp-box" type="text" inputmode="numeric" autocomplete="off" aria-label="Digit 5"><input class="otp-box" type="text" inputmode="numeric" autocomplete="off" aria-label="Digit 6">
                </div>
                <input id="smtpVerifyCode" type="hidden">
            </div>
            <p class="sa-inline-error" id="smtpModalError" role="alert" hidden></p>
            <div class="sa-modal-save-bar">
                <button type="button" class="sa-btn sa-btn-ghost" id="smtpVerifyResendBtn">Resend code</button>
                <button type="button" class="sa-btn sa-btn-primary" id="smtpVerifyConfirmBtn" disabled>Verify</button>
            </div>
        </div>

        {{-- Step 3: success --}}
        <div id="smtpStepDone" hidden>
            <div class="smtp-done">
                <span class="sa-result-check" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg></span>
                <h2>Gmail verified</h2>
                <p id="smtpDoneText"></p>
            </div>
            <div class="sa-modal-save-bar">
                <button type="button" class="sa-btn sa-btn-primary" id="smtpDoneBtn">Done</button>
            </div>
        </div>
    </div>
</div>

<div class="sa-modal-overlay" id="databaseImportModal" aria-hidden="true">
    <div class="sa-modal database-import-modal" role="dialog" aria-modal="true" aria-labelledby="databaseImportModalTitle" aria-describedby="databaseImportWarning">
        <div class="sa-modal-header">
            <div class="dim-title">
                <span class="dim-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3.5 2.5 20h19L12 3.5Z"/><path d="M12 10v4.5M12 17.5h.01"/></svg></span>
                <div>
                    <h2 id="databaseImportModalTitle">Review database import</h2>
                    <p>Check what will change, then confirm it's you.</p>
                </div>
            </div>
            <button type="button" class="sa-modal-close" id="closeDatabaseImportModal" aria-label="Cancel import review">&times;</button>
        </div>

        <div class="dim-notice" id="databaseImportWarning" role="note">
            <strong>Data in every table on the left is replaced.</strong>
            <span>This can't be undone from this screen.</span>
        </div>

        <div class="dim-backup">
            <span class="dim-backup-icon" aria-hidden="true"><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="14" height="4"/><path d="M4 8v8h12V8M8 12h4"/></svg></span>
            <p><strong>A safety backup is created first.</strong> When the import finishes you are taken to <button type="button" class="sa-link-btn" data-open-backups>Database backups</button>, where it is listed as <em>Before import</em>.</p>
        </div>

        <div class="database-import-summary">
            <section aria-labelledby="dimReplacedTitle">
                <h3 id="dimReplacedTitle">Replaced <span class="dim-count" id="databaseImportTableCount">0</span></h3>
                <ul id="databaseImportTables"></ul>
            </section>
            <section aria-labelledby="dimRetainedTitle">
                <h3 id="dimRetainedTitle">Retained <span class="dim-count" id="databaseImportOmittedCount">0</span></h3>
                <ul id="databaseImportOmittedTables"></ul>
            </section>
        </div>

        <div class="dim-confirm">
            <h3>Confirm it's you</h3>
            <div class="database-import-confirm-fields">
                <label for="databaseImportPassword">
                    Head Secretary current password
                    <input id="databaseImportPassword" type="password" autocomplete="current-password">
                </label>
                <label for="databaseImportConfirmationText">
                    <span>Type <code class="dim-phrase">IMPORT DATABASE</code> to confirm</span>
                    <input id="databaseImportConfirmationText" type="text" autocomplete="off" spellcheck="false" placeholder="IMPORT DATABASE">
                    <small class="dim-match" id="databaseImportMatch" aria-live="polite"></small>
                </label>
            </div>
        </div>
        <p class="database-import-error" id="databaseImportConfirmError" role="alert" hidden></p>
        <div class="sa-modal-save-bar">
            <button type="button" class="sa-btn sa-btn-ghost" id="cancelDatabaseImport">Cancel</button>
            <button type="button" class="sa-btn sa-btn-danger" id="confirmDatabaseImport" disabled>Import database</button>
        </div>
    </div>
</div>

<div class="sa-modal-overlay" id="deleteHakbangModal" aria-hidden="true">
    <div class="sa-modal" role="dialog" aria-modal="true" aria-labelledby="deleteHakbangTitle">
        <div class="sa-modal-header">
            <div>
                <h2 id="deleteHakbangTitle">Delete Hakbang?</h2>
                <p id="deleteHakbangMessage">This entry will be permanently removed.</p>
            </div>
        </div>
        <div class="sa-modal-save-bar">
            <button type="button" class="sa-btn sa-btn-ghost" id="cancelDeleteHakbang">Cancel</button>
            <button type="button" class="sa-btn sa-btn-danger" id="confirmDeleteHakbang">Delete Hakbang</button>
        </div>
    </div>
</div>

<!-- ============ EDIT PERMISSIONS OF SECRETARIES (modal, launched from User Management) ============ -->
<div class="sa-modal-overlay" id="permissionsModalOverlay">
    <div class="sa-modal">
        <div class="sa-modal-header">
            <div>
                <h2>Secretary Permissions</h2>
                <p>Only currently active secretaries are listed. Toggle which portals each one can access.</p>
            </div>
            <button type="button" class="sa-modal-close" id="closePermissionsModal" aria-label="Close">&times;</button>
        </div>

        <div class="sa-modal-search-row">
            <input type="text" class="sa-search" id="permSearch" placeholder="Search by name or email..." style="max-width:100%;">
        </div>

        <div class="sa-table-wrap">
            <table class="sa-table" id="permissionsTable">
                <thead>
                    <tr>
                        <th>Secretary</th>
                        <th class="center">Allow Dashboard<br>Monitoring</th>
                        <th class="center">Allow Form<br>Generator Portal</th>
                        <th class="center">Allow<br>De-Listment</th>
                        <th class="center">Allow Inquire<br>/ Modify</th>
                        <th class="center">Allow Tarheta<br>Status Checker</th>
                        <th class="center">Allow Filter &amp;<br>BNH Helper</th>
                        <th class="center">Allow chatbot<br>record lookups</th>
                    </tr>
                </thead>
                <tbody id="permissionsTableBody">
                    @forelse ($users->where('is_verified', true) as $user)
                        @php $userName = trim(($user->first_name ?? '').' '.($user->last_name ?? '')) ?: $user->gmail; @endphp
                        <tr data-user-id="{{ $user->id }}" data-name="{{ strtolower($userName) }}" data-email="{{ strtolower($user->gmail) }}">
                            <td>
                                <div class="sa-user-cell">
                                    <span class="sa-user-avatar" aria-hidden="true">{{ strtoupper(substr($userName, 0, 1)) }}</span>
                                    <div>
                                        <div class="sa-user-name">{{ $userName }}</div>
                                        <div class="sa-user-email">{{ $user->gmail }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="center"><input type="checkbox" class="sa-checkbox" data-permission="dashboard_allow" @checked($user->dashboard_allow)></td>
                            <td class="center"><input type="checkbox" class="sa-checkbox" data-permission="form_generator_allow" @checked($user->form_generator_allow)></td>
                            <td class="center"><input type="checkbox" class="sa-checkbox" data-permission="Delistment_allow" @checked($user->Delistment_allow)></td>
                            <td class="center"><input type="checkbox" class="sa-checkbox" data-permission="Inquire_Allow" @checked($user->Inquire_Allow)></td>
                            <td class="center"><input type="checkbox" class="sa-checkbox" data-permission="tarheta_status_allow" @checked($user->tarheta_status_allow)></td>
                            <td class="center"><input type="checkbox" class="sa-checkbox" data-permission="filter_bnh_helper_allow" @checked($user->filter_bnh_helper_allow)></td>
                            <td class="center"><input type="checkbox" class="sa-checkbox" data-permission="chatbot_records_allow" @checked($user->chatbot_records_allow)></td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="sa-empty">No verified users found.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div class="sa-empty" id="permEmptyState" style="display:none;">No secretaries match your search.</div>
        </div>

        <div class="sa-modal-save-bar">
            <button type="button" class="sa-btn sa-btn-ghost" id="cancelPermissionsBtn">Cancel</button>
            <button type="button" class="sa-btn sa-btn-primary" id="savePermissionsBtn">Save Permissions</button>
        </div>
    </div>
</div>

<script>
    (function () {
        // ===== Tabs =====
        const tabBtns = document.querySelectorAll('.sa-tab-btn');
        const tabPanels = document.querySelectorAll('.sa-tab-panel');
        let ensureBnhResetCode = null;

        function activateTab(tabName) {
            const button = Array.from(tabBtns).find(tab => tab.dataset.tab === tabName);
            const panel = document.getElementById(`tab-${tabName}`);
            if (!button || !panel) return;
            tabBtns.forEach(tab => {
                tab.classList.toggle('active', tab === button);
                if (tab === button) tab.setAttribute('aria-current', 'page'); else tab.removeAttribute('aria-current');
            });
            tabPanels.forEach(tabPanel => tabPanel.classList.toggle('active', tabPanel === panel));
        }

        const requestedTab = window.location.hash.replace(/^#/, '')
            || new URLSearchParams(window.location.search).get('tab');
        if (requestedTab) activateTab(requestedTab);

        tabBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                activateTab(btn.dataset.tab);
                window.history.replaceState(null, '', `#${btn.dataset.tab}`);
                if (btn.dataset.tab === 'maintenance') ensureBnhResetCode?.();
                if (btn.dataset.tab === 'backups') window.saLoadBackups?.();
            });
        });
        document.querySelectorAll('[data-open-smtp]').forEach(button => {
            button.addEventListener('click', () => {
                activateTab('smtp-settings');
                window.history.replaceState(null, '', '#smtp-settings');
            });
        });
        const smtpSetupAlert = document.getElementById('smtpSetupAlert');
        const smtpSetupAlertText = document.getElementById('smtpSetupAlertText');
        const smtpSecurityAlert = document.getElementById('smtpSecurityAlert');
        const requireOtpSetting = document.getElementById('requireOtpSetting');
        const allowForgotPasswordSetting = document.getElementById('allowForgotPasswordSetting');
        const smtpNeedsVerificationAtLoad = @json($smtpNeedsVerification);
        const refreshSmtpPrompt = () => {
            if (!smtpSetupAlert) return;
            const otpEnabled = Boolean(requireOtpSetting?.checked);
            const forgotPasswordEnabled = Boolean(allowForgotPasswordSetting?.checked);
            if (smtpSetupAlertText && !smtpNeedsVerificationAtLoad) {
                smtpSetupAlertText.textContent = otpEnabled && forgotPasswordEnabled
                    ? 'Email verification and password recovery are enabled, but these emails cannot be delivered until SMTP is set up.'
                    : otpEnabled
                        ? 'Email verification is enabled, but one-time codes cannot be delivered until SMTP is set up.'
                        : forgotPasswordEnabled
                            ? 'Password recovery is enabled, but reset links cannot be delivered until SMTP is set up.'
                            : 'Configure SMTP to enable system email delivery, including account verification and password recovery.';
            }
            if (smtpSecurityAlert) smtpSecurityAlert.hidden = !otpEnabled && !forgotPasswordEnabled;
        };
        requireOtpSetting?.addEventListener('change', refreshSmtpPrompt);
        allowForgotPasswordSetting?.addEventListener('change', refreshSmtpPrompt);
        refreshSmtpPrompt();

        // ===== Generic search filter (used by Users table + Permissions modal table) =====
        function wireSearch(inputId, bodyId, emptyStateId) {
            const input = document.getElementById(inputId);
            const body = document.getElementById(bodyId);
            const emptyState = document.getElementById(emptyStateId);
            if (!input || !body) return;

            input.addEventListener('input', () => {
                const term = input.value.trim().toLowerCase();
                let visibleCount = 0;

                body.querySelectorAll('tr').forEach(row => {
                    const matches = (row.dataset.name || '').includes(term) || (row.dataset.email || '').includes(term);
                    row.style.display = matches ? '' : 'none';
                    if (matches) visibleCount++;
                });

                if (emptyState) emptyState.style.display = visibleCount === 0 ? 'block' : 'none';
            });
        }

        const userSearchInput = document.getElementById('userSearch');
        const userStatusFilter = document.getElementById('userStatusFilter');
        const usersCount = document.getElementById('usersCount');
        function applyUserFilters() {
            const term = (userSearchInput?.value || '').trim().toLowerCase();
            const status = userStatusFilter?.value ?? '';
            const rows = document.querySelectorAll('#usersTableBody tr[data-user-id]');
            let visible = 0;
            rows.forEach(row => {
                const matches = ((row.dataset.name || '').includes(term) || (row.dataset.email || '').includes(term))
                    && (status === '' || row.dataset.verified === status);
                row.style.display = matches ? '' : 'none';
                if (matches) visible++;
            });
            const empty = document.getElementById('usersEmptyState');
            if (empty) empty.style.display = rows.length && visible === 0 ? 'block' : 'none';
            if (usersCount) usersCount.textContent = `${visible} of ${rows.length} secretar${rows.length === 1 ? 'y' : 'ies'}`;
        }
        userSearchInput?.addEventListener('input', applyUserFilters);
        userStatusFilter?.addEventListener('change', applyUserFilters);
        applyUserFilters();
        wireSearch('permSearch', 'permissionsTableBody', 'permEmptyState');

        const csrfToken = @json(csrf_token());
        const superadminUrls = {
            users: @json(url('/superadmin/users')),
            permissions: @json(route('superadmin.permissions.update')),
            signatories: @json(route('superadmin.signatories.update')),
            globalVariables: @json(route('superadmin.global-variables.update')),
            securitySettings: @json(route('superadmin.security-settings.update')),
            smtpSettings: @json(route('superadmin.smtp-settings.update')),
            transferHeadSecretary: @json(route('superadmin.users.transfer-head-secretary')),
            databaseImportPreview: @json(route('superadmin.database.import-preview')),
            databaseImport: @json(route('superadmin.database.import')),
            generateBnhResetCode: @json(route('superadmin.reset-bnh-helper-checks.code')),
            resetBnhChecks: @json(route('superadmin.reset-bnh-helper-checks')),
            r402Hakbangs: @json(url('/superadmin/r4-02/hakbangs')),
            databaseBackups: @json(\Illuminate\Support\Facades\Route::has('superadmin.database.backups') ? route('superadmin.database.backups') : null),
            databaseBackupDownload: @json(\Illuminate\Support\Facades\Route::has('superadmin.database.backups.download') ? route('superadmin.database.backups.download', ['file' => '__FILE__']) : null),
            databaseBackupStore: @json(\Illuminate\Support\Facades\Route::has('superadmin.database.backups.store') ? route('superadmin.database.backups.store') : null),
            smtpVerificationStatus: @json(\Illuminate\Support\Facades\Route::has('superadmin.smtp-settings.verification.status') ? route('superadmin.smtp-settings.verification.status') : null),
            smtpVerificationSend: @json(\Illuminate\Support\Facades\Route::has('superadmin.smtp-settings.verification.send') ? route('superadmin.smtp-settings.verification.send') : null),
            smtpVerificationVerify: @json(\Illuminate\Support\Facades\Route::has('superadmin.smtp-settings.verification.verify') ? route('superadmin.smtp-settings.verification.verify') : null),
            databaseBackupRename: @json(\Illuminate\Support\Facades\Route::has('superadmin.database.backups.rename') ? route('superadmin.database.backups.rename', ['file' => '__FILE__']) : null),
        };
        const toastContainer = document.getElementById('saToastContainer');
        if (toastContainer && toastContainer.parentElement !== document.body) {
            document.body.appendChild(toastContainer);
        }

        function showToast(message, type = 'success') {
            if (!toastContainer || !message) return;

            const toast = document.createElement('div');
            toast.className = 'sa-toast';
            toast.dataset.type = type;
            toast.setAttribute('role', type === 'error' ? 'alert' : 'status');

            const icon = document.createElement('span');
            icon.className = 'sa-toast-icon';
            icon.setAttribute('aria-hidden', 'true');
            icon.textContent = type === 'success' ? '✓' : (type === 'error' ? '!' : 'i');

            const text = document.createElement('span');
            text.className = 'sa-toast-message';
            text.textContent = message;

            const close = document.createElement('button');
            close.className = 'sa-toast-close';
            close.type = 'button';
            close.setAttribute('aria-label', 'Dismiss notification');
            close.textContent = '×';

            let removed = false;
            const dismiss = () => {
                if (removed) return;
                removed = true;
                window.clearTimeout(timer);
                toast.classList.add('sa-toast-leaving');
                window.setTimeout(() => toast.remove(), 180);
            };
            close.addEventListener('click', dismiss);
            toast.append(icon, text, close);
            toastContainer.appendChild(toast);
            const timer = window.setTimeout(dismiss, type === 'error' ? 7000 : 4500);
        }

        function setBusy(button, busy) {
            if (!button) return;
            button.classList.toggle('is-loading', busy);
            button.setAttribute('aria-busy', String(busy));
            button.disabled = busy;
        }

        function setInlineError(scope, message) {
            document.querySelectorAll(`[data-error-scope="${scope}"]`).forEach(element => {
                element.textContent = message || '';
                element.hidden = !message;
            });
        }

        const confirmDialog = document.getElementById('confirmDialog');
        if (confirmDialog && confirmDialog.parentElement !== document.body) {
            document.body.appendChild(confirmDialog);
        }
        function confirmAction({ title, message, confirmLabel = 'Confirm', danger = false }) {
            return new Promise(resolve => {
                const ok = document.getElementById('confirmDialogOk');
                const cancel = document.getElementById('confirmDialogCancel');
                const previous = document.activeElement;
                document.getElementById('confirmDialogTitle').textContent = title;
                document.getElementById('confirmDialogMessage').textContent = message;
                ok.textContent = confirmLabel;
                ok.className = `sa-btn ${danger ? 'sa-btn-danger' : 'sa-btn-primary'}`;

                const close = result => {
                    confirmDialog.classList.remove('active');
                    confirmDialog.setAttribute('aria-hidden', 'true');
                    ok.removeEventListener('click', onOk);
                    cancel.removeEventListener('click', onCancel);
                    confirmDialog.removeEventListener('click', onBackdrop);
                    document.removeEventListener('keydown', onKey, true);
                    previous?.focus?.();
                    resolve(result);
                };
                const onOk = () => close(true);
                const onCancel = () => close(false);
                const onBackdrop = event => { if (event.target === confirmDialog) close(false); };
                const onKey = event => {
                    if (event.key === 'Escape') { event.stopPropagation(); close(false); }
                    if (event.key === 'Tab') {
                        const focusable = [cancel, ok];
                        const index = focusable.indexOf(document.activeElement);
                        event.preventDefault();
                        focusable[(index + (event.shiftKey ? -1 : 1) + focusable.length) % focusable.length].focus();
                    }
                };
                ok.addEventListener('click', onOk);
                cancel.addEventListener('click', onCancel);
                confirmDialog.addEventListener('click', onBackdrop);
                document.addEventListener('keydown', onKey, true);
                confirmDialog.classList.add('active');
                confirmDialog.setAttribute('aria-hidden', 'false');
                cancel.focus();
            });
        }

        async function sendFormData(url, formData, method = 'POST') {
            const response = await fetch(url, {
                method,
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: formData,
            });
            const body = await response.json();
            if (!response.ok) {
                const message = body.errors ? Object.values(body.errors).flat()[0] : body.message;
                throw new Error(message || 'The request could not be completed.');
            }
            return body;
        }

        document.getElementById('transferHeadSecretaryBtn')?.addEventListener('click', async event => {
            const button = event.currentTarget;
            const targetId = document.getElementById('headSecretaryTransferTarget').value;
            const passwordInput = document.getElementById('headSecretaryTransferPassword');
            const currentPassword = passwordInput.value;
            if (!targetId) {
                showToast('Choose a verified Secretary account first.', 'error');
                return;
            }
            if (!currentPassword) {
                passwordInput.focus();
                showToast('Enter your current password to confirm the transfer.', 'error');
                return;
            }
            const confirmed = await confirmAction({
                title: 'Transfer Head Secretary role?',
                message: 'Your account will become a Secretary and lose Head Secretary access. The selected account becomes Head Secretary.',
                confirmLabel: 'Transfer role',
                danger: true,
            });
            if (!confirmed) return;
            setBusy(button, true);
            try {
                const result = await sendUpdate(superadminUrls.transferHeadSecretary, {
                    target_user_id: Number(targetId),
                    current_password: currentPassword,
                    confirmation: true,
                }, 'POST');
                passwordInput.value = '';
                showToast(result.message);
                window.setTimeout(() => { window.location.href = result.redirect_url; }, 1200);
            } catch (error) {
                passwordInput.value = '';
                showToast(error.message, 'error');
                setBusy(button, false);
            }
        });

        const databaseImportForm = document.getElementById('databaseImportForm');
        const databaseSqlInput = document.getElementById('databaseSqlFile');
        const databaseSqlDropzone = document.getElementById('databaseSqlDropzone');
        const databaseSqlStatus = document.getElementById('databaseSqlFileStatus');
        const databaseSqlError = document.getElementById('databaseSqlFileError');
        const databaseImportButton = document.getElementById('importDatabaseBtn');
        const databaseImportModal = document.getElementById('databaseImportModal');
        const databaseImportPassword = document.getElementById('databaseImportPassword');
        const databaseImportConfirmationText = document.getElementById('databaseImportConfirmationText');
        const databaseImportConfirmError = document.getElementById('databaseImportConfirmError');
        const confirmDatabaseImportButton = document.getElementById('confirmDatabaseImport');
        let pendingDatabaseImport = null;
        const updateImportConfirmState = () => {
            const matches = databaseImportConfirmationText.value.trim() === 'IMPORT DATABASE';
            document.getElementById('databaseImportMatch').textContent = matches ? 'Matches' : '';
            confirmDatabaseImportButton.disabled = !(databaseImportPassword.value && matches);
        };
        let validSqlFileSelected = false;

        if (databaseImportModal && databaseImportModal.parentElement !== document.body) {
            document.body.appendChild(databaseImportModal);
        }

        function closeDatabaseImportReview() {
            databaseImportModal.classList.remove('active');
            databaseImportModal.setAttribute('aria-hidden', 'true');
            databaseImportPassword.value = '';
            databaseImportConfirmationText.value = '';
            databaseImportConfirmError.hidden = true;
            updateImportConfirmState();
            pendingDatabaseImport = null;
            databaseImportButton.disabled = !validSqlFileSelected;
        }

        function populateTableList(list, tables) {
            list.replaceChildren();
            if (tables.length === 0) {
                const item = document.createElement('li');
                item.textContent = 'None';
                list.appendChild(item);
                return;
            }
            tables.forEach(table => {
                const item = document.createElement('li');
                item.textContent = table;
                list.appendChild(item);
            });
        }

        function renderSqlSelection(file) {
            const box = document.getElementById('databaseSqlSelected');
            databaseSqlDropzone.classList.toggle('has-file', Boolean(file));
            box.hidden = !file;
            document.querySelector('.sql-dropzone-title').textContent = file ? 'File ready — drop another to replace it' : 'Drop your SQL backup here';
            if (file) {
                document.getElementById('databaseSqlSelectedName').textContent = file.name;
                document.getElementById('databaseSqlSelectedSize').textContent = `${(file.size / 1024 / 1024).toFixed(1)} MB · ready for review`;
            }
        }
        document.getElementById('databaseSqlClear')?.addEventListener('click', () => {
            databaseSqlInput.value = '';
            validateSqlFile(null);
        });

        function validateSqlFile(file) {
            renderSqlSelection(null);
            databaseSqlError.hidden = true;
            databaseSqlError.textContent = '';
            if (!file) {
                validSqlFileSelected = false;
                databaseSqlStatus.textContent = 'No file selected.';
                databaseImportButton.disabled = true;
                return false;
            }

            if (!/\.sql$/i.test(file.name)) {
                databaseSqlInput.value = '';
                databaseSqlError.textContent = 'Choose an .sql backup file.';
            } else if (file.size > 100 * 1024 * 1024) {
                databaseSqlInput.value = '';
                databaseSqlError.textContent = 'The SQL backup must be 100 MB or smaller.';
            } else {
                validSqlFileSelected = true;
                renderSqlSelection(file);
                databaseSqlStatus.textContent = `${file.name} · ${(file.size / 1024 / 1024).toFixed(1)} MB`;
                databaseImportButton.disabled = false;
                return true;
            }

            validSqlFileSelected = false;
            databaseSqlStatus.textContent = 'No file selected.';
            databaseSqlError.hidden = false;
            databaseImportButton.disabled = true;
            return false;
        }

        databaseSqlInput?.addEventListener('change', () => validateSqlFile(databaseSqlInput.files?.[0]));
        ['dragenter', 'dragover'].forEach(eventName => databaseSqlDropzone?.addEventListener(eventName, event => {
            event.preventDefault();
            databaseSqlDropzone.classList.add('is-dragging');
        }));
        ['dragleave', 'dragend'].forEach(eventName => databaseSqlDropzone?.addEventListener(eventName, event => {
            if (eventName === 'dragleave' && databaseSqlDropzone.contains(event.relatedTarget)) return;
            databaseSqlDropzone.classList.remove('is-dragging');
        }));
        databaseSqlDropzone?.addEventListener('drop', event => {
            event.preventDefault();
            databaseSqlDropzone.classList.remove('is-dragging');
            const file = event.dataTransfer?.files?.[0];
            if (!file) return;

            const transfer = new DataTransfer();
            transfer.items.add(file);
            databaseSqlInput.files = transfer.files;
            databaseSqlInput.dispatchEvent(new Event('change', { bubbles: true }));
        });

        document.getElementById('cancelDatabaseImport')?.addEventListener('click', closeDatabaseImportReview);
        document.getElementById('closeDatabaseImportModal')?.addEventListener('click', closeDatabaseImportReview);
        databaseImportModal?.addEventListener('click', event => {
            if (event.target === databaseImportModal) closeDatabaseImportReview();
        });
        document.addEventListener('keydown', event => {
            if (event.key === 'Escape' && databaseImportModal?.classList.contains('active')) {
                closeDatabaseImportReview();
            }
        });

        databaseImportForm?.addEventListener('submit', async event => {
            event.preventDefault();
            if (!validSqlFileSelected || pendingDatabaseImport) return;
            databaseImportButton.disabled = true;
            try {
                const formData = new FormData(databaseImportForm);
                const preview = await sendFormData(superadminUrls.databaseImportPreview, formData);
                document.getElementById('databaseImportTableCount').textContent = preview.tables.length;
                document.getElementById('databaseImportOmittedCount').textContent = preview.omitted_tables.length;
                populateTableList(document.getElementById('databaseImportTables'), preview.tables);
                populateTableList(document.getElementById('databaseImportOmittedTables'), preview.omitted_tables);
                pendingDatabaseImport = formData;
                databaseImportConfirmError.hidden = true;
                updateImportConfirmState();
                databaseImportModal.classList.add('active');
                databaseImportModal.setAttribute('aria-hidden', 'false');
                databaseImportPassword.focus();
            } catch (error) {
                showToast(error.message, 'error');
            } finally {
                databaseImportButton.disabled = !validSqlFileSelected;
            }
        });

        databaseImportPassword.addEventListener('input', updateImportConfirmState);
        databaseImportConfirmationText.addEventListener('input', updateImportConfirmState);
        confirmDatabaseImportButton?.addEventListener('click', async () => {
            if (!pendingDatabaseImport) return;
            const password = databaseImportPassword.value;
            const confirmationText = databaseImportConfirmationText.value.trim();
            if (!password || confirmationText !== 'IMPORT DATABASE') {
                databaseImportConfirmError.textContent = !password
                    ? 'Enter your current password to continue.'
                    : 'Type IMPORT DATABASE exactly as shown to continue.';
                databaseImportConfirmError.hidden = false;
                (!password ? databaseImportPassword : databaseImportConfirmationText).focus();
                return;
            }

            setBusy(confirmDatabaseImportButton, true);
            document.getElementById('cancelDatabaseImport').disabled = true;
            document.getElementById('closeDatabaseImportModal').disabled = true;
            databaseImportConfirmError.hidden = true;
            const formData = pendingDatabaseImport;
            formData.set('confirmation_text', confirmationText);
            formData.set('current_password', password);
            try {
                const result = await sendFormData(superadminUrls.databaseImport, formData);
                closeDatabaseImportReview();
                try { window.sessionStorage.setItem('saImportResult', JSON.stringify({ message: result.message, backup: result.backup_file })); } catch (error) { /* ignore */ }
                showToast(`${result.message} Backup: ${result.backup_file}`);
                databaseImportForm.reset();
                validSqlFileSelected = false;
                databaseSqlStatus.textContent = 'No file selected.';
                databaseSqlError.hidden = true;
                databaseImportButton.disabled = true;
                window.setTimeout(() => window.location.reload(), 1800);
            } catch (error) {
                databaseImportConfirmError.textContent = error.message;
                databaseImportConfirmError.hidden = false;
                databaseImportPassword.value = '';
                databaseImportConfirmationText.value = '';
                databaseImportPassword.focus();
            } finally {
                setBusy(confirmDatabaseImportButton, false);
                updateImportConfirmState();
                document.getElementById('cancelDatabaseImport').disabled = false;
                document.getElementById('closeDatabaseImportModal').disabled = false;
            }
        });

        async function sendDestructiveReset(url, confirmation) {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ confirmation }),
            });
            const body = await response.json();
            if (!response.ok) {
                const message = body.errors ? Object.values(body.errors).flat()[0] : body.message;
                throw new Error(message || 'The reset could not be completed.');
            }
            return body;
        }

        const bnhCodePanel = document.getElementById('bnhResetCodePanel');
        const bnhEmptyState = document.getElementById('bnhResetEmptyState');
        const bnhLoadingState = document.getElementById('bnhResetLoadingState');
        const handleBnhError = error => { bnhLoadingState.hidden = true; showToast(error.message, 'error'); };
        const bnhCodeDisplay = document.getElementById('bnhResetGeneratedCode');
        const bnhCodeInput = document.getElementById('bnhResetCodeInput');
        const bnhCountdownBar = document.getElementById('bnhResetCountdownBar');
        const bnhCountdownTrack = bnhCountdownBar?.parentElement;
        const bnhCountdownText = document.getElementById('bnhResetCountdownText');
        const bnhCodeLifetime = 45;
        let expectedBnhCode = '';
        let submittingBnhCode = false;
        let bnhCodeCycleActive = false;
        let bnhCodeExpiresAt = 0;
        let bnhCodeTimer = null;

        async function generateBnhResetCode() {
            window.clearInterval(bnhCodeTimer);
            bnhCodeCycleActive = false;
            expectedBnhCode = '';
            bnhCodePanel.hidden = true;
            bnhEmptyState.hidden = true;
            bnhLoadingState.hidden = false;
            bnhCodeInput.disabled = true;
            bnhCodeInput.value = '';
            const requestStartedAt = Date.now();
            const response = await fetch(superadminUrls.generateBnhResetCode, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
            });
            const body = await response.json();
            bnhLoadingState.hidden = true;
            if (!response.ok) {
                bnhCodePanel.hidden = true;
                if (body.empty) {
                    bnhEmptyState.hidden = false;
                    return;
                }
                throw new Error(body.message || Object.values(body.errors || {}).flat()[0] || 'Could not generate the BNH reset code.');
            }

            expectedBnhCode = body.code;
            bnhCodeDisplay.textContent = expectedBnhCode;
            bnhCodePanel.hidden = false;
            bnhCodeInput.disabled = false;
            bnhCodeCycleActive = true;
            bnhCodeExpiresAt = requestStartedAt + bnhCodeLifetime * 1000;

            const updateCountdown = () => {
                const remaining = Math.max(0, bnhCodeExpiresAt - Date.now());
                const seconds = Math.ceil(remaining / 1000);
                bnhCountdownBar.style.width = `${(remaining / (bnhCodeLifetime * 1000)) * 100}%`;
                bnhCountdownTrack.setAttribute('aria-valuenow', String(seconds));
                bnhCountdownText.textContent = `${seconds} second${seconds === 1 ? '' : 's'} remaining`;
                if (remaining === 0) {
                    window.clearInterval(bnhCodeTimer);
                    generateBnhResetCode().catch(handleBnhError);
                }
            };

            updateCountdown();
            bnhCodeTimer = window.setInterval(updateCountdown, 100);
        }

        ensureBnhResetCode = () => {
            if (bnhCodeCycleActive || submittingBnhCode) return;
            generateBnhResetCode().catch(handleBnhError);
        };

        bnhCodeDisplay.addEventListener('copy', event => event.preventDefault());
        bnhCodeDisplay.addEventListener('cut', event => event.preventDefault());
        bnhCodeDisplay.addEventListener('contextmenu', event => event.preventDefault());
        bnhCodeInput.addEventListener('paste', event => {
            event.preventDefault();
            showToast('Please type the code manually; pasting is disabled.', 'error');
        });
        bnhCodeInput.addEventListener('copy', event => event.preventDefault());
        bnhCodeInput.addEventListener('cut', event => event.preventDefault());
        bnhCodeInput.addEventListener('input', async () => {
            if (!expectedBnhCode || submittingBnhCode || bnhCodeInput.value !== expectedBnhCode) return;
            submittingBnhCode = true;
            bnhCodeCycleActive = false;
            window.clearInterval(bnhCodeTimer);
            bnhCodeInput.disabled = true;
            try {
                const result = await sendDestructiveReset(superadminUrls.resetBnhChecks, bnhCodeInput.value);
                showToast(result.message);
                expectedBnhCode = '';
                bnhCodePanel.hidden = true;
                bnhEmptyState.hidden = false;
                bnhCodeInput.value = '';
            } catch (error) {
                if (error.message === 'BNH CHECKS is currently Empty.') bnhEmptyState.hidden = false;
                else showToast(error.message, 'error');
                expectedBnhCode = '';
                bnhCodePanel.hidden = true;
                bnhCodeInput.value = '';
            } finally {
                submittingBnhCode = false;
            }
        });

        if (document.getElementById('tab-maintenance')?.classList.contains('active')) {
            ensureBnhResetCode();
        }

        async function sendUpdate(url, payload, method = 'PUT') {
            const response = await fetch(url, {
                method,
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(payload),
            });
            const body = await response.json();
            if (!response.ok) {
                const message = body.errors ? Object.values(body.errors).flat()[0] : body.message;
                throw new Error(message || 'The update could not be saved.');
            }
            return body;
        }

        // ===== R4-02 Hakbang management (card layout) =====
        const hakbangRows = document.getElementById('hakbangRows');
        const hakbangEmptyState = document.getElementById('hakbangEmptyState');
        const hakbangStatusButtons = document.querySelectorAll('[data-hakbang-status]');
        const hakbangAddForm = document.getElementById('addHakbangForm');
        const deleteHakbangModal = document.getElementById('deleteHakbangModal');
        const deleteHakbangMessage = document.getElementById('deleteHakbangMessage');
        let selectedHakbangStatus = 'MS';
        let pendingHakbangDelete = null;

        if (deleteHakbangModal && deleteHakbangModal.parentElement !== document.body) {
            document.body.appendChild(deleteHakbangModal);
        }

        function refreshHakbangRows() {
            if (!hakbangRows) return;
            const cards = [...hakbangRows.querySelectorAll('.hakbang-card')];
            cards.forEach(card => { card.hidden = card.dataset.status !== selectedHakbangStatus; });
            const visible = cards.filter(card => !card.hidden).length;
            if (hakbangEmptyState) hakbangEmptyState.style.display = visible ? 'none' : 'block';
        }

        function setHakbangRowBaseline(card) {
            card.querySelector('.hakbang-code-input').dataset.originalValue = card.querySelector('.hakbang-code-input').value;
            card.querySelector('.hakbang-description-input').dataset.originalValue = card.querySelector('.hakbang-description-input').value;
            const save = card.querySelector('.hakbang-save-btn');
            setBusy(save, false);
            save.disabled = true;
        }

        function createHakbangRow(item) {
            const card = document.createElement('article');
            card.className = 'hakbang-card';
            card.dataset.hakbangId = item.id;
            card.dataset.status = item.status;

            const head = document.createElement('div');
            head.className = 'hakbang-card-head';
            const badge = document.createElement('span');
            badge.className = 'hakbang-badge';
            badge.textContent = item.status;
            const actions = document.createElement('div');
            actions.className = 'hakbang-actions';
            const saveButton = document.createElement('button');
            saveButton.type = 'button';
            saveButton.className = 'sa-btn sa-btn-primary sa-btn-sm hakbang-save-btn';
            saveButton.textContent = 'Save';
            saveButton.disabled = true;
            const deleteButton = document.createElement('button');
            deleteButton.type = 'button';
            deleteButton.className = 'sa-btn sa-btn-danger sa-btn-sm hakbang-delete-btn';
            deleteButton.textContent = 'Delete';
            actions.append(saveButton, deleteButton);
            head.append(badge, actions);

            const makeField = (labelText, input) => {
                const wrap = document.createElement('div');
                const label = document.createElement('span');
                label.className = 'hakbang-label';
                label.textContent = labelText;
                wrap.append(label, input);
                return wrap;
            };
            const codeInput = document.createElement('input');
            codeInput.className = 'hakbang-row-input hakbang-code-input';
            codeInput.type = 'text';
            codeInput.maxLength = 30;
            codeInput.pattern = '[0-9]+-[0-9]+';
            codeInput.setAttribute('aria-label', 'Hakbang code');
            codeInput.value = item.code;
            const descriptionInput = document.createElement('input');
            descriptionInput.className = 'hakbang-row-input hakbang-description-input';
            descriptionInput.type = 'text';
            descriptionInput.maxLength = 2000;
            descriptionInput.setAttribute('aria-label', 'Hakbang description');
            descriptionInput.value = item.description;

            card.append(head, makeField('Code', codeInput), makeField('Description', descriptionInput));
            setHakbangRowBaseline(card);
            return card;
        }

        async function sendHakbangRequest(method, url, payload) {
            const response = await fetch(url, {
                method,
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                ...(payload ? { body: JSON.stringify(payload) } : {}),
            });
            const body = await response.json();
            if (!response.ok) {
                const message = body.errors ? Object.values(body.errors).flat()[0] : body.message;
                throw new Error(message || 'The R4-02 hakbang could not be saved.');
            }
            return body;
        }

        hakbangRows?.querySelectorAll('.hakbang-card').forEach(setHakbangRowBaseline);
        hakbangStatusButtons.forEach(button => {
            button.addEventListener('click', () => {
                selectedHakbangStatus = button.dataset.hakbangStatus;
                hakbangStatusButtons.forEach(item => {
                    const active = item === button;
                    item.classList.toggle('active', active);
                    item.setAttribute('aria-selected', String(active));
                });
                refreshHakbangRows();
            });
        });
        refreshHakbangRows();

        hakbangRows?.addEventListener('input', event => {
            const card = event.target.closest('.hakbang-card');
            if (!card) return;
            const code = card.querySelector('.hakbang-code-input');
            const description = card.querySelector('.hakbang-description-input');
            card.querySelector('.hakbang-save-btn').disabled =
                code.value === code.dataset.originalValue &&
                description.value === description.dataset.originalValue;
        });

        hakbangAddForm?.addEventListener('submit', async event => {
            event.preventDefault();
            const submitButton = document.getElementById('addHakbangBtn');
            const code = document.getElementById('newHakbangCode');
            const description = document.getElementById('newHakbangDescription');
            setBusy(submitButton, true);
            try {
                const { data, message } = await sendHakbangRequest('POST', superadminUrls.r402Hakbangs, {
                    status: selectedHakbangStatus,
                    code: code.value.trim(),
                    description: description.value.trim(),
                });
                hakbangRows.appendChild(createHakbangRow(data));
                hakbangAddForm.reset();
                refreshHakbangRows();
                showToast(message || 'Hakbang added.');
            } catch (error) {
                showToast(error.message, 'error');
            } finally {
                setBusy(submitButton, false);
            }
        });

        hakbangRows?.addEventListener('click', async event => {
            const card = event.target.closest('.hakbang-card');
            if (!card) return;

            if (event.target.closest('.hakbang-save-btn')) {
                const button = event.target.closest('.hakbang-save-btn');
                const code = card.querySelector('.hakbang-code-input').value.trim();
                const description = card.querySelector('.hakbang-description-input').value.trim();
                setBusy(button, true);
                try {
                    const { data, message } = await sendHakbangRequest(
                        'PUT',
                        `${superadminUrls.r402Hakbangs}/${card.dataset.hakbangId}`,
                        { status: card.dataset.status, code, description }
                    );
                    card.querySelector('.hakbang-code-input').value = data.code;
                    card.querySelector('.hakbang-description-input').value = data.description;
                    setHakbangRowBaseline(card);
                    showToast(message || 'Hakbang updated.');
                } catch (error) {
                    setBusy(button, false);
                    showToast(error.message, 'error');
                }
                return;
            }

            if (event.target.closest('.hakbang-delete-btn')) {
                const code = card.querySelector('.hakbang-code-input').value;
                deleteHakbangMessage.textContent = `Delete ${card.dataset.status} hakbang ${code}? This cannot be undone.`;
                deleteHakbangModal.classList.add('active');
                deleteHakbangModal.setAttribute('aria-hidden', 'false');
                pendingHakbangDelete = card;
            }
        });

        function closeHakbangDeleteModal() {
            deleteHakbangModal.classList.remove('active');
            deleteHakbangModal.setAttribute('aria-hidden', 'true');
            pendingHakbangDelete = null;
        }

        document.getElementById('cancelDeleteHakbang')?.addEventListener('click', closeHakbangDeleteModal);
        deleteHakbangModal?.addEventListener('click', event => {
            if (event.target === deleteHakbangModal) closeHakbangDeleteModal();
        });
        document.addEventListener('keydown', event => {
            if (event.key === 'Escape' && deleteHakbangModal?.classList.contains('active')) closeHakbangDeleteModal();
        });
        document.getElementById('confirmDeleteHakbang')?.addEventListener('click', async event => {
            if (!pendingHakbangDelete) return;
            const card = pendingHakbangDelete;
            const button = event.currentTarget;
            setBusy(button, true);
            try {
                const { message } = await sendHakbangRequest(
                    'DELETE',
                    `${superadminUrls.r402Hakbangs}/${card.dataset.hakbangId}`
                );
                card.remove();
                closeHakbangDeleteModal();
                refreshHakbangRows();
                showToast(message || 'Hakbang deleted.');
            } catch (error) {
                showToast(error.message, 'error');
            } finally {
                setBusy(button, false);
            }
        });

        // ===== Access and role updates =====
        const usersTableBody = document.getElementById('usersTableBody');
        usersTableBody?.addEventListener('change', (e) => {
            const control = e.target;
            const row = control.closest('tr[data-user-id]');
            if (!row) return;

            const update = control.classList.contains('access-toggle')
                ? { is_verified: control.checked ? 1 : 0 }
                : control.classList.contains('tungkulin-select')
                    ? { tungkulin: control.value || null }
                    : null;
            if (!update) return;

            const previousValue = control.type === 'checkbox' ? !control.checked : control.dataset.previousValue;
            control.disabled = true;
            sendUpdate(`${superadminUrls.users}/${row.dataset.userId}`, update)
                .then(({ data }) => {
                    if (control.classList.contains('tungkulin-select')) {
                        control.dataset.previousValue = data.tungkulin || '';
                    } else {
                        const pill = row.querySelector('.status-pill');
                        control.checked = data.is_verified;
                        pill.classList.toggle('on', data.is_verified);
                        pill.classList.toggle('off', !data.is_verified);
                        pill.lastChild.textContent = data.is_verified ? 'Verified' : 'Not Verified';
                        row.dataset.verified = data.is_verified ? '1' : '0';
                        applyUserFilters();
                        if (data.revoked_current_session) {
                            window.location.assign('/login?access=revoked');
                            return;
                        }
                        const permissionRow = document.querySelector(`#permissionsTableBody tr[data-user-id="${row.dataset.userId}"]`);
                        if (!data.is_verified) {
                            permissionRow?.remove();
                            document.getElementById('permEmptyState').style.display =
                                document.querySelector('#permissionsTableBody tr[data-user-id]') ? 'none' : 'block';
                        }
                        if (data.is_verified && !permissionRow) window.location.reload();
                    }
                    showToast('User settings saved.');
                })
                .catch(error => {
                    if (control.type === 'checkbox') control.checked = previousValue;
                    else control.value = previousValue ?? '';
                    showToast(error.message, 'error');
                })
                .finally(() => { control.disabled = false; });
        });

        // ===== Edit Permissions of Secretaries modal =====
        const permModalOverlay = document.getElementById('permissionsModalOverlay');
        const editPermissionsBtn = document.getElementById('editPermissionsBtn');
        const closePermissionsModal = document.getElementById('closePermissionsModal');
        const cancelPermissionsBtn = document.getElementById('cancelPermissionsBtn');

        // Move the modal to be a direct child of <body> so position:fixed is
        // always relative to the viewport (not a transformed ancestor).
        if (permModalOverlay && permModalOverlay.parentElement !== document.body) {
            document.body.appendChild(permModalOverlay);
        }

        // Force critical visual properties inline so the dimmed backdrop shows
        // even if the app's global CSS has conflicting overlay rules.
        if (permModalOverlay) {
            permModalOverlay.style.position = 'fixed';
            permModalOverlay.style.top = '0';
            permModalOverlay.style.left = '0';
            permModalOverlay.style.right = '0';
            permModalOverlay.style.bottom = '0';
            permModalOverlay.style.width = '100%';
            permModalOverlay.style.height = '100%';
            permModalOverlay.style.margin = '0';
            permModalOverlay.style.zIndex = '99999';
            permModalOverlay.style.background = 'rgba(0, 0, 0, 0.65)';
            permModalOverlay.style.justifyContent = 'center';
            permModalOverlay.style.overflowY = 'auto';
            permModalOverlay.style.boxSizing = 'border-box';
        }

        function openPermissionsModal() {
            permModalOverlay.style.display = 'flex';
            permModalOverlay.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        function closePermissionsModalFn() {
            permModalOverlay.style.display = 'none';
            permModalOverlay.classList.remove('active');
            document.body.style.overflow = '';
        }

        editPermissionsBtn?.addEventListener('click', openPermissionsModal);
        closePermissionsModal?.addEventListener('click', closePermissionsModalFn);
        cancelPermissionsBtn?.addEventListener('click', closePermissionsModalFn);
        document.getElementById('savePermissionsBtn')?.addEventListener('click', async (e) => {
            const button = e.currentTarget;
            const permissions = Array.from(document.querySelectorAll('#permissionsTableBody tr[data-user-id]')).map(row => {
                const values = { id: Number(row.dataset.userId) };
                row.querySelectorAll('[data-permission]').forEach(input => {
                    values[input.dataset.permission] = input.checked ? 1 : 0;
                });
                return values;
            });

            setBusy(button, true);
            try {
                await sendUpdate(superadminUrls.permissions, { permissions });
                closePermissionsModalFn();
                showToast('Permissions saved.');
            } catch (error) {
                showToast(error.message, 'error');
            } finally {
                setBusy(button, false);
            }
        });
        permModalOverlay?.addEventListener('click', (e) => {
            if (e.target === permModalOverlay) closePermissionsModalFn();
        });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && permModalOverlay.classList.contains('active')) {
                closePermissionsModalFn();
            }
        });

        // ===== Signatories: add / remove Purok, and Sakop na Grupo preview =====
        const purokList = document.getElementById('purokList');
        const addPurokBtn = document.getElementById('addPurokBtn');
        const saveSignatoriesBtn = document.getElementById('saveSignatoriesBtn');
        let purokCounter = purokList.querySelectorAll('.purok-card').length;
        const signatoryUsers = @json($users->map(fn ($user) => [
            'id' => $user->id,
            'name' => trim(($user->first_name ?? '').' '.($user->last_name ?? '')) ?: $user->gmail,
        ])->values());
        const escapeHtml = value => String(value).replace(/[&<>"']/g, character => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;',
        }[character]));

        function collectSignatories() {
            return Array.from(purokList.querySelectorAll('.purok-card')).map(card => {
                const id = card.dataset.id;
                return {
                    ...(id ? { id: Number(id) } : {}),
                    purok_number: Number(card.querySelector('.purok-number-input').value),
                    purok_name: normalizeName(card.querySelector('.purok-name-input').value),
                    secretary: card.querySelector('.secretary-input').value || null,
                    manggagawa: card.querySelector('.manggagawa-input').value.trim() || null,
                    ministerial_id: card.querySelector('.purok-id-input').value.trim() || null,
                    grupo_start: Number(card.querySelector('.sakop-from').value),
                    grupo_end: Number(card.querySelector('.sakop-to').value),
                    full_purok: card.querySelector('.complete-purok-check').checked ? 1 : 0,
                };
            });
        }

        let savedSignatoriesSnapshot = JSON.stringify(collectSignatories());
        function getSakopOverlap(signatories = collectSignatories()) {
            for (let i = 0; i < signatories.length; i++) {
                const current = signatories[i];
                for (let j = i + 1; j < signatories.length; j++) {
                    const other = signatories[j];
                    if (Number(current.purok_number) !== Number(other.purok_number)) continue;

                    const complete = current.full_purok || other.full_purok;
                    const overlaps = current.grupo_start <= other.grupo_end
                        && other.grupo_start <= current.grupo_end;
                    if (complete) {
                        const completeName = current.full_purok
                            ? current.purok_name
                            : other.purok_name;
                        return `${completeName || `Purok ${current.purok_number}`} cannot be marked Complete Purok while another Purok shares header ${current.purok_number}. Use non-overlapping Sakop na Grupo ranges instead.`;
                    }
                    if (overlaps) {
                        const currentName = current.purok_name || `Purok ${current.purok_number}`;
                        const otherName = other.purok_name || `Purok ${other.purok_number}`;
                        return `${currentName} and ${otherName} use overlapping Sakop na Grupo ranges under header ${current.purok_number}.`;
                    }
                }
            }
            return '';
        }

        let lastSignatoryOverlap = '';
        function updateSignatoriesSaveState(notify = false) {
            const signatories = collectSignatories();
            const overlap = getSakopOverlap(signatories);
            saveSignatoriesBtn.disabled = Boolean(overlap) || JSON.stringify(signatories) === savedSignatoriesSnapshot;
            if (notify && overlap && overlap !== lastSignatoryOverlap) {
                showToast(overlap, 'error');
            }
            lastSignatoryOverlap = overlap;
        }

        purokList.addEventListener('input', () => updateSignatoriesSaveState(true));
        purokList.addEventListener('change', () => updateSignatoriesSaveState(true));
        updateSignatoriesSaveState();

        function updateSakopPreview(card) {
            const nameInput = card.querySelector('.purok-name-input');
            const fromInput = card.querySelector('.sakop-from');
            const toInput = card.querySelector('.sakop-to');
            const previewEl = card.querySelector('.sakop-preview');
            if (!nameInput || !fromInput || !toInput || !previewEl) return;

            const numberInput = card.querySelector('.purok-number-input');
            const completeCheck = card.querySelector('.complete-purok-check');
            const sakopRow = card.querySelector('.sakop-row');
            const prefix = numberInput && numberInput.value !== '' ? numberInput.value : null;
            const from = fromInput.value;
            const to = toInput.value;
            const isComplete = completeCheck && completeCheck.checked;

            sakopRow.classList.toggle('disabled', isComplete);
            fromInput.disabled = isComplete;
            toInput.disabled = isComplete;

            if (isComplete) {
                previewEl.textContent = prefix
                    ? `All groups: Purok ${prefix}-1, ${prefix}-2, ${prefix}-3 ...`
                    : 'Enter an Assigned Number to preview.';
            } else if (prefix && from !== '' && to !== '') {
                previewEl.textContent = `Range: Purok ${prefix}-${from} to ${prefix}-${to}`;
            } else {
                previewEl.textContent = 'Enter an Assigned Number and a From/To range to preview.';
            }
        }

        function buildPurokCard(name, number) {
            const card = document.createElement('div');
            card.className = 'purok-card';
            const userOptions = signatoryUsers.map(user =>
                `<option value="${user.id}">${escapeHtml(user.name)}</option>`
            ).join('');
            const safeName = escapeHtml(name);
            card.innerHTML = `
                <div class="purok-head">
                    <div class="purok-head-left">
                        <svg width="17" height="17" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" style="color:var(--sa-ink-soft); flex-shrink:0;"><path d="M3 8l7-5 7 5v9H3z"/><path d="M8 17v-5h4v5"/></svg>
                        <div class="purok-name-wrap">
                            <div class="purok-name-view">
                                <span class="purok-name-display">${safeName}</span>
                                <button type="button" class="purok-edit-btn" title="Edit Purok name" aria-label="Edit Purok name"><svg width="15" height="15" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M13.5 3.5l3 3L7 16H4v-3z"/><path d="M12 5l3 3"/></svg></button>
                            </div>
                            <div class="purok-name-edit">
                                <input type="text" class="purok-name-input" value="${safeName}" maxlength="40">
                                <button type="button" class="sa-btn sa-btn-primary sa-btn-sm purok-name-save">Save</button>
                                <button type="button" class="sa-btn sa-btn-ghost sa-btn-sm purok-name-cancel">Cancel</button>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="sa-btn sa-btn-danger sa-btn-sm remove-purok-btn">Remove</button>
                </div>
                <div class="purok-body">
                    <div class="signatory-row">
                        <span class="signatory-role-badge">Header Number</span>
                        <input type="number" class="signatory-input purok-number-input" placeholder="e.g. 1" min="1" value="${number}">
                    </div>
                    <div class="signatory-row">
                        <span class="signatory-role-badge">Secretary</span>
                        <select class="signatory-input secretary-input">
                            <option value="">Not assigned</option>${userOptions}
                        </select>
                    </div>
                    <div class="signatory-row">
                        <span class="signatory-role-badge">Manggagawa</span>
                        <input type="text" class="signatory-input manggagawa-input" placeholder="Full name" maxlength="255">
                    </div>
                    <div class="signatory-row">
                        <span class="signatory-role-badge">ID Number</span>
                        <input type="text" class="signatory-input purok-id-input" placeholder="Ministerial ID number">
                    </div>
                    <label class="complete-row">
                        <input type="checkbox" class="complete-purok-check">
                        <div class="complete-row-text">
                            <strong>Complete Purok</strong>
                            <span>Includes every group that starts with this Purok number (e.g. 1-1, 1-2, 1-3 ...).</span>
                        </div>
                    </label>
                    <div class="sakop-row">
                        <span class="signatory-role-badge">Sakop na Grupo</span>
                        <div>
                            <div class="sakop-range-inputs">
                                <input type="number" class="signatory-input sakop-from" placeholder="From" value="1" min="0">
                                <span>to</span>
                                <input type="number" class="signatory-input sakop-to" placeholder="To" value="24" min="0">
                            </div>
                            <div class="sakop-preview">Enter an Assigned Number and a From/To range to preview.</div>
                        </div>
                    </div>
                </div>
            `;
            return card;
        }

        addPurokBtn?.addEventListener('click', () => {
            purokCounter++;
            while (nameExists(`Purok ${purokCounter}`)) purokCounter++;
            const card = buildPurokCard(`Purok ${purokCounter}`, purokCounter);
            purokList.appendChild(card);
            document.getElementById('noPuroksMessage')?.remove();
            updateSakopPreview(card);
            updateSignatoriesSaveState();
        });

        saveSignatoriesBtn?.addEventListener('click', async (e) => {
            const button = e.currentTarget;
            if (button.disabled) return;
            const puroks = collectSignatories();
            const overlap = getSakopOverlap(puroks);
            if (overlap) {
                showToast(overlap, 'error');
                return;
            }

            setBusy(button, true);
            try {
                await sendUpdate(superadminUrls.signatories, { puroks });
                savedSignatoriesSnapshot = JSON.stringify(puroks);
                showToast('Purok signatories saved.');
            } catch (error) {
                showToast(error.message, 'error');
            } finally {
                setBusy(button, false);
                updateSignatoriesSaveState();
            }
        });
        document.getElementById('discardSignatoriesBtn')?.addEventListener('click', () => window.location.reload());

        purokList?.addEventListener('click', (e) => {
            if (e.target.classList.contains('remove-purok-btn')) {
                e.target.closest('.purok-card').remove();
                updateSignatoriesSaveState();
            }
        });

        purokList?.addEventListener('change', (e) => {
            if (e.target.classList.contains('complete-purok-check')) {
                updateSakopPreview(e.target.closest('.purok-card'));
            }
        });

        purokList?.addEventListener('input', (e) => {
            if (
                e.target.classList.contains('purok-number-input') ||
                e.target.classList.contains('sakop-from') ||
                e.target.classList.contains('sakop-to')
            ) {
                updateSakopPreview(e.target.closest('.purok-card'));
            }
        });
        purokList.querySelectorAll('.purok-card').forEach(updateSakopPreview);

        // ===== Purok name: display + edit icon, with unique-name validation =====
        function normalizeName(v) {
            return v.trim().replace(/\s+/g, ' ');
        }

        function nameExists(name, exceptCard) {
            const key = normalizeName(name).toLowerCase();
            return Array.from(purokList.querySelectorAll('.purok-card')).some(card => {
                if (card === exceptCard) return false;
                const display = card.querySelector('.purok-name-display');
                return display && normalizeName(display.textContent).toLowerCase() === key;
            });
        }

        function validatePurokName(card) {
            const input = card.querySelector('.purok-name-input');
            const value = normalizeName(input.value);
            let message = '';

            if (value === '') {
                message = 'Purok name can\'t be empty.';
            } else if (nameExists(value, card)) {
                message = `"${value}" is already used by another Purok. Choose a different name.`;
            }

            input.classList.toggle('invalid', message !== '');
            return message;
        }

        function openNameEditor(card) {
            const display = card.querySelector('.purok-name-display');
            const input = card.querySelector('.purok-name-input');
            input.value = display.textContent;
            card.querySelector('.purok-name-view').style.display = 'none';
            card.querySelector('.purok-name-edit').classList.add('active');
            validatePurokName(card);
            input.focus();
            input.select();
        }

        function closeNameEditor(card) {
            const input = card.querySelector('.purok-name-input');
            input.classList.remove('invalid');
            card.querySelector('.purok-name-edit').classList.remove('active');
            card.querySelector('.purok-name-view').style.display = '';
        }

        function saveName(card) {
            const validationMessage = validatePurokName(card);
            if (validationMessage) {
                showToast(validationMessage, 'error');
                return;
            }
            const input = card.querySelector('.purok-name-input');
            card.querySelector('.purok-name-display').textContent = normalizeName(input.value);
            closeNameEditor(card);
        }

        purokList?.addEventListener('click', (e) => {
            const card = e.target.closest('.purok-card');
            if (!card) return;
            if (e.target.closest('.purok-edit-btn')) openNameEditor(card);
            else if (e.target.closest('.purok-name-save')) saveName(card);
            else if (e.target.closest('.purok-name-cancel')) closeNameEditor(card);
        });

        purokList?.addEventListener('input', (e) => {
            if (e.target.classList.contains('purok-name-input')) {
                validatePurokName(e.target.closest('.purok-card'));
            }
        });

        purokList?.addEventListener('keydown', (e) => {
            if (!e.target.classList.contains('purok-name-input')) return;
            const card = e.target.closest('.purok-card');
            if (e.key === 'Enter') { e.preventDefault(); saveName(card); }
            if (e.key === 'Escape') { e.stopPropagation(); closeNameEditor(card); }
        });

        // ===== Global Variables: de-listment requirement gray-out logic =====
        document.querySelectorAll('[data-controls]').forEach(checkbox => {
            const targetField = document.getElementById(checkbox.dataset.controls);
            const input = targetField.querySelector('input, select');

            checkbox.addEventListener('change', () => {
                const enabled = checkbox.checked;
                targetField.classList.toggle('disabled', !enabled);
                input.disabled = !enabled;
            });
        });

        const globalVariableFields = {
            locale: 'gvLocale',
            lcode: 'gvLcode',
            district: 'gvDistrict',
            dcode: 'gvDcode',
            s_number: 'gvSNumber',
            pastor_destinado: 'gvPastor',
            ksp_pd: 'gvKsp',
            head_lsp: 'gvHeadLsp',
            kalihim_ms: 'gvKalihimMs',
            kalihim_uwp: 'gvKalihimUwp',
            baptism_requirement: 'gvBaptismDate',
            entry_years_requirement: 'gvEntryYears',
        };
        // Both "Document settings" and "R2-10 eligibility" pages save through the same existing endpoint,
        // so the payload is identical to before and the backend is unchanged.
        document.querySelectorAll('[data-save-globals]').forEach(saveButton => saveButton.addEventListener('click', async () => {
            const values = Object.fromEntries(Object.entries(globalVariableFields).map(([column, id]) => {
                const value = document.getElementById(id).value;
                return [column, value === '' ? null : value];
            }));
            values.dateofbaptism_requirement_boolean = document.getElementById('delistBaptismCheck').checked ? 1 : 0;
            values.r401_use_purok_secretary = document.getElementById('gvR401PurokSecretary').checked ? 1 : 0;
            setInlineError('globals', '');
            document.querySelectorAll('[data-save-globals]').forEach(item => setBusy(item, true));
            saveButton.classList.add('is-loading');
            try {
                await sendUpdate(superadminUrls.globalVariables, values);
                showToast('Settings saved.');
            } catch (error) {
                setInlineError('globals', error.message);
                showToast(error.message, 'error');
            } finally {
                document.querySelectorAll('[data-save-globals]').forEach(item => setBusy(item, false));
            }
        }));
        document.querySelectorAll('[data-discard-globals]').forEach(button => button.addEventListener('click', () => window.location.reload()));

        const r401Toggle = document.getElementById('gvR401PurokSecretary');
        const syncR401Effect = () => {
            document.querySelectorAll('[data-r401-effect]').forEach(element => {
                element.classList.toggle('is-active', (element.dataset.r401Effect === 'on') === Boolean(r401Toggle?.checked));
            });
        };
        r401Toggle?.addEventListener('change', syncR401Effect);
        syncR401Effect();

        const securityInputs = ['requireOtpSetting', 'allowForgotPasswordSetting'];
        const securityDefaults = Object.fromEntries(securityInputs.map(id => [id, document.getElementById(id).checked]));
        document.getElementById('saveSecuritySettingsBtn')?.addEventListener('click', async event => {
            const button = event.currentTarget;
            setBusy(button, true);
            try {
                await sendUpdate(superadminUrls.securitySettings, {
                    require_otp: document.getElementById('requireOtpSetting').checked ? 1 : 0,
                    allow_forgot_password: document.getElementById('allowForgotPasswordSetting').checked ? 1 : 0,
                });
                showToast('Security settings saved.');
            } catch (error) {
                showToast(error.message, 'error');
            } finally {
                setBusy(button, false);
            }
        });
        document.getElementById('discardSecuritySettingsBtn')?.addEventListener('click', () => {
            securityInputs.forEach(id => { document.getElementById(id).checked = securityDefaults[id]; });
        });
        // ===== Secretary activity: "Add Filter" popover (submits the same GET filters as before) =====
        (function initAuditFilters() {
            const form = document.getElementById('recordAuditFilters');
            if (!form) return;
            const defs = [
                { key: 'date', label: 'Date range', fields: [
                    { name: 'date_from', label: 'From date', type: 'date' },
                    { name: 'date_to', label: 'To date', type: 'date' } ] },
                { key: 'record_name', label: 'Record name', fields: [
                    { name: 'record_name', label: 'Record name', type: 'search', placeholder: 'Search member name', maxLength: 150 } ] },
                { key: 'actor_email', label: 'Secretary email', fields: [
                    { name: 'actor_email', label: 'Secretary email', type: 'search', placeholder: 'Search account email', maxLength: 255 } ] },
            ];
            const addBtn = document.getElementById('addFilterBtn');
            const pop = document.getElementById('filterPopover');
            const listView = document.getElementById('filterListView');
            const search = document.getElementById('filterSearch');
            const list = document.getElementById('filterList');
            const editor = document.getElementById('filterEditor');
            const chips = document.getElementById('filterChips');
            const hidden = name => form.querySelector(`input[type="hidden"][name="${name}"]`);
            const chevron = '<svg width="14" height="14" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7.5 4.5L13 10l-5.5 5.5"/></svg>';
            const isActive = def => def.fields.some(field => hidden(field.name).value !== '');
            const summary = def => {
                if (!isActive(def)) return 'All';
                if (def.key === 'date') return `${hidden('date_from').value || '…'} → ${hidden('date_to').value || '…'}`;
                return hidden(def.fields[0].name).value;
            };
            const el = (tag, className, text) => {
                const node = document.createElement(tag);
                if (className) node.className = className;
                if (text !== undefined) node.textContent = text;
                return node;
            };

            function renderChips() {
                chips.replaceChildren();
                const active = defs.filter(isActive);
                active.forEach(def => {
                    const chip = el('span', 'sa-chip');
                    const label = el('button', 'sa-chip-label');
                    label.type = 'button';
                    label.append(`${def.label}:`, el('strong', '', summary(def)));
                    label.setAttribute('aria-label', `Edit ${def.label} filter`);
                    label.addEventListener('click', () => { openPopover(); showEditor(def); });
                    const remove = el('button', 'sa-chip-remove', '×');
                    remove.type = 'button';
                    remove.setAttribute('aria-label', `Remove ${def.label} filter`);
                    remove.addEventListener('click', () => { def.fields.forEach(f => { hidden(f.name).value = ''; }); form.submit(); });
                    chip.append(label, remove);
                    chips.appendChild(chip);
                });
                if (active.length > 1) {
                    const clear = el('button', 'sa-chip-clear', 'Clear all');
                    clear.type = 'button';
                    clear.addEventListener('click', () => { defs.forEach(d => d.fields.forEach(f => { hidden(f.name).value = ''; })); form.submit(); });
                    chips.appendChild(clear);
                }
            }

            function renderList(term) {
                list.replaceChildren();
                const matches = defs.filter(def => def.label.toLowerCase().includes(term.trim().toLowerCase()));
                if (!matches.length) { list.appendChild(el('li', 'sa-filter-empty', 'No matching filters.')); return; }
                matches.forEach(def => {
                    const item = el('li');
                    const button = el('button', 'sa-filter-item');
                    button.type = 'button';
                    button.append(el('span', '', def.label));
                    const value = el('span', 'sa-filter-item-value');
                    value.append(el('span', '', summary(def)));
                    value.insertAdjacentHTML('beforeend', chevron);
                    button.appendChild(value);
                    button.addEventListener('click', () => showEditor(def));
                    item.appendChild(button);
                    list.appendChild(item);
                });
            }

            function showList() {
                editor.hidden = true; listView.hidden = false;
                search.value = ''; renderList(''); search.focus();
            }

            function showEditor(def) {
                listView.hidden = true; editor.hidden = false; editor.replaceChildren();
                const head = el('div', 'sa-filter-editor-head');
                const back = el('button', 'sa-filter-back');
                back.type = 'button'; back.setAttribute('aria-label', 'Back to filters');
                back.insertAdjacentHTML('beforeend', '<svg width="14" height="14" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12.5 4.5L7 10l5.5 5.5"/></svg>');
                back.addEventListener('click', showList);
                head.append(back, el('span', '', def.label));
                const body = el('div', 'sa-filter-editor-body');
                const inputs = def.fields.map((field, index) => {
                    const wrap = el('div');
                    const label = el('label', '', field.label);
                    const input = document.createElement('input');
                    input.type = field.type; input.id = `filterField_${field.name}`;
                    label.htmlFor = input.id;
                    input.value = hidden(field.name).value;
                    if (field.placeholder) input.placeholder = field.placeholder;
                    if (field.maxLength) input.maxLength = field.maxLength;
                    wrap.append(label, input); body.appendChild(wrap);
                    return { field, input };
                });
                const actions = el('div', 'sa-filter-editor-actions');
                if (isActive(def)) {
                    const clear = el('button', 'sa-btn sa-btn-ghost sa-btn-sm', 'Remove');
                    clear.type = 'button';
                    clear.addEventListener('click', () => { def.fields.forEach(f => { hidden(f.name).value = ''; }); form.submit(); });
                    actions.appendChild(clear);
                }
                const apply = el('button', 'sa-btn sa-btn-primary sa-btn-sm', 'Apply');
                apply.type = 'button';
                const submit = () => {
                    inputs.forEach(({ field, input }) => { hidden(field.name).value = input.value.trim(); });
                    form.submit();
                };
                apply.addEventListener('click', submit);
                actions.appendChild(apply);
                inputs.forEach(({ input }) => input.addEventListener('keydown', event => {
                    if (event.key === 'Enter') { event.preventDefault(); submit(); }
                }));
                editor.append(head, body, actions);
                inputs[0].input.focus();
            }

            function openPopover() {
                pop.hidden = false; addBtn.setAttribute('aria-expanded', 'true'); showList();
            }
            function closePopover(restoreFocus = false) {
                pop.hidden = true; addBtn.setAttribute('aria-expanded', 'false');
                if (restoreFocus) addBtn.focus();
            }
            addBtn.addEventListener('click', () => (pop.hidden ? openPopover() : closePopover()));
            search.addEventListener('input', () => renderList(search.value));
            search.addEventListener('keydown', event => { if (event.key === 'Enter') event.preventDefault(); });
            document.addEventListener('click', event => {
                if (!pop.hidden && !event.target.closest('.sa-filter-menu') && !event.target.closest('.sa-chip')) closePopover();
            });
            document.addEventListener('keydown', event => {
                if (event.key === 'Escape' && !pop.hidden) { event.stopPropagation(); closePopover(true); }
            });
            renderChips();
        })();
        // ===== Database backups (automatic + manual) =====
        (function initBackups() {
            const body = document.getElementById('backupsBody');
            const state = document.getElementById('backupsState');
            const count = document.getElementById('backupsCount');
            const refresh = document.getElementById('refreshBackupsBtn');
            const sourceButtons = document.querySelectorAll('[data-backup-source]');
            const uploadInput = document.getElementById('backupUploadFile');
            const uploadButton = document.getElementById('backupUploadBtn');
            const uploadSelected = document.getElementById('backupUploadSelected');
            const uploadError = document.getElementById('backupUploadError');
            if (!body) return;
            let items = [];
            let sourceFilter = '';

            const formatDate = iso => {
                const date = new Date(iso);
                return Number.isNaN(date.getTime()) ? String(iso) : new Intl.DateTimeFormat('en-PH', { dateStyle: 'medium', timeStyle: 'medium', timeZone: 'Asia/Manila' }).format(date);
            };
            const formatSize = bytes => {
                const value = Number(bytes);
                if (!Number.isFinite(value)) return '—';
                if (value < 1024) return `${value} B`;
                if (value < 1024 * 1024) return `${(value / 1024).toFixed(1)} KB`;
                return `${(value / 1024 / 1024).toFixed(1)} MB`;
            };
            const showState = message => { state.textContent = message; state.hidden = !message; };
            const cell = child => {
                const td = document.createElement('td');
                td.append(child);
                return td;
            };
            const urlFor = (template, name) => template.replace('__FILE__', encodeURIComponent(name));

            function renderSkeleton() {
                body.replaceChildren();
                for (let i = 0; i < 4; i++) {
                    const row = document.createElement('tr');
                    ['60%', '30%', '45%', '25%', '35%'].forEach(width => {
                        const bar = document.createElement('span');
                        bar.className = 'sa-skeleton';
                        bar.style.width = width;
                        row.appendChild(cell(bar));
                    });
                    body.appendChild(row);
                }
            }

            function startRename(nameCell, item) {
                const dot = item.name.lastIndexOf('.');
                const base = dot > 0 ? item.name.slice(0, dot) : item.name;
                const extension = dot > 0 ? item.name.slice(dot) : '';
                const wrap = document.createElement('div');
                wrap.className = 'backup-rename';
                const line = document.createElement('div');
                line.className = 'backup-rename-row';
                const input = document.createElement('input');
                input.type = 'text';
                input.value = base;
                input.maxLength = 100;
                input.setAttribute('aria-label', `New name for ${item.name}`);
                const suffix = document.createElement('span');
                suffix.textContent = extension;
                const save = document.createElement('button');
                save.type = 'button';
                save.className = 'sa-btn sa-btn-primary sa-btn-sm';
                save.textContent = 'Save';
                const cancel = document.createElement('button');
                cancel.type = 'button';
                cancel.className = 'sa-btn sa-btn-ghost sa-btn-sm';
                cancel.textContent = 'Cancel';
                const error = document.createElement('p');
                error.className = 'backup-rename-error';
                error.setAttribute('role', 'alert');
                error.hidden = true;
                line.append(input, suffix, save, cancel);
                wrap.append(line, error);
                const originalNodes = [...nameCell.childNodes];
                nameCell.replaceChildren(wrap);
                input.focus();
                input.select();

                let saving = false;
                const onOutside = event => {
                    if (!saving && !wrap.contains(event.target)) cancelRename();
                };
                function cancelRename() {
                    document.removeEventListener('mousedown', onOutside, true);
                    nameCell.replaceChildren(...originalNodes);
                }
                document.addEventListener('mousedown', onOutside, true);

                const submit = async () => {
                    error.hidden = true;
                    saving = true;
                    setBusy(save, true);
                    try {
                        const result = await sendUpdate(urlFor(superadminUrls.databaseBackupRename, item.name), { name: input.value.trim() }, 'PUT');
                        document.removeEventListener('mousedown', onOutside, true);
                        showToast(result.message);
                        await load();
                    } catch (failure) {
                        saving = false;
                        error.textContent = failure.message;
                        error.hidden = false;
                        setBusy(save, false);
                        input.focus();
                    }
                };
                save.addEventListener('click', submit);
                cancel.addEventListener('click', cancelRename);
                input.addEventListener('keydown', event => {
                    if (event.key === 'Enter') { event.preventDefault(); submit(); }
                    if (event.key === 'Escape') { event.stopPropagation(); cancelRename(); }
                });
            }

            function render() {
                body.replaceChildren();
                const visible = items.filter(item => !sourceFilter || item.source === sourceFilter);
                count.textContent = items.length ? `${visible.length} of ${items.length} backup${items.length === 1 ? '' : 's'}` : '';
                if (!items.length) { showState('No backups yet. One is created automatically before each database import, or add one above.'); return; }
                if (!visible.length) { showState('No backups match this filter.'); return; }
                showState('');

                visible.forEach(item => {
                    const row = document.createElement('tr');
                    const isNew = Boolean(window.saHighlightBackup) && item.name === window.saHighlightBackup;
                    if (isNew) row.classList.add('sa-row-new');
                    const nameCell = document.createElement('td');
                    const name = document.createElement('span');
                    name.className = 'sa-mono';
                    name.textContent = item.name;
                    nameCell.appendChild(name);
                    if (isNew) {
                        const fresh = document.createElement('span');
                        fresh.className = 'sa-badge is-new';
                        fresh.textContent = 'New';
                        nameCell.appendChild(fresh);
                    }
                    if (item === items[0]) {
                        const latest = document.createElement('span');
                        latest.className = 'sa-badge';
                        latest.textContent = 'Latest';
                        nameCell.appendChild(latest);
                    }

                    const sourceInfo = {
                        import: { label: 'Before import', css: ' is-import', hint: 'Safety backup made right before a database import' },
                        auto: { label: 'Monthly auto', css: '', hint: 'Monthly automatic backup' },
                        manual: { label: 'Manual', css: ' is-manual', hint: 'Added by a Head Secretary from an exported file' },
                    }[item.source] || { label: item.source, css: '', hint: '' };
                    const source = document.createElement('span');
                    source.className = `sa-badge is-source${sourceInfo.css}`;
                    source.textContent = sourceInfo.label;
                    source.title = sourceInfo.hint;

                    const actions = document.createElement('div');
                    actions.className = 'backup-actions';
                    if (superadminUrls.databaseBackupRename) {
                        const renameButton = document.createElement('button');
                        renameButton.type = 'button';
                        renameButton.className = 'sa-btn sa-btn-ghost sa-btn-sm';
                        renameButton.textContent = 'Rename';
                        renameButton.setAttribute('aria-label', `Rename ${item.name}`);
                        renameButton.addEventListener('click', () => startRename(nameCell, item));
                        actions.appendChild(renameButton);
                    }
                    if (superadminUrls.databaseBackupDownload) {
                        const link = document.createElement('a');
                        link.className = 'sa-btn sa-btn-ghost sa-btn-sm';
                        link.href = urlFor(superadminUrls.databaseBackupDownload, item.name);
                        link.textContent = 'Download';
                        link.setAttribute('aria-label', `Download ${item.name}`);
                        actions.appendChild(link);
                    }

                    row.append(nameCell, cell(source), cell(document.createTextNode(formatDate(item.created_at))), cell(document.createTextNode(formatSize(item.size))), cell(actions));
                    body.appendChild(row);
                });
            }

            async function load() {
                showState('');
                count.textContent = '';
                if (!superadminUrls.databaseBackups) {
                    body.replaceChildren();
                    showState('Backup listing is not enabled on the server yet. Add the backup routes to enable it.');
                    return;
                }
                setBusy(refresh, true);
                renderSkeleton();
                try {
                    const response = await fetch(superadminUrls.databaseBackups, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                    const result = await response.json();
                    if (!response.ok) throw new Error(result.message || 'The backups could not be loaded.');
                    items = Array.isArray(result.data) ? result.data : [];
                    render();
                    if (result.pruned > 0) {
                        showToast(`${result.pruned} backup${result.pruned === 1 ? '' : 's'} older than 1 year ${result.pruned === 1 ? 'was' : 'were'} deleted automatically.`);
                    }
                } catch (error) {
                    items = [];
                    body.replaceChildren();
                    showState(error.message);
                    showToast(error.message, 'error');
                } finally {
                    setBusy(refresh, false);
                }
            }
            window.saLoadBackups = load;
            refresh?.addEventListener('click', load);
            sourceButtons.forEach(button => button.addEventListener('click', () => {
                sourceFilter = button.dataset.backupSource;
                sourceButtons.forEach(item => {
                    item.classList.toggle('active', item === button);
                    item.setAttribute('aria-pressed', String(item === button));
                });
                render();
            }));

            // ----- Manual backup upload -----
            function resetUpload() {
                uploadInput.value = '';
                uploadSelected.hidden = true;
                uploadButton.disabled = true;
            }
            const showUploadError = message => { uploadError.textContent = message; uploadError.hidden = !message; };
            uploadInput?.addEventListener('change', () => {
                showUploadError('');
                const file = uploadInput.files?.[0];
                if (!file) { resetUpload(); return; }
                if (!/\.sql$/i.test(file.name)) { resetUpload(); showUploadError('Choose an .sql export file.'); return; }
                document.getElementById('backupUploadName').textContent = file.name;
                document.getElementById('backupUploadSize').textContent = `${(file.size / 1024 / 1024).toFixed(1)} MB · ready to save`;
                uploadSelected.hidden = false;
                uploadButton.disabled = !superadminUrls.databaseBackupStore;
                if (!superadminUrls.databaseBackupStore) showUploadError('Manual backups are not enabled on the server yet. Add the backup routes to enable them.');
            });
            document.getElementById('backupUploadClear')?.addEventListener('click', () => { resetUpload(); showUploadError(''); });
            uploadButton?.addEventListener('click', async () => {
                const file = uploadInput.files?.[0];
                if (!file || !superadminUrls.databaseBackupStore) return;
                showUploadError('');
                const formData = new FormData();
                formData.append('file', file);
                setBusy(uploadButton, true);
                try {
                    const result = await sendFormData(superadminUrls.databaseBackupStore, formData);
                    showToast(`${result.message} Saved as ${result.data.name}`);
                    resetUpload();
                    await load();
                } catch (error) {
                    showUploadError(error.message);
                    showToast(error.message, 'error');
                    uploadButton.disabled = false;
                } finally {
                    setBusy(uploadButton, false);
                    uploadButton.disabled = !uploadInput.files?.length;
                }
            });

            if (document.getElementById('tab-backups')?.classList.contains('active')) load();
        })();

        document.querySelectorAll('[data-open-backups]').forEach(button => button.addEventListener('click', () => {
            if (databaseImportModal?.classList.contains('active')) closeDatabaseImportReview();
            activateTab('backups');
            window.history.replaceState(null, '', '#backups');
            window.saLoadBackups?.();
        }));

        // Show the result of an import that just completed (real values from the server response, kept across the reload)
        try {
            const stored = window.sessionStorage.getItem('saImportResult');
            if (stored) {
                window.sessionStorage.removeItem('saImportResult');
                const result = JSON.parse(stored);
                const banner = document.getElementById('databaseImportResult');
                if (banner && result.backup) {
                    document.getElementById('databaseImportResultMessage').textContent = result.message || 'Database import complete.';
                    document.getElementById('databaseImportResultFile').textContent = result.backup;
                    banner.hidden = false;
                    window.saHighlightBackup = result.backup;
                    activateTab('backups');
                    window.history.replaceState(null, '', '#backups');
                    window.saLoadBackups?.();
                }
            }
        } catch (error) { /* sessionStorage unavailable: the toast already reported the result */ }
        document.getElementById('dismissImportResult')?.addEventListener('click', () => { document.getElementById('databaseImportResult').hidden = true; });

        // ===== SMTP setup: one Gmail sender, popup flow =====
        (function initSmtpSetup() {
            const $ = id => document.getElementById(id);
            const modal = $('smtpModal');
            const form = $('smtpSettingsForm');
            if (!modal || !form) return;
            const stepCode = $('smtpStepCode');
            const stepDone = $('smtpStepDone');
            const codeInput = $('smtpVerifyCode');
            const boxGroup = $('smtpCodeBoxes');
            const boxes = [...boxGroup.querySelectorAll('.otp-box')];
            function syncCode() {
                codeInput.value = boxes.map(box => box.value).join('');
                confirmButton.disabled = codeInput.value.length !== 6;
                boxes.forEach(box => box.classList.toggle('filled', box.value !== ''));
                boxGroup.classList.remove('is-error');
            }
            function fillCode(digits, start = 0) {
                digits.slice(0, 6 - start).split('').forEach((digit, offset) => { boxes[start + offset].value = digit; });
                syncCode();
                boxes[Math.min(start + digits.length, 5)].focus();
            }
            function clearCode(focus = true) {
                boxes.forEach(box => { box.value = ''; });
                syncCode();
                if (focus) boxes[0].focus();
            }
            const confirmButton = $('smtpVerifyConfirmBtn');
            const resendButton = $('smtpVerifyResendBtn');
            const modalError = $('smtpModalError');
            const saveButton = $('saveSmtpSettingsBtn');
            const CHECK = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>';
            let state = @json($smtpVerification);
            let resendTimer = null;

            if (modal.parentElement !== document.body) document.body.appendChild(modal);

            const fmt = iso => new Intl.DateTimeFormat('en-PH', { dateStyle: 'medium', timeStyle: 'short', timeZone: 'Asia/Manila' }).format(new Date(iso));
            const showModalError = message => { modalError.textContent = message || ''; modalError.hidden = !message; };

            function showStep(step) {
                form.hidden = step !== 'form';
                stepCode.hidden = step !== 'code';
                stepDone.hidden = step !== 'done';
            }

            function render() {
                const saved = Boolean(state.saved);
                $('smtpEmpty').hidden = saved;
                $('smtpAccountView').hidden = !saved;
                if (!saved) return;

                const verified = Boolean(state.verified);
                const mark = $('smtpAccountIcon');
                mark.className = `smtpx-mark ${verified ? 'is-ok' : 'is-warn'}`;
                mark.innerHTML = verified ? CHECK : '!';
                $('smtpAccountEmail').textContent = state.email || '';
                $('smtpAccountStatus').textContent = verified
                    ? `Verified${state.verified_at ? ` on ${fmt(state.verified_at)}` : ''} · in use`
                    : 'Not verified · not in use';
                $('smtpAccountNote').textContent = verified
                    ? 'This Gmail account sends all system emails, including sign-in codes and password recovery. To use another account, change it. The current one is replaced.'
                    : "This account is saved but can't send emails until you enter the code we sent.";
                const codeButton = $('smtpCodeBtn');
                codeButton.hidden = verified;
                codeButton.textContent = state.pending ? 'Enter code' : 'Send code';

                if (verified) {
                    if (smtpSetupAlert) smtpSetupAlert.hidden = true;
                    if (smtpSecurityAlert) smtpSecurityAlert.hidden = true;
                }
            }

            function updateResend(seconds) {
                window.clearInterval(resendTimer);
                let remaining = Math.max(0, Number(seconds) || 0);
                const paint = () => {
                    resendButton.disabled = remaining > 0;
                    resendButton.textContent = remaining > 0 ? `Resend in ${remaining}s` : 'Resend code';
                };
                paint();
                if (remaining === 0) return;
                resendTimer = window.setInterval(() => {
                    remaining -= 1;
                    paint();
                    if (remaining <= 0) window.clearInterval(resendTimer);
                }, 1000);
            }

            function openModal() { modal.classList.add('active'); modal.setAttribute('aria-hidden', 'false'); }
            function closeModal() {
                modal.classList.remove('active');
                modal.setAttribute('aria-hidden', 'true');
                window.clearInterval(resendTimer);
            }

            function openForm() {
                form.reset();
                $('smtpEmail').value = '';
                $('smtpModalTitle').textContent = state.saved ? 'Change Gmail' : 'Connect Gmail';
                $('smtpReplaceNote').hidden = !state.saved;
                showStep('form');
                openModal();
                $('smtpEmail').focus();
            }

            function openCode() {
                $('smtpCodeText').textContent = `Enter the 6-digit code sent to ${state.sent_to || state.recipient || 'your Gmail'}${state.expires_at ? `. It expires ${fmt(state.expires_at)}` : ''}.`;
                showModalError('');
                clearCode(false);
                updateResend(state.resend_in);
                showStep('code');
                openModal();
                boxes[0].focus();
            }

            async function requestCode(button) {
                showModalError('');
                setBusy(button, true);
                try {
                    const result = await sendUpdate(superadminUrls.smtpVerificationSend, {}, 'POST');
                    state = result.state;
                    render();
                    openCode();
                    showToast(result.message);
                } catch (error) {
                    showModalError(error.message);
                    showToast(error.message, 'error');
                } finally {
                    setBusy(button, false);
                    if (button === resendButton) updateResend(state.resend_in);
                }
            }

            // Save -> code is emailed -> code step
            form.addEventListener('submit', async event => {
                event.preventDefault();
                setBusy(saveButton, true);
                try {
                    const result = await sendFormData(superadminUrls.smtpSettings, new URLSearchParams(new FormData(form)), 'PUT');
                    $('smtpAppPassword').value = '';
                    state = result.state;
                    render();
                    if (result.email_sent && state.pending) {
                        openCode();
                    } else {
                        closeModal();
                        showToast(result.message, 'error');
                    }
                } catch (error) {
                    showToast(error.message, 'error');
                } finally {
                    setBusy(saveButton, false);
                }
            });

            $('smtpConnectBtn').addEventListener('click', openForm);
            $('smtpChangeBtn').addEventListener('click', openForm);
            $('smtpCodeBtn').addEventListener('click', event => {
                if (state.pending) openCode(); else requestCode(event.currentTarget);
            });

            $('smtpRemoveBtn').addEventListener('click', async event => {
                const button = event.currentTarget;
                const confirmed = await confirmAction({
                    title: 'Remove this Gmail sender?',
                    message: `${state.email || 'This account'} will be deleted. System emails (sign-in codes, password recovery) won't send until a new Gmail is connected and verified.`,
                    confirmLabel: 'Remove Gmail',
                    danger: true,
                });
                if (!confirmed) return;
                setBusy(button, true);
                try {
                    const result = await sendUpdate(superadminUrls.smtpSettings, {}, 'DELETE');
                    state = result.state;
                    render();
                    showToast(result.message);
                } catch (error) {
                    showToast(error.message, 'error');
                } finally {
                    setBusy(button, false);
                }
            });

            // Popup controls
            modal.querySelectorAll('[data-smtp-close]').forEach(button => button.addEventListener('click', closeModal));
            modal.addEventListener('click', event => { if (event.target === modal) closeModal(); });
            document.addEventListener('keydown', event => {
                if (event.key === 'Escape' && modal.classList.contains('active')) closeModal();
            });
            resendButton.addEventListener('click', () => requestCode(resendButton));
            boxes.forEach((box, i) => {
                box.addEventListener('focus', () => box.select());
                box.addEventListener('keydown', event => {
                    if (event.ctrlKey || event.metaKey || event.altKey) return;
                    if (/^\d$/.test(event.key)) {
                        event.preventDefault();
                        box.value = event.key;
                        syncCode();
                        if (i < 5) boxes[i + 1].focus();
                    } else if (event.key === 'Backspace') {
                        event.preventDefault();
                        if (box.value) { box.value = ''; }
                        else if (i > 0) { boxes[i - 1].value = ''; boxes[i - 1].focus(); }
                        syncCode();
                    } else if (event.key === 'Delete') {
                        event.preventDefault(); box.value = ''; syncCode();
                    } else if (event.key === 'ArrowLeft' && i > 0) {
                        event.preventDefault(); boxes[i - 1].focus();
                    } else if (event.key === 'ArrowRight' && i < 5) {
                        event.preventDefault(); boxes[i + 1].focus();
                    } else if (event.key === 'Enter') {
                        event.preventDefault();
                        if (!confirmButton.disabled) confirmButton.click();
                    } else if (event.key.length === 1) {
                        event.preventDefault(); // block letters and symbols
                    }
                });
                // Mobile keyboards and one-time-code autofill arrive here
                box.addEventListener('input', () => {
                    const digits = box.value.replace(/\D/g, '');
                    box.value = '';
                    if (digits) fillCode(digits, digits.length >= 6 ? 0 : i);
                    else syncCode();
                });
                box.addEventListener('paste', event => {
                    event.preventDefault();
                    const digits = (event.clipboardData?.getData('text') || '').replace(/\D/g, '');
                    if (digits) fillCode(digits, digits.length >= 6 ? 0 : i);
                });
            });
            confirmButton.addEventListener('click', async () => {
                showModalError('');
                setBusy(confirmButton, true);
                try {
                    const result = await sendUpdate(superadminUrls.smtpVerificationVerify, { code: codeInput.value }, 'POST');
                    state = result.state;
                    render();
                    $('smtpDoneText').textContent = `${state.email} is now the sender for all LSPDMS system emails.`;
                    window.clearInterval(resendTimer);
                    showStep('done');
                    showToast('Gmail verified.');
                } catch (error) {
                    showModalError(error.message);
                    boxGroup.classList.add('is-error');
                    boxes.forEach(box => { box.value = ''; });
                    boxes.forEach(box => box.classList.remove('filled'));
                    codeInput.value = '';
                    boxes[0].focus();
                } finally {
                    setBusy(confirmButton, false);
                    confirmButton.disabled = state.verified || codeInput.value.length !== 6;
                }
            });
            $('smtpDoneBtn').addEventListener('click', closeModal);

            // Top alert's "Configure SMTP" button should open the popup directly
            document.querySelectorAll('[data-open-smtp]').forEach(button => button.addEventListener('click', () => {
                if (!state.saved) openForm();
            }));

            render();
        })();
    })();
</script>
@endsection