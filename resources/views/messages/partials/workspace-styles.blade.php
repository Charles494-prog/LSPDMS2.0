<style>
    /* Chat To-Do + Tasks page. Vercel-style: monochrome ink on white (or white on black), 1px grey lines as
       separators instead of shadows, controlled corners (8px cards, 6px controls), and color only where it
       carries meaning: red = overdue / danger, amber = due soon / waiting, green = done. */
    .workspace {
        --ws-canvas: #fafafa; --ws-card: #ffffff; --ws-soft: #f5f5f5; --ws-hover: #ededed;
        --ws-ink: #171717; --ws-muted: #666666; --ws-faint: #8f8f8f; --ws-line: #eaeaea; --ws-line-strong: #d4d4d4;
        --ws-on-ink: #ffffff;
        --ws-red: #e5484d; --ws-red-soft: #fff0f0; --ws-amber: #ab6400; --ws-amber-soft: #fff4e5; --ws-green: #297c3b; --ws-green-soft: #ebf7ed;
        --ws-r: 8px; --ws-r-sm: 6px;
        display: flex; flex-direction: column; flex: 1; width: 100%; min-width: 0; min-height: 0; height: 100%;
        overflow: hidden; border: 1px solid var(--border); border-radius: 12px; background: var(--ws-canvas); color: var(--ws-ink);
        font-feature-settings: "tnum";
    }
    html[data-theme="dark"] .workspace {
        --ws-canvas: #000000; --ws-card: #0a0a0a; --ws-soft: #141414; --ws-hover: #1f1f1f;
        --ws-ink: #ededed; --ws-muted: #a1a1a1; --ws-faint: #7c7c7c; --ws-line: #262626; --ws-line-strong: #3a3a3a;
        --ws-on-ink: #0a0a0a;
        --ws-red: #ff6166; --ws-red-soft: #2a1314; --ws-amber: #f5a524; --ws-amber-soft: #2a2010; --ws-green: #62c073; --ws-green-soft: #0f2416;
    }
    .chat-frame-page .workspace { border: 0; border-radius: 0; }
    .workspace .ws-icon { width: 18px; height: 18px; flex: none; fill: none; stroke: currentColor; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }

    /* Chat window tabs: text + 2px ink underline */
    .workspace-tabs { display: grid; grid-template-columns: repeat(2, 1fr); flex: none; padding: 0 .5rem; border-bottom: 1px solid var(--ws-line); background: var(--ws-card); }
    .workspace-tabs button { position: relative; display: flex; align-items: center; justify-content: center; gap: .45rem; height: 44px; border: 0; background: transparent; color: var(--ws-muted); font: inherit; font-size: .86rem; font-weight: 600; cursor: pointer; transition: color .15s ease; }
    .workspace-tabs button:hover { color: var(--ws-ink); }
    .workspace-tabs button[aria-selected="true"] { color: var(--ws-ink); }
    .workspace-tabs button[aria-selected="true"]::after { content: ""; position: absolute; left: 12px; right: 12px; bottom: -1px; height: 2px; background: var(--ws-ink); }
    .ws-count { display: grid; place-items: center; min-width: 18px; height: 18px; padding: 0 5px; border-radius: 999px; background: var(--ws-ink); color: var(--ws-on-ink); font-size: .66rem; font-weight: 700; line-height: 1; }
    .ws-count[hidden] { display: none; }

    .workspace-panel { display: flex; flex: 1; min-width: 0; min-height: 0; }
    .workspace-panel[hidden] { display: none; }
    .workspace-panel > .messages-page { border: 0; border-radius: 0; }
    .ws-scroll { overflow-y: auto; overscroll-behavior: contain; }
    .ws-column { display: grid; align-content: start; gap: .75rem; width: 100%; max-width: 680px; margin: 0 auto; padding: .85rem .75rem 1.5rem; }

    /* Cards and controls */
    .ws-card { border: 1px solid var(--ws-line); border-radius: var(--ws-r); background: var(--ws-card); }
    .ws-card-head { display: flex; align-items: flex-start; justify-content: space-between; gap: .75rem; padding: .85rem 1rem .4rem; }
    .ws-card-head h2 { margin: 0; font-size: 1rem; font-weight: 650; letter-spacing: -.01em; }
    .ws-card-head p { margin: .15rem 0 0; color: var(--ws-muted); font-size: .8rem; }
    .ws-btn { display: inline-flex; align-items: center; justify-content: center; gap: .4rem; min-height: 34px; padding: 0 .85rem; border: 1px solid var(--ws-line); border-radius: var(--ws-r-sm); background: var(--ws-card); color: var(--ws-ink); font: inherit; font-size: .84rem; font-weight: 600; cursor: pointer; transition: background .15s ease, border-color .15s ease; }
    .ws-btn:hover:not(:disabled) { background: var(--ws-soft); border-color: var(--ws-line-strong); }
    .ws-btn.primary { border-color: var(--ws-ink); background: var(--ws-ink); color: var(--ws-on-ink); }
    .ws-btn.primary:hover:not(:disabled) { background: var(--ws-ink); opacity: .86; }
    .ws-btn:disabled { opacity: .5; cursor: default; }
    .workspace button:focus-visible, .workspace input:focus-visible, .workspace textarea:focus-visible, .workspace select:focus-visible, .workspace [tabindex]:focus-visible { outline: 2px solid var(--ws-ink); outline-offset: 2px; }
    .ws-kbd { display: inline-grid; place-items: center; min-width: 20px; height: 20px; padding: 0 5px; border: 1px solid var(--ws-line); border-radius: 4px; color: var(--ws-faint); font: 600 .68rem ui-monospace, SFMono-Regular, Menlo, monospace; }

    .ws-status { margin: 0; padding: 0 .25rem; color: var(--ws-muted); font-size: .78rem; }
    .ws-status:empty { display: none; }
    .ws-status.error { color: var(--ws-red); }
    .ws-loading, .ws-error, .ws-empty-inline { margin: 0; padding: 1rem; color: var(--ws-muted); font-size: .85rem; text-align: center; list-style: none; }
    .ws-error { color: var(--ws-red); }
    .ws-empty { display: grid; justify-items: center; gap: .35rem; padding: 2.5rem 1rem; border: 1px dashed var(--ws-line-strong); border-radius: var(--ws-r); color: var(--ws-muted); font-size: .85rem; text-align: center; list-style: none; }
    .ws-empty strong { color: var(--ws-ink); font-size: .95rem; font-weight: 650; }
    .ws-empty .ws-btn { margin-top: .4rem; }
    .ws-empty-icon { width: 36px; height: 36px; margin-bottom: .25rem; padding: 8px; border: 1px solid var(--ws-line); border-radius: var(--ws-r); fill: none; stroke: var(--ws-muted); stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
    .ws-todo-list .ws-empty, .ws-feed > .ws-empty { border-style: dashed; }
    .ws-todo-list .ws-empty { margin: .25rem .6rem .6rem; }

    /* Avatars: monochrome initials */
    .ws-avatar { position: relative; display: grid; place-items: center; flex: none; width: 36px; height: 36px; overflow: hidden; border: 1px solid var(--ws-line); border-radius: 50%; background: var(--ws-soft); color: var(--ws-ink); font-size: .74rem; font-weight: 650; }
    .ws-avatar img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
    .ws-avatar.sm { width: 28px; height: 28px; font-size: .64rem; }
    .ws-avatar.xs { width: 22px; height: 22px; font-size: .56rem; }
    .ws-avatar.more { color: var(--ws-muted); }
    .ws-avatar-stack { display: flex; }
    .ws-avatar-stack .ws-avatar { border: 2px solid var(--ws-card); }
    .ws-avatar-stack .ws-avatar + .ws-avatar { margin-left: -6px; }
    .ws-avatar-stack .ws-avatar.is-done { border-color: var(--ws-green); }

    /* To-Do (chat window) */
    .ws-todo-add { display: flex; align-items: flex-end; gap: .4rem; margin: .4rem 1rem .6rem; padding: .3rem .3rem .3rem .75rem; border: 1px solid var(--ws-line); border-radius: var(--ws-r); background: var(--ws-card); transition: border-color .15s ease; }
    .ws-todo-add:focus-within { border-color: var(--ws-ink); }
    .ws-todo-add textarea { flex: 1; min-height: 32px; max-height: 160px; padding: .4rem 0; border: 0; outline: 0; resize: none; background: transparent; color: var(--ws-ink); font: inherit; font-size: .88rem; line-height: 1.4; }
    .ws-todo-add textarea:focus-visible { outline: none; }
    .ws-date-btn { position: relative; display: grid; place-items: center; width: 32px; height: 32px; border-radius: var(--ws-r-sm); color: var(--ws-muted); cursor: pointer; }
    .ws-date-btn:hover { background: var(--ws-soft); color: var(--ws-ink); }
    .ws-date-btn input, .ws-chip-date { position: absolute; inset: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; }
    .ws-date-btn input::-webkit-calendar-picker-indicator, .ws-chip-date::-webkit-calendar-picker-indicator { position: absolute; inset: 0; width: auto; height: auto; cursor: pointer; }
    .ws-todo-card .ws-status { padding: 0 1rem .4rem; }
    .ws-todo-list { margin: 0; padding: 0 0 .25rem; list-style: none; }
    .ws-todo { display: flex; align-items: flex-start; gap: .65rem; padding: .6rem 1rem; border-top: 1px solid var(--ws-line); transition: background .15s ease, opacity .35s ease, transform .35s ease; }
    .ws-todo:hover { background: var(--ws-soft); }
    .ws-todo.is-done { opacity: 0; transform: translateX(12px); }
    .ws-todo.is-flash { background: var(--ws-amber-soft); }
    .ws-check { display: grid; place-items: center; flex: none; width: 20px; height: 20px; margin-top: 1px; padding: 0; border: 1.5px solid var(--ws-line-strong); border-radius: 5px; background: transparent; color: transparent; cursor: pointer; transition: border-color .15s ease, background .15s ease, color .15s ease; }
    .ws-check .ws-icon { width: 13px; height: 13px; stroke-width: 3; }
    .ws-check:hover { border-color: var(--ws-ink); color: var(--ws-faint); }
    .ws-todo.is-done .ws-check { border-color: var(--ws-ink); background: var(--ws-ink); color: var(--ws-on-ink); }
    .ws-todo-body { display: grid; gap: .3rem; flex: 1; min-width: 0; }
    .ws-todo-text { margin: 0; color: var(--ws-ink); font-size: .88rem; line-height: 1.45; white-space: pre-wrap; overflow-wrap: anywhere; cursor: text; }
    .ws-todo-editor { width: 100%; min-height: 34px; padding: .35rem .5rem; border: 1px solid var(--ws-ink); border-radius: var(--ws-r-sm); outline: 0; resize: none; background: var(--ws-card); color: var(--ws-ink); font: inherit; font-size: .88rem; line-height: 1.45; }
    .ws-todo-meta { display: flex; flex-wrap: wrap; gap: .35rem; }

    /* Chips (status / dates): bordered, color only for meaning */
    .ws-chip { position: relative; display: inline-flex; align-items: center; gap: .3rem; height: 22px; padding: 0 .5rem; border: 1px solid var(--ws-line); border-radius: var(--ws-r-sm); background: var(--ws-card); color: var(--ws-muted); font-size: .72rem; font-weight: 600; white-space: nowrap; }
    label.ws-chip { cursor: pointer; }
    label.ws-chip:hover { border-color: var(--ws-line-strong); color: var(--ws-ink); }
    .ws-chip .ws-icon { width: 12px; height: 12px; }
    .ws-chip.soon { border-color: transparent; background: var(--ws-amber-soft); color: var(--ws-amber); }
    .ws-chip.late { border-color: transparent; background: var(--ws-red-soft); color: var(--ws-red); }

    /* To-Do timeline */
    .ws-timeline { margin: 0 1rem .6rem; padding: .6rem .75rem .45rem; border: 1px solid var(--ws-line); border-radius: var(--ws-r); }
    .ws-timeline[hidden] { display: none; }
    .ws-tl-label { margin: 0 0 .55rem; color: var(--ws-muted); font-size: .7rem; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; }
    .ws-tl-track { position: relative; height: 14px; margin: 0 8px; }
    .ws-tl-track::before { content: ""; position: absolute; left: 0; right: 0; top: 6px; height: 1px; background: var(--ws-line-strong); }
    .ws-tl-today { position: absolute; top: -2px; width: 2px; height: 18px; margin-left: -1px; background: var(--ws-ink); }
    .ws-tl-dot { position: absolute; top: 2px; width: 10px; height: 10px; margin-left: -5px; padding: 0; border: 2px solid var(--ws-card); border-radius: 50%; background: var(--ws-faint); box-shadow: 0 0 0 1px var(--ws-line-strong); cursor: pointer; transition: transform .15s ease; }
    .ws-tl-dot:hover { transform: scale(1.3); }
    .ws-tl-dot.soon { background: var(--ws-amber); box-shadow: none; }
    .ws-tl-dot.late { background: var(--ws-red); box-shadow: none; }
    .ws-tl-ends { display: flex; justify-content: space-between; margin-top: .3rem; color: var(--ws-muted); font-size: .7rem; }

    /* Tasks: summary stats (also quick filters) */
    .ws-stats { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); overflow: hidden; border: 1px solid var(--ws-line); border-radius: var(--ws-r); background: var(--ws-card); }
    .ws-stat { display: grid; gap: .2rem; padding: .85rem 1rem; border: 0; border-left: 1px solid var(--ws-line); background: transparent; color: var(--ws-ink); font: inherit; text-align: left; cursor: pointer; transition: background .15s ease; }
    .ws-stat:first-child { border-left: 0; }
    .ws-stat:hover { background: var(--ws-soft); }
    .ws-stat[aria-pressed="true"] { box-shadow: inset 0 -2px 0 var(--ws-ink); }
    .ws-stat-value { font-size: 1.45rem; font-weight: 650; letter-spacing: -.03em; line-height: 1.1; }
    .ws-stat-label { color: var(--ws-muted); font-size: .76rem; font-weight: 500; }
    .ws-stat.danger .ws-stat-value { color: var(--ws-red); }
    @media (max-width: 760px) {
        .ws-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .ws-stat { border-left: 0; border-top: 1px solid var(--ws-line); }
        .ws-stat:nth-child(-n+2) { border-top: 0; }
        .ws-stat:nth-child(even) { border-left: 1px solid var(--ws-line); }
    }

    /* Tasks: toolbar (Open/Completed tabs, search, sort) + filter pills */
    .ws-toolbar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .6rem; padding-bottom: .5rem; border-bottom: 1px solid var(--ws-line); }
    .ws-tabs { display: flex; gap: 1rem; }
    .ws-tabs button { position: relative; display: inline-flex; align-items: center; gap: .4rem; height: 34px; padding: 0; border: 0; background: none; color: var(--ws-muted); font: inherit; font-size: .88rem; font-weight: 600; cursor: pointer; }
    .ws-tabs button:hover, .ws-tabs button[aria-selected="true"] { color: var(--ws-ink); }
    .ws-tabs button[aria-selected="true"]::after { content: ""; position: absolute; left: 0; right: 0; bottom: -.55rem; height: 2px; background: var(--ws-ink); }
    .ws-tab-count { color: var(--ws-faint); font-size: .78rem; font-weight: 500; }
    .ws-toolbar-right { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; }
    .ws-search { display: flex; align-items: center; gap: .4rem; height: 34px; padding: 0 .4rem 0 .6rem; border: 1px solid var(--ws-line); border-radius: var(--ws-r-sm); background: var(--ws-card); color: var(--ws-muted); }
    .ws-search:focus-within { border-color: var(--ws-ink); }
    .ws-search .ws-icon { width: 15px; height: 15px; }
    .ws-search input { width: 180px; min-width: 0; border: 0; outline: 0; background: transparent; color: var(--ws-ink); font: inherit; font-size: .84rem; }
    .ws-search input:focus-visible { outline: none; }
    .ws-select { height: 34px; padding: 0 .55rem; border: 1px solid var(--ws-line); border-radius: var(--ws-r-sm); background: var(--ws-card); color: var(--ws-ink); font: inherit; font-size: .84rem; cursor: pointer; }
    .ws-filters { display: flex; flex-wrap: wrap; gap: .35rem; }
    .ws-filters[hidden] { display: none; }
    .ws-filter { height: 28px; padding: 0 .65rem; border: 1px solid var(--ws-line); border-radius: 999px; background: var(--ws-card); color: var(--ws-muted); font: inherit; font-size: .78rem; font-weight: 600; cursor: pointer; }
    .ws-filter:hover { color: var(--ws-ink); border-color: var(--ws-line-strong); }
    .ws-filter[aria-pressed="true"] { border-color: var(--ws-ink); background: var(--ws-ink); color: var(--ws-on-ink); }
    @media (max-width: 560px) { .ws-search input { width: 120px; } }

    /* Tasks: composer */
    .ws-composer { padding: .75rem; }
    .ws-composer-row { display: flex; align-items: center; gap: .6rem; }
    .ws-composer-open { flex: 1; height: 38px; padding: 0 .85rem; border: 1px solid var(--ws-line); border-radius: var(--ws-r-sm); background: var(--ws-canvas); color: var(--ws-muted); font: inherit; font-size: .9rem; text-align: left; cursor: pointer; }
    .ws-composer-open:hover { border-color: var(--ws-line-strong); color: var(--ws-ink); }
    .ws-compose-form { display: grid; gap: .6rem; margin-top: .75rem; padding-top: .75rem; border-top: 1px solid var(--ws-line); }
    .ws-compose-form[hidden] { display: none; }
    .ws-compose-form h3 { margin: 0; font-size: .95rem; font-weight: 650; }
    .ws-compose-form input[type="text"], .ws-compose-form textarea, .ws-field-inline input {
        width: 100%; padding: .55rem .7rem; border: 1px solid var(--ws-line); border-radius: var(--ws-r-sm); background: var(--ws-card); color: var(--ws-ink); font: inherit; font-size: .9rem;
    }
    .ws-compose-form input[type="text"]:focus, .ws-compose-form textarea:focus, .ws-field-inline input:focus { border-color: var(--ws-ink); outline: none; }
    .ws-compose-form input[type="text"] { font-weight: 600; }
    .ws-compose-form textarea { min-height: 70px; max-height: 220px; resize: none; line-height: 1.45; }
    .ws-field-row { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem .75rem; }
    .ws-field-inline { display: flex; align-items: center; gap: .6rem; color: var(--ws-muted); font-size: .82rem; font-weight: 600; }
    .ws-field-inline input { width: auto; padding: .35rem .5rem; }
    .ws-quick-dates { display: flex; flex-wrap: wrap; gap: .3rem; }
    .ws-chip-btn { height: 26px; padding: 0 .55rem; border: 1px solid var(--ws-line); border-radius: var(--ws-r-sm); background: var(--ws-card); color: var(--ws-muted); font: inherit; font-size: .74rem; font-weight: 600; cursor: pointer; }
    .ws-chip-btn:hover { border-color: var(--ws-line-strong); color: var(--ws-ink); }
    .ws-fieldset { display: grid; gap: .3rem; margin: 0; padding: .6rem .7rem; border: 1px solid var(--ws-line); border-radius: var(--ws-r-sm); }
    .ws-fieldset legend { padding: 0 .3rem; color: var(--ws-ink); font-size: .78rem; font-weight: 650; }
    .ws-radio { display: flex; align-items: flex-start; gap: .55rem; padding: .35rem .3rem; border-radius: var(--ws-r-sm); cursor: pointer; }
    .ws-radio:hover { background: var(--ws-soft); }
    .ws-radio input, .ws-check-line input, .ws-person input { accent-color: var(--ws-ink); }
    .ws-radio input { margin-top: .2rem; }
    .ws-radio span { display: grid; }
    .ws-radio strong { font-size: .85rem; font-weight: 600; }
    .ws-radio small { color: var(--ws-muted); font-size: .76rem; }
    .ws-check-line { display: flex; align-items: center; gap: .5rem; padding: .25rem .3rem; color: var(--ws-ink); font-size: .82rem; cursor: pointer; }
    .ws-check-line[hidden] { display: none; }
    .ws-people { display: grid; max-height: 260px; overflow-y: auto; }
    .ws-person { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .3rem .8rem; padding: .4rem .3rem; border-top: 1px solid var(--ws-line); }
    .ws-person:first-child { border-top: 0; }
    .ws-person.is-off .ws-person-name { color: var(--ws-faint); }
    .ws-person-pick { display: flex; align-items: center; gap: .5rem; min-width: 0; cursor: pointer; }
    .ws-person-name { overflow: hidden; font-size: .85rem; font-weight: 600; text-overflow: ellipsis; white-space: nowrap; }
    .ws-person-perms { display: flex; gap: .35rem; }
    .ws-perm { display: inline-flex; align-items: center; gap: .3rem; height: 24px; padding: 0 .45rem; border: 1px solid var(--ws-line); border-radius: var(--ws-r-sm); color: var(--ws-muted); font-size: .72rem; font-weight: 600; cursor: pointer; }
    .ws-person.is-off .ws-perm { opacity: .45; cursor: default; }
    .ws-compose-actions { display: flex; justify-content: flex-end; gap: .5rem; }
    .ws-person.is-mentioned { background: var(--ws-soft); box-shadow: inset 2px 0 0 var(--ws-ink); transition: background .4s ease; }

    /* @mention picker: floats over the page (fixed, positioned by mentions.js under / above the box) */
    .ws-mention-menu { position: fixed; z-index: 1200; display: grid; width: 280px; max-width: calc(100vw - 16px); max-height: 264px; overflow-y: auto; padding: 4px; border: 1px solid var(--ws-line-strong); border-radius: var(--ws-r); background: var(--ws-card); color: var(--ws-ink); box-shadow: 0 8px 24px rgba(0, 0, 0, .08), 0 1px 2px rgba(0, 0, 0, .04); overscroll-behavior: contain; }
    html[data-theme="dark"] .ws-mention-menu { box-shadow: 0 8px 24px rgba(0, 0, 0, .6); }
    .ws-mention-menu[hidden] { display: none; }
    .ws-mention-option { display: flex; align-items: center; gap: .55rem; width: 100%; min-height: 40px; padding: .3rem .5rem; border: 0; border-radius: var(--ws-r-sm); background: transparent; color: var(--ws-ink); font: inherit; text-align: left; cursor: pointer; }
    .ws-mention-option + .ws-mention-option { margin-top: 1px; }
    .ws-mention-option.on { background: var(--ws-hover); }
    .ws-mention-text { display: grid; min-width: 0; line-height: 1.25; }
    .ws-mention-text strong { overflow: hidden; font-size: .84rem; font-weight: 600; text-overflow: ellipsis; white-space: nowrap; }
    .ws-mention-text span { overflow: hidden; color: var(--ws-muted); font: 500 .72rem ui-monospace, SFMono-Regular, Menlo, monospace; text-overflow: ellipsis; white-space: nowrap; }
    .ws-mention-empty { padding: .6rem .55rem; color: var(--ws-muted); font-size: .8rem; }
    /* A mention inside a task, comment or reply */
    .ws-mention { padding: 0 .2em; border-radius: 4px; background: color-mix(in srgb, var(--ws-ink) 7%, transparent); color: var(--ws-ink); font-weight: 650; white-space: nowrap; cursor: default; }

    /* Tasks: feed posts (Facebook structure, Vercel look) */
    .ws-feed { display: grid; gap: .75rem; }
    .ws-task { border: 1px solid var(--ws-line); border-radius: var(--ws-r); background: var(--ws-card); transition: border-color .2s ease, box-shadow .3s ease; }
    .ws-task:hover { border-color: var(--ws-line-strong); }
    .ws-task.is-waiting { box-shadow: inset 3px 0 0 var(--ws-amber); }
    .ws-task.is-new { border-color: var(--ws-ink); }
    .ws-task.is-flash { box-shadow: 0 0 0 3px color-mix(in srgb, var(--ws-ink) 18%, transparent); }
    .ws-new { flex: none; height: 20px; padding: 0 .45rem; border-radius: var(--ws-r-sm); background: var(--ws-ink); color: var(--ws-on-ink); font-size: .68rem; font-weight: 700; line-height: 20px; }
    .ws-task-head { display: flex; align-items: center; gap: .6rem; padding: .75rem 1rem 0; }
    .ws-task-who { display: grid; flex: 1; min-width: 0; }
    .ws-task-who strong { overflow: hidden; font-size: .86rem; font-weight: 600; text-overflow: ellipsis; white-space: nowrap; }
    .ws-task-when { display: flex; align-items: center; color: var(--ws-muted); font-size: .75rem; }
    .ws-audience { display: inline-flex; align-items: center; gap: .2rem; }
    .ws-audience .ws-icon { width: 12px; height: 12px; }
    .ws-task-tools { display: flex; gap: .1rem; }
    .ws-icon-btn { display: grid; place-items: center; width: 30px; height: 30px; padding: 0; border: 0; border-radius: var(--ws-r-sm); background: transparent; color: var(--ws-muted); cursor: pointer; }
    .ws-icon-btn .ws-icon { width: 16px; height: 16px; }
    .ws-icon-btn:hover { background: var(--ws-soft); color: var(--ws-ink); }
    .ws-icon-btn.danger:hover { background: var(--ws-red-soft); color: var(--ws-red); }
    .ws-task-main { display: grid; gap: .5rem; padding: .6rem 1rem .75rem; }
    .ws-task-title { margin: 0; font-size: 1rem; font-weight: 650; letter-spacing: -.01em; line-height: 1.35; overflow-wrap: anywhere; }
    .ws-task-body { margin: 0; color: var(--ws-ink); font-size: .9rem; line-height: 1.55; white-space: pre-wrap; overflow-wrap: anywhere; }
    .ws-task-tags { display: flex; flex-wrap: wrap; gap: .35rem; }
    .ws-pill { display: inline-flex; align-items: center; gap: .35rem; height: 22px; padding: 0 .5rem; border: 1px solid var(--ws-line); border-radius: var(--ws-r-sm); color: var(--ws-ink); font-size: .72rem; font-weight: 600; }
    .ws-pill .ws-icon { width: 13px; height: 13px; }
    .ws-pill.wait { border-color: transparent; background: var(--ws-amber-soft); color: var(--ws-amber); }
    .ws-dot { width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
    .ws-pill.open .ws-dot { background: var(--ws-green); }
    .ws-pill-progress { color: var(--ws-muted); font-weight: 500; }
    .ws-task-members { display: flex; align-items: center; gap: .5rem; color: var(--ws-muted); font-size: .76rem; }

    .ws-task-stats { display: flex; align-items: center; justify-content: space-between; gap: .5rem; margin: 0 1rem; padding: 0 0 .5rem; color: var(--ws-muted); font-size: .8rem; }
    .ws-link { padding: 0; border: 0; background: none; color: var(--ws-muted); font: inherit; cursor: pointer; }
    .ws-link:hover { color: var(--ws-ink); text-decoration: underline; }
    .ws-task-actions { display: flex; gap: .25rem; padding: .3rem .5rem; border-top: 1px solid var(--ws-line); }
    .ws-action { display: inline-flex; flex: 1; align-items: center; justify-content: center; gap: .4rem; height: 34px; border: 0; border-radius: var(--ws-r-sm); background: transparent; color: var(--ws-muted); font: inherit; font-size: .84rem; font-weight: 600; cursor: pointer; }
    .ws-action .ws-icon { width: 16px; height: 16px; }
    .ws-action:hover:not(:disabled) { background: var(--ws-soft); color: var(--ws-ink); }
    .ws-action.primary { color: var(--ws-ink); }
    .ws-action.primary .ws-icon { stroke-width: 2.4; }
    .ws-action:disabled { opacity: .5; cursor: default; }

    /* Reactions: the emoji carry the color; text stays monochrome */
    .ws-react-summary { display: inline-flex; align-items: center; gap: .3rem; cursor: default; }
    .ws-react-icons { display: inline-flex; }
    .ws-react-icons .ws-react-emoji { display: grid; place-items: center; width: 20px; height: 20px; margin-left: -4px; border: 1px solid var(--ws-line); border-radius: 50%; background: var(--ws-card); font-size: .74rem; line-height: 1; }
    .ws-react-icons .ws-react-emoji:first-child { margin-left: 0; }
    .ws-react-count { font-size: .8rem; }
    .ws-react-emoji.big { font-size: 1rem; line-height: 1; }
    .ws-react-wrap { position: relative; display: inline-flex; flex: 1; }
    .ws-react-wrap > .ws-action { flex: 1; }
    .ws-react-wrap.compact { flex: none; }
    .ws-action.reacted, .ws-meta-btn.reacted { color: var(--ws-ink); font-weight: 700; }
    .ws-react-picker {
        position: absolute; left: 0; bottom: calc(100% + 6px); z-index: 20; display: flex; gap: .1rem; padding: .25rem;
        border: 1px solid var(--ws-line); border-radius: 999px; background: var(--ws-card); box-shadow: 0 8px 24px rgba(0, 0, 0, .12);
        opacity: 0; transform: translateY(6px) scale(.92); transform-origin: bottom left; pointer-events: none; transition: opacity .15s ease, transform .18s ease;
    }
    html[data-theme="dark"] .ws-react-picker { box-shadow: 0 8px 24px rgba(0, 0, 0, .6); }
    .ws-react-wrap.picking .ws-react-picker { opacity: 1; transform: none; pointer-events: auto; }
    .ws-react-option { display: grid; place-items: center; width: 36px; height: 36px; padding: 0; border: 0; border-radius: 50%; background: transparent; font-size: 1.45rem; line-height: 1; cursor: pointer; transition: transform .15s cubic-bezier(.3, 1.6, .5, 1); }
    .ws-react-option:hover, .ws-react-option:focus-visible { transform: translateY(-5px) scale(1.25); outline: none; }
    .ws-react-option.on { background: var(--ws-soft); }

    /* Comments: flat rows with a subtle grey bubble, replies on a thread line */
    .ws-thread { display: grid; gap: .6rem; padding: .75rem 1rem .85rem; border-top: 1px solid var(--ws-line); background: var(--ws-canvas); border-radius: 0 0 var(--ws-r) var(--ws-r); }
    .ws-comment-block { display: grid; gap: .45rem; }
    .ws-comment { display: flex; align-items: flex-start; gap: .5rem; }
    .ws-comment-wrap { display: grid; justify-items: start; min-width: 0; }
    .ws-bubble { position: relative; max-width: 100%; padding: .45rem .7rem; border: 1px solid var(--ws-line); border-radius: var(--ws-r); background: var(--ws-card); }
    .ws-comment.mine .ws-bubble { background: var(--ws-soft); }
    .ws-bubble strong { display: block; font-size: .78rem; font-weight: 650; }
    .ws-bubble p { margin: .1rem 0 0; font-size: .87rem; line-height: 1.45; white-space: pre-wrap; overflow-wrap: anywhere; }
    .ws-react-summary.on-bubble { position: absolute; right: -8px; bottom: -10px; padding: 0 4px 0 1px; border: 1px solid var(--ws-line); border-radius: 999px; background: var(--ws-card); font-size: .7rem; }
    .ws-react-summary.on-bubble .ws-react-emoji { width: 16px; height: 16px; border: 0; font-size: .66rem; }
    .ws-comment-meta { display: flex; align-items: center; gap: .7rem; padding: .25rem .25rem 0; }
    .ws-meta-btn { padding: 0; border: 0; background: none; color: var(--ws-muted); font: inherit; font-size: .74rem; font-weight: 600; cursor: pointer; }
    .ws-meta-btn:hover { color: var(--ws-ink); text-decoration: underline; }
    .ws-comment-time { color: var(--ws-faint); font-size: .72rem; }
    .ws-comment-meta .ws-react-picker { bottom: calc(100% + 4px); }
    .ws-comment-meta .ws-react-option { width: 30px; height: 30px; font-size: 1.2rem; }
    .ws-replies { display: grid; gap: .45rem; margin-left: 1.1rem; padding-left: 1.05rem; border-left: 1px solid var(--ws-line-strong); }
    .ws-comment.reply .ws-bubble { padding: .35rem .6rem; }
    .ws-event { display: flex; align-items: center; gap: .4rem; margin: 0; color: var(--ws-muted); font-size: .76rem; }
    .ws-event::before { content: ""; width: 6px; height: 6px; margin: 0 .55rem 0 .6rem; border-radius: 50%; background: var(--ws-line-strong); }
    .ws-comment-form { display: flex; align-items: flex-end; gap: .45rem; }
    .ws-comment-input { flex: 1; min-height: 34px; max-height: 140px; padding: .45rem .7rem; border: 1px solid var(--ws-line); border-radius: var(--ws-r); outline: 0; resize: none; background: var(--ws-card); color: var(--ws-ink); font: inherit; font-size: .86rem; line-height: 1.35; }
    .ws-comment-input:focus { border-color: var(--ws-ink); }
    .ws-comment-form.reply .ws-comment-input { min-height: 30px; padding: .35rem .6rem; font-size: .83rem; }
    .ws-send { display: grid; place-items: center; width: 34px; height: 34px; padding: 0; border: 1px solid var(--ws-ink); border-radius: var(--ws-r-sm); background: var(--ws-ink); color: var(--ws-on-ink); cursor: pointer; }
    .ws-send:hover:not(:disabled) { opacity: .86; }
    .ws-send .ws-icon { width: 15px; height: 15px; }

    /* Completed list */
    .ws-done-list { display: grid; }
    .ws-done-row { display: flex; align-items: center; gap: .75rem; padding: .75rem 1rem; border-top: 1px solid var(--ws-line); }
    .ws-done-row:first-child { border-top: 0; }
    .ws-done-check { display: grid; place-items: center; flex: none; width: 22px; height: 22px; border-radius: 50%; background: var(--ws-green-soft); color: var(--ws-green); }
    .ws-done-check .ws-icon { width: 13px; height: 13px; stroke-width: 2.6; }
    .ws-done-body { display: grid; flex: 1; min-width: 0; }
    .ws-done-body strong { overflow: hidden; font-size: .88rem; font-weight: 600; text-overflow: ellipsis; white-space: nowrap; }
    .ws-done-meta { color: var(--ws-muted); font-size: .76rem; }

    /* Side column: Due soon, calendar, activity */
    .ws-side-card { padding: .85rem 1rem; }
    .ws-side-head { display: flex; align-items: center; gap: .45rem; margin-bottom: .6rem; color: var(--ws-muted); }
    .ws-side-head h2 { margin: 0; color: var(--ws-ink); font-size: .9rem; font-weight: 650; }
    .ws-side-card.is-critical { border-color: color-mix(in srgb, var(--ws-red) 45%, var(--ws-line)); }
    .ws-side-card.is-critical .ws-side-head { color: var(--ws-red); }
    .ws-side-empty { margin: 0; color: var(--ws-muted); font-size: .8rem; }
    .ws-due-list { display: grid; margin: 0; padding: 0; list-style: none; }
    .ws-due-list li + li { border-top: 1px solid var(--ws-line); }
    .ws-due-item { display: grid; gap: .1rem; width: 100%; padding: .5rem 0; border: 0; background: none; color: var(--ws-ink); font: inherit; text-align: left; cursor: pointer; }
    .ws-due-item:hover .ws-due-title { text-decoration: underline; }
    .ws-due-when { display: flex; align-items: center; gap: .35rem; color: var(--ws-muted); font-size: .7rem; font-weight: 600; letter-spacing: .03em; text-transform: uppercase; }
    .ws-due-when::before { content: ""; width: 6px; height: 6px; border-radius: 50%; background: var(--ws-faint); }
    .ws-due-item.overdue .ws-due-when, .ws-due-item.today .ws-due-when { color: var(--ws-red); }
    .ws-due-item.overdue .ws-due-when::before, .ws-due-item.today .ws-due-when::before { background: var(--ws-red); }
    .ws-due-item.soon .ws-due-when { color: var(--ws-amber); }
    .ws-due-item.soon .ws-due-when::before { background: var(--ws-amber); }
    .ws-due-item.waiting .ws-due-when::before { background: var(--ws-green); }
    .ws-due-title { overflow: hidden; font-size: .85rem; font-weight: 600; text-overflow: ellipsis; white-space: nowrap; }

    .ws-cal-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: .4rem; }
    .ws-cal-head h2 { margin: 0; font-size: .9rem; font-weight: 650; }
    .ws-cal-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 2px; }
    .ws-cal-dow { padding: .2rem 0; color: var(--ws-faint); font-size: .68rem; font-weight: 600; text-align: center; }
    .ws-cal-day { position: relative; display: grid; justify-items: center; align-content: start; gap: 2px; min-height: 36px; padding: .25rem 0; border: 1px solid transparent; border-radius: var(--ws-r-sm); background: transparent; color: var(--ws-ink); font: inherit; cursor: pointer; }
    .ws-cal-day:hover { border-color: var(--ws-line); }
    .ws-cal-num { font-size: .78rem; font-weight: 500; }
    .ws-cal-day.today .ws-cal-num { display: grid; place-items: center; width: 22px; height: 22px; border-radius: 50%; background: var(--ws-ink); color: var(--ws-on-ink); font-weight: 650; }
    .ws-cal-day.has-tasks { background: var(--ws-soft); }
    .ws-cal-day.picked { border-color: var(--ws-ink); }
    .ws-cal-dots { display: flex; gap: 2px; }
    .ws-cal-dot { display: inline-block; width: 5px; height: 5px; border-radius: 50%; background: var(--ws-ink); }
    .ws-cal-dot.overdue, .ws-cal-dot.today { background: var(--ws-red); }
    .ws-cal-dot.soon { background: var(--ws-amber); }
    .ws-cal-dot.waiting { background: var(--ws-green); }
    .ws-cal-legend { display: flex; flex-wrap: wrap; gap: .3rem .75rem; margin-top: .55rem; color: var(--ws-muted); font-size: .7rem; }
    .ws-cal-legend span { display: inline-flex; align-items: center; gap: .3rem; }
    .ws-cal-picked { display: grid; margin-top: .65rem; padding-top: .6rem; border-top: 1px solid var(--ws-line); }
    .ws-cal-picked-date { margin: 0 0 .2rem; font-size: .78rem; font-weight: 650; }

    .ws-activity { display: grid; margin: 0; padding: 0; list-style: none; }
    .ws-activity li + li { border-top: 1px solid var(--ws-line); }
    .ws-activity-item { display: grid; grid-template-columns: auto minmax(0, 1fr); gap: .15rem .5rem; width: 100%; padding: .55rem 0; border: 0; background: none; color: var(--ws-ink); font: inherit; text-align: left; cursor: pointer; }
    .ws-activity-item .ws-avatar { grid-row: span 3; }
    .ws-activity-line { font-size: .8rem; line-height: 1.35; }
    .ws-activity-line strong { font-weight: 650; }
    .ws-activity-line em { font-style: normal; font-weight: 600; }
    .ws-activity-item:hover .ws-activity-line em { text-decoration: underline; }
    .ws-activity-quote { overflow: hidden; color: var(--ws-muted); font-size: .76rem; text-overflow: ellipsis; white-space: nowrap; }
    .ws-activity-time { color: var(--ws-faint); font-size: .7rem; }

    @media (max-width: 480px) {
        .ws-column { padding: .6rem .5rem 1.2rem; }
        .ws-person-perms { width: 100%; padding-left: 2.4rem; }
    }
    @media (prefers-reduced-motion: reduce) {
        .ws-todo, .ws-tl-dot, .workspace-tabs button, .ws-react-option, .ws-react-picker, .ws-task { transition: none; }
    }
</style>
