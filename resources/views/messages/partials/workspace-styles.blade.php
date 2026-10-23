<style>
    /* Chats / To-Do / Tasks workspace. Facebook-style: soft gray canvas, white rounded cards,
       round avatars, pill buttons, gray comment bubbles. Follows light/dark via html[data-theme]. */
    .workspace {
        --ws-canvas: #f0f2f5; --ws-card: #ffffff; --ws-soft: #f0f2f5; --ws-hover: #e4e6eb;
        --ws-ink: #050505; --ws-muted: #65676b; --ws-line: #dadde1;
        --ws-blue: #1877f2; --ws-blue-soft: #e7f3ff; --ws-green: #31a24c; --ws-amber: #b26b00; --ws-amber-soft: #fff4e0;
        --ws-red: #e41e3f; --ws-red-soft: #ffebee;
        --ws-shadow: 0 1px 2px rgba(0, 0, 0, .12);
        display: flex; flex-direction: column; flex: 1; width: 100%; min-width: 0; min-height: 0; height: 100%;
        overflow: hidden; border: 1px solid var(--border); border-radius: 14px; background: var(--ws-canvas); color: var(--ws-ink);
    }
    html[data-theme="dark"] .workspace {
        --ws-canvas: #18191a; --ws-card: #242526; --ws-soft: #3a3b3c; --ws-hover: #4e4f50;
        --ws-ink: #e4e6eb; --ws-muted: #b0b3b8; --ws-line: #3e4042;
        --ws-blue: #2d88ff; --ws-blue-soft: #263951; --ws-green: #45bd62; --ws-amber: #f7b928; --ws-amber-soft: #3d3420;
        --ws-red: #f02849; --ws-red-soft: #3c1f24; --ws-shadow: 0 1px 2px rgba(0, 0, 0, .3);
    }
    .chat-frame-page .workspace { border: 0; border-radius: 0; }
    .workspace .ws-icon { width: 20px; height: 20px; flex: none; fill: none; stroke: currentColor; stroke-width: 1.9; stroke-linecap: round; stroke-linejoin: round; }

    /* Top tabs, like Facebook's header icons with an underline */
    .workspace-tabs { display: grid; grid-template-columns: repeat(2, 1fr); flex: none; padding: 0 .4rem; border-bottom: 1px solid var(--ws-line); background: var(--ws-card); }
    .workspace-tabs button {
        position: relative; display: flex; align-items: center; justify-content: center; gap: .45rem;
        height: 48px; margin: 4px 2px; border: 0; border-radius: 8px; background: transparent; color: var(--ws-muted);
        font: inherit; font-size: .88rem; font-weight: 650; cursor: pointer; transition: background .15s ease, color .15s ease;
    }
    .workspace-tabs button:hover { background: var(--ws-soft); }
    .workspace-tabs button[aria-selected="true"] { color: var(--ws-blue); }
    .workspace-tabs button[aria-selected="true"]::after { content: ""; position: absolute; left: 8px; right: 8px; bottom: -5px; height: 3px; border-radius: 3px 3px 0 0; background: var(--ws-blue); }
    .workspace-tabs button:focus-visible { outline: 2px solid var(--ws-blue); outline-offset: -2px; }
    .ws-count { display: grid; place-items: center; min-width: 19px; height: 19px; padding: 0 5px; border-radius: 999px; background: var(--ws-red); color: #fff; font-size: .68rem; font-weight: 800; line-height: 1; }
    .ws-count[hidden] { display: none; }

    .workspace-panel { display: flex; flex: 1; min-width: 0; min-height: 0; }
    .workspace-panel[hidden] { display: none; }
    .workspace-panel > .messages-page { border: 0; border-radius: 0; }
    .ws-scroll { overflow-y: auto; overscroll-behavior: contain; }
    .ws-column { display: grid; align-content: start; gap: .75rem; width: 100%; max-width: 680px; margin: 0 auto; padding: .85rem .75rem 1.5rem; }

    /* Cards */
    .ws-card { border-radius: 10px; background: var(--ws-card); box-shadow: var(--ws-shadow); }
    .ws-card-head { display: flex; align-items: flex-start; justify-content: space-between; gap: .75rem; padding: .85rem 1rem .4rem; }
    .ws-card-head h2 { margin: 0; font-size: 1.1rem; font-weight: 750; }
    .ws-card-head p { margin: .15rem 0 0; color: var(--ws-muted); font-size: .8rem; }
    .ws-seg { display: inline-flex; flex: none; padding: 3px; border-radius: 999px; background: var(--ws-soft); }
    .ws-seg button { padding: .35rem .8rem; border: 0; border-radius: 999px; background: transparent; color: var(--ws-muted); font: inherit; font-size: .78rem; font-weight: 700; cursor: pointer; }
    .ws-seg button[aria-pressed="true"] { background: var(--ws-card); color: var(--ws-ink); box-shadow: var(--ws-shadow); }

    .ws-btn { display: inline-flex; align-items: center; justify-content: center; gap: .4rem; min-height: 36px; padding: 0 .95rem; border: 0; border-radius: 6px; background: var(--ws-soft); color: var(--ws-ink); font: inherit; font-size: .86rem; font-weight: 650; cursor: pointer; }
    .ws-btn:hover:not(:disabled) { background: var(--ws-hover); }
    .ws-btn.primary { background: var(--ws-blue); color: #fff; }
    .ws-btn.primary:hover:not(:disabled) { filter: brightness(1.08); background: var(--ws-blue); }
    .ws-btn:disabled { opacity: .55; cursor: default; }
    .workspace button:focus-visible, .workspace input:focus-visible, .workspace textarea:focus-visible, .workspace [tabindex]:focus-visible { outline: 2px solid var(--ws-blue); outline-offset: 2px; }

    .ws-status { min-height: 0; margin: 0; padding: 0 .25rem; color: var(--ws-muted); font-size: .76rem; }
    .ws-status:empty { display: none; }
    .ws-status.error { color: var(--ws-red); }
    .ws-loading, .ws-error, .ws-empty-inline { margin: 0; padding: 1rem; color: var(--ws-muted); font-size: .85rem; text-align: center; list-style: none; }
    .ws-error { color: var(--ws-red); }
    .ws-empty { display: grid; justify-items: center; gap: .3rem; padding: 2rem 1rem; color: var(--ws-muted); font-size: .85rem; text-align: center; list-style: none; }
    .ws-empty strong { color: var(--ws-ink); font-size: .95rem; }
    .ws-empty-icon { width: 40px; height: 40px; margin-bottom: .3rem; padding: 8px; border-radius: 50%; background: var(--ws-soft); fill: none; stroke: var(--ws-muted); stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }

    /* Avatars */
    .ws-avatar { position: relative; display: grid; place-items: center; flex: none; width: 40px; height: 40px; overflow: hidden; border-radius: 50%; background: var(--ws-blue-soft); color: var(--ws-blue); font-size: .8rem; font-weight: 750; }
    .ws-avatar img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
    .ws-avatar.sm { width: 32px; height: 32px; font-size: .7rem; }
    .ws-avatar.xs { width: 24px; height: 24px; font-size: .58rem; border: 2px solid var(--ws-card); }
    .ws-avatar.more { background: var(--ws-soft); color: var(--ws-muted); }
    .ws-avatar-stack { display: flex; }
    .ws-avatar-stack .ws-avatar + .ws-avatar { margin-left: -7px; }

    /* To-Do */
    .ws-todo-add { display: flex; align-items: flex-end; gap: .45rem; margin: .4rem 1rem .6rem; padding: .35rem .35rem .35rem .9rem; border-radius: 22px; background: var(--ws-soft); }
    .ws-todo-add textarea { flex: 1; min-height: 34px; max-height: 160px; padding: .45rem 0; border: 0; outline: 0; resize: none; background: transparent; color: var(--ws-ink); font: inherit; font-size: .9rem; line-height: 1.4; }
    .ws-todo-add .ws-btn { border-radius: 999px; }
    .ws-date-btn { position: relative; display: grid; place-items: center; width: 36px; height: 36px; border-radius: 50%; color: var(--ws-muted); cursor: pointer; }
    .ws-date-btn:hover { background: var(--ws-hover); }
    .ws-date-btn input, .ws-chip-date { position: absolute; inset: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; }
    .ws-date-btn input::-webkit-calendar-picker-indicator, .ws-chip-date::-webkit-calendar-picker-indicator { position: absolute; inset: 0; width: auto; height: auto; cursor: pointer; }
    .ws-todo-card .ws-status { padding: 0 1rem .4rem; }
    .ws-todo-list { margin: 0; padding: 0 .4rem .5rem; list-style: none; }
    .ws-todo { display: flex; align-items: flex-start; gap: .7rem; padding: .6rem .6rem; border-radius: 8px; transition: background .15s ease, opacity .35s ease, transform .35s ease; }
    .ws-todo:hover { background: var(--ws-soft); }
    .ws-todo.is-done { opacity: 0; transform: translateX(14px); }
    .ws-todo.is-flash { background: var(--ws-blue-soft); }
    .ws-check { display: grid; place-items: center; flex: none; width: 24px; height: 24px; margin-top: 1px; padding: 0; border: 2px solid var(--ws-muted); border-radius: 50%; background: transparent; color: transparent; cursor: pointer; transition: border-color .15s ease, background .15s ease, color .15s ease; }
    .ws-check .ws-icon { width: 14px; height: 14px; stroke-width: 3; }
    .ws-check:hover, .ws-todo.is-done .ws-check { border-color: var(--ws-green); color: var(--ws-green); }
    .ws-todo.is-done .ws-check { background: var(--ws-green); color: #fff; }
    .ws-todo-body { display: grid; gap: .3rem; flex: 1; min-width: 0; }
    .ws-todo-text { margin: 0; color: var(--ws-ink); font-size: .9rem; line-height: 1.45; white-space: pre-wrap; overflow-wrap: anywhere; cursor: text; border-radius: 4px; }
    .ws-todo-editor { width: 100%; min-height: 36px; padding: .35rem .5rem; border: 1px solid var(--ws-blue); border-radius: 6px; outline: 0; resize: none; background: var(--ws-card); color: var(--ws-ink); font: inherit; font-size: .9rem; line-height: 1.45; }
    .ws-todo-meta { display: flex; flex-wrap: wrap; gap: .35rem; }
    .ws-chip { position: relative; display: inline-flex; align-items: center; gap: .3rem; padding: .18rem .55rem; border-radius: 999px; background: var(--ws-soft); color: var(--ws-muted); font-size: .72rem; font-weight: 650; }
    label.ws-chip { cursor: pointer; }
    label.ws-chip:hover { background: var(--ws-hover); }
    .ws-chip .ws-icon { width: 13px; height: 13px; }
    .ws-chip.soon { background: var(--ws-amber-soft); color: var(--ws-amber); }
    .ws-chip.late { background: var(--ws-red-soft); color: var(--ws-red); }

    /* To-Do timeline: earliest → latest due date, today marked */
    .ws-timeline { margin: 0 1rem .6rem; padding: .6rem .75rem .45rem; border-radius: 8px; background: var(--ws-soft); }
    .ws-timeline[hidden] { display: none; }
    .ws-tl-label { margin: 0 0 .55rem; color: var(--ws-muted); font-size: .72rem; font-weight: 700; letter-spacing: .03em; text-transform: uppercase; }
    .ws-tl-track { position: relative; height: 14px; margin: 0 8px; }
    .ws-tl-track::before { content: ""; position: absolute; left: 0; right: 0; top: 6px; height: 2px; border-radius: 2px; background: var(--ws-line); }
    .ws-tl-today { position: absolute; top: -2px; width: 2px; height: 18px; margin-left: -1px; border-radius: 2px; background: var(--ws-blue); }
    .ws-tl-dot { position: absolute; top: 1px; width: 12px; height: 12px; margin-left: -6px; padding: 0; border: 2px solid var(--ws-card); border-radius: 50%; background: var(--ws-muted); cursor: pointer; transition: transform .15s ease; }
    .ws-tl-dot:hover { transform: scale(1.35); }
    .ws-tl-dot.soon { background: var(--ws-amber); }
    .ws-tl-dot.late { background: var(--ws-red); }
    .ws-tl-ends { display: flex; justify-content: space-between; margin-top: .3rem; color: var(--ws-muted); font-size: .7rem; }

    /* Tasks: composer */
    .ws-composer { padding: .75rem 1rem; }
    .ws-composer-row { display: flex; align-items: center; gap: .6rem; }
    .ws-composer-open { flex: 1; min-height: 40px; padding: 0 1rem; border: 0; border-radius: 999px; background: var(--ws-soft); color: var(--ws-muted); font: inherit; font-size: .92rem; text-align: left; cursor: pointer; }
    .ws-composer-open:hover { background: var(--ws-hover); }
    .ws-compose-form { display: grid; gap: .6rem; margin-top: .75rem; padding-top: .75rem; border-top: 1px solid var(--ws-line); }
    .ws-compose-form[hidden] { display: none; }
    .ws-compose-form h3 { margin: 0; font-size: 1rem; font-weight: 750; text-align: center; }
    .ws-compose-form input[type="text"], .ws-compose-form textarea, .ws-field-inline input {
        width: 100%; padding: .6rem .75rem; border: 1px solid var(--ws-line); border-radius: 8px; background: var(--ws-card); color: var(--ws-ink); font: inherit; font-size: .9rem;
    }
    .ws-compose-form input[type="text"] { font-size: 1rem; font-weight: 650; }
    .ws-compose-form textarea { min-height: 70px; max-height: 220px; resize: none; line-height: 1.45; }
    .ws-field-inline { display: flex; align-items: center; gap: .6rem; color: var(--ws-muted); font-size: .82rem; font-weight: 650; }
    .ws-field-inline input { width: auto; padding: .4rem .55rem; }
    .ws-fieldset { display: grid; gap: .4rem; margin: 0; padding: .6rem .7rem; border: 1px solid var(--ws-line); border-radius: 8px; }
    .ws-fieldset legend { padding: 0 .3rem; color: var(--ws-ink); font-size: .8rem; font-weight: 750; }
    .ws-radio { display: flex; align-items: flex-start; gap: .55rem; padding: .35rem .3rem; border-radius: 6px; cursor: pointer; }
    .ws-radio:hover { background: var(--ws-soft); }
    .ws-radio input { margin-top: .2rem; accent-color: var(--ws-blue); }
    .ws-radio span { display: grid; }
    .ws-radio strong { font-size: .86rem; }
    .ws-radio small { color: var(--ws-muted); font-size: .76rem; }
    .ws-check-line { display: flex; align-items: center; gap: .5rem; padding: .25rem .3rem; color: var(--ws-ink); font-size: .82rem; cursor: pointer; }
    .ws-check-line[hidden] { display: none; }
    .ws-check-line input, .ws-person input { accent-color: var(--ws-blue); }
    .ws-people { display: grid; gap: .15rem; max-height: 260px; overflow-y: auto; }
    .ws-person { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .3rem .8rem; padding: .35rem .3rem; border-radius: 6px; }
    .ws-person:hover { background: var(--ws-soft); }
    .ws-person.is-off .ws-person-name { color: var(--ws-muted); }
    .ws-person-pick { display: flex; align-items: center; gap: .5rem; min-width: 0; cursor: pointer; }
    .ws-person-name { overflow: hidden; font-size: .86rem; font-weight: 650; text-overflow: ellipsis; white-space: nowrap; }
    .ws-person-perms { display: flex; gap: .35rem; }
    .ws-perm { display: inline-flex; align-items: center; gap: .3rem; padding: .2rem .5rem; border-radius: 999px; background: var(--ws-soft); color: var(--ws-muted); font-size: .72rem; font-weight: 650; cursor: pointer; }
    .ws-person.is-off .ws-perm { opacity: .45; cursor: default; }
    .ws-compose-actions { display: flex; justify-content: flex-end; gap: .5rem; }

    /* Tasks: feed posts */
    .ws-feed { display: grid; gap: .75rem; }
    .ws-task { border-radius: 10px; background: var(--ws-card); box-shadow: var(--ws-shadow); overflow: hidden; }
    .ws-task.is-waiting { box-shadow: var(--ws-shadow), inset 3px 0 0 var(--ws-amber); }
    .ws-task.is-new { box-shadow: var(--ws-shadow), 0 0 0 2px var(--ws-blue); }
    .ws-new { flex: none; padding: .15rem .55rem; border-radius: 999px; background: var(--ws-blue); color: #fff; font-size: .7rem; font-weight: 800; letter-spacing: .02em; }
    .ws-task-head { display: flex; align-items: center; gap: .6rem; padding: .75rem 1rem 0; }
    .ws-task-who { display: grid; flex: 1; min-width: 0; }
    .ws-task-who strong { overflow: hidden; font-size: .9rem; text-overflow: ellipsis; white-space: nowrap; }
    .ws-task-when { display: flex; align-items: center; color: var(--ws-muted); font-size: .76rem; }
    .ws-audience { display: inline-flex; align-items: center; gap: .2rem; }
    .ws-audience .ws-icon { width: 13px; height: 13px; }
    .ws-task-tools { display: flex; gap: .15rem; }
    .ws-icon-btn { display: grid; place-items: center; width: 34px; height: 34px; padding: 0; border: 0; border-radius: 50%; background: transparent; color: var(--ws-muted); cursor: pointer; }
    .ws-icon-btn .ws-icon { width: 18px; height: 18px; }
    .ws-icon-btn:hover { background: var(--ws-soft); color: var(--ws-ink); }
    .ws-icon-btn.danger:hover { background: var(--ws-red-soft); color: var(--ws-red); }
    .ws-task-main { display: grid; gap: .5rem; padding: .6rem 1rem .7rem; }
    .ws-task-title { margin: 0; font-size: 1.02rem; font-weight: 750; line-height: 1.35; overflow-wrap: anywhere; }
    .ws-task-body { margin: 0; font-size: .92rem; line-height: 1.5; white-space: pre-wrap; overflow-wrap: anywhere; }
    .ws-task-tags { display: flex; flex-wrap: wrap; gap: .35rem; }
    .ws-pill { display: inline-flex; align-items: center; gap: .35rem; padding: .22rem .6rem; border-radius: 999px; font-size: .74rem; font-weight: 700; }
    .ws-pill .ws-icon { width: 14px; height: 14px; }
    .ws-pill.open { background: var(--ws-blue-soft); color: var(--ws-blue); }
    .ws-pill.wait { background: var(--ws-amber-soft); color: var(--ws-amber); }
    .ws-dot { width: 7px; height: 7px; border-radius: 50%; background: currentColor; }
    .ws-task-members { display: flex; align-items: center; gap: .5rem; color: var(--ws-muted); font-size: .78rem; }
    .ws-task-actions { display: flex; gap: .25rem; margin: 0 .75rem; padding: .25rem 0; border-top: 1px solid var(--ws-line); }
    .ws-action { display: inline-flex; flex: 1; align-items: center; justify-content: center; gap: .4rem; min-height: 36px; border: 0; border-radius: 6px; background: transparent; color: var(--ws-muted); font: inherit; font-size: .86rem; font-weight: 650; cursor: pointer; }
    .ws-action .ws-icon { width: 18px; height: 18px; }
    .ws-action:hover:not(:disabled) { background: var(--ws-soft); }
    .ws-action.primary { color: var(--ws-blue); }
    .ws-action:disabled { opacity: .5; cursor: default; }

    /* Comment thread */
    .ws-thread { display: grid; gap: .45rem; padding: .5rem 1rem .8rem; border-top: 1px solid var(--ws-line); }
    .ws-comment { display: flex; align-items: flex-start; gap: .45rem; }
    .ws-comment-wrap { display: grid; justify-items: start; min-width: 0; }
    .ws-bubble { max-width: 100%; padding: .45rem .75rem; border-radius: 18px; background: var(--ws-soft); }
    .ws-bubble strong { display: block; font-size: .8rem; }
    .ws-bubble p { margin: .05rem 0 0; font-size: .88rem; line-height: 1.4; white-space: pre-wrap; overflow-wrap: anywhere; }
    .ws-comment-time { padding: .1rem .75rem 0; color: var(--ws-muted); font-size: .7rem; font-weight: 650; }
    .ws-event { margin: 0; padding-left: 2.4rem; color: var(--ws-muted); font-size: .76rem; font-style: italic; }
    .ws-comment-form { display: flex; align-items: flex-end; gap: .45rem; margin-top: .2rem; }
    .ws-comment-input { flex: 1; min-height: 36px; max-height: 140px; padding: .5rem .9rem; border: 0; border-radius: 18px; outline: 0; resize: none; background: var(--ws-soft); color: var(--ws-ink); font: inherit; font-size: .88rem; line-height: 1.35; }
    .ws-send { display: grid; place-items: center; width: 36px; height: 36px; padding: 0; border: 0; border-radius: 50%; background: transparent; color: var(--ws-blue); cursor: pointer; }
    .ws-send:hover:not(:disabled) { background: var(--ws-soft); }
    .ws-send .ws-icon { width: 19px; height: 19px; }

    /* Reactions summary line and Like button (Facebook colours per reaction) */
    .ws-task.is-flash { box-shadow: var(--ws-shadow), 0 0 0 3px var(--ws-amber); }
    .ws-task-stats { display: flex; align-items: center; justify-content: space-between; gap: .5rem; margin: 0 1rem; padding: .1rem 0 .45rem; color: var(--ws-muted); font-size: .82rem; }
    .ws-link { padding: 0; border: 0; background: none; color: var(--ws-muted); font: inherit; cursor: pointer; }
    .ws-link:hover { text-decoration: underline; }
    .ws-react-summary { display: inline-flex; align-items: center; gap: .3rem; cursor: default; }
    .ws-react-icons { display: inline-flex; }
    .ws-react-icons .ws-react-emoji { display: grid; place-items: center; width: 20px; height: 20px; margin-left: -4px; border: 2px solid var(--ws-card); border-radius: 50%; background: var(--ws-card); font-size: .78rem; line-height: 1; }
    .ws-react-icons .ws-react-emoji:first-child { margin-left: 0; }
    .ws-react-count { font-size: .8rem; }
    .ws-react-emoji.big { font-size: 1.05rem; line-height: 1; }
    .ws-react-wrap { position: relative; display: inline-flex; flex: 1; }
    .ws-react-wrap > .ws-action { flex: 1; }
    .ws-react-wrap.compact { flex: none; }
    .ws-action.reacted.like, .ws-meta-btn.reacted.like { color: #1877f2; }
    .ws-action.reacted.love, .ws-meta-btn.reacted.love { color: #f33e58; }
    .ws-action.reacted.haha, .ws-action.reacted.wow, .ws-action.reacted.sad,
    .ws-meta-btn.reacted.haha, .ws-meta-btn.reacted.wow, .ws-meta-btn.reacted.sad { color: #e3a008; }
    .ws-action.reacted.angry, .ws-meta-btn.reacted.angry { color: #e9710f; }
    html[data-theme="dark"] .ws-action.reacted.like, html[data-theme="dark"] .ws-meta-btn.reacted.like { color: #4599ff; }
    .ws-react-picker {
        position: absolute; left: 0; bottom: calc(100% + 6px); z-index: 20; display: flex; gap: .15rem; padding: .3rem .4rem;
        border-radius: 999px; background: var(--ws-card); box-shadow: 0 6px 22px rgba(0, 0, 0, .22), 0 0 0 1px var(--ws-line);
        opacity: 0; transform: translateY(6px) scale(.9); transform-origin: bottom left; pointer-events: none; transition: opacity .15s ease, transform .18s ease;
    }
    .ws-react-wrap.picking .ws-react-picker { opacity: 1; transform: none; pointer-events: auto; }
    .ws-react-option { display: grid; place-items: center; width: 38px; height: 38px; padding: 0; border: 0; border-radius: 50%; background: transparent; font-size: 1.55rem; line-height: 1; cursor: pointer; transition: transform .15s cubic-bezier(.3, 1.6, .5, 1); }
    .ws-react-option:hover, .ws-react-option:focus-visible { transform: translateY(-6px) scale(1.3); outline: none; }
    .ws-react-option.on { background: var(--ws-soft); }

    /* Comments: Facebook meta line under the bubble, replies indented */
    .ws-comment-block { display: grid; gap: .35rem; }
    .ws-bubble { position: relative; }
    .ws-react-summary.on-bubble { position: absolute; right: -10px; bottom: -10px; padding: 1px 5px 1px 2px; border-radius: 999px; background: var(--ws-card); box-shadow: 0 1px 4px rgba(0, 0, 0, .2); font-size: .72rem; }
    .ws-react-summary.on-bubble .ws-react-emoji { width: 17px; height: 17px; font-size: .68rem; }
    .ws-comment-meta { display: flex; align-items: center; gap: .75rem; padding: .2rem .75rem 0; }
    .ws-meta-btn { padding: 0; border: 0; background: none; color: var(--ws-muted); font: inherit; font-size: .74rem; font-weight: 750; cursor: pointer; }
    .ws-meta-btn:hover { text-decoration: underline; }
    .ws-comment-meta .ws-comment-time { padding: 0; }
    .ws-comment-meta .ws-react-picker { bottom: calc(100% + 4px); }
    .ws-comment-meta .ws-react-option { width: 32px; height: 32px; font-size: 1.3rem; }
    .ws-replies { display: grid; gap: .35rem; margin-left: 2.6rem; padding-left: .7rem; border-left: 2px solid var(--ws-line); }
    .ws-comment.reply .ws-bubble { padding: .35rem .65rem; }
    .ws-comment-form.reply .ws-comment-input { min-height: 32px; padding: .4rem .8rem; font-size: .84rem; }

    /* Due soon (critical) + calendar side cards */
    .ws-side-card { padding: .85rem 1rem; }
    .ws-side-head { display: flex; align-items: center; gap: .45rem; margin-bottom: .5rem; color: var(--ws-amber); }
    .ws-side-head h2 { margin: 0; color: var(--ws-ink); font-size: .98rem; font-weight: 750; }
    .ws-side-card.is-critical { box-shadow: var(--ws-shadow), inset 0 3px 0 var(--ws-red); }
    .ws-side-card.is-critical .ws-side-head { color: var(--ws-red); }
    .ws-side-empty { margin: 0; color: var(--ws-muted); font-size: .82rem; }
    .ws-due-list { display: grid; gap: .35rem; margin: 0; padding: 0; list-style: none; }
    .ws-due-item { display: grid; gap: .1rem; width: 100%; padding: .5rem .65rem; border: 0; border-left: 4px solid var(--ws-line); border-radius: 8px; background: var(--ws-soft); color: var(--ws-ink); font: inherit; text-align: left; cursor: pointer; }
    .ws-due-item:hover { background: var(--ws-hover); }
    .ws-due-item.overdue, .ws-due-item.today { border-left-color: var(--ws-red); background: var(--ws-red-soft); }
    .ws-due-item.soon { border-left-color: var(--ws-amber); background: var(--ws-amber-soft); }
    .ws-due-item.later { border-left-color: var(--ws-blue); }
    .ws-due-item.waiting { border-left-color: var(--ws-green); }
    .ws-due-when { font-size: .72rem; font-weight: 800; letter-spacing: .02em; text-transform: uppercase; }
    .ws-due-item.overdue .ws-due-when, .ws-due-item.today .ws-due-when { color: var(--ws-red); }
    .ws-due-item.soon .ws-due-when { color: var(--ws-amber); }
    .ws-due-title { overflow: hidden; font-size: .86rem; font-weight: 650; text-overflow: ellipsis; white-space: nowrap; }

    .ws-cal-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: .4rem; }
    .ws-cal-head h2 { margin: 0; font-size: .95rem; font-weight: 750; }
    .ws-cal-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 2px; }
    .ws-cal-dow { padding: .2rem 0; color: var(--ws-muted); font-size: .68rem; font-weight: 750; text-align: center; }
    .ws-cal-day { position: relative; display: grid; justify-items: center; align-content: start; gap: 2px; min-height: 38px; padding: .25rem 0; border: 0; border-radius: 8px; background: transparent; color: var(--ws-ink); font: inherit; cursor: pointer; }
    .ws-cal-day:hover { background: var(--ws-soft); }
    .ws-cal-num { font-size: .78rem; font-weight: 600; }
    .ws-cal-day.today .ws-cal-num { display: grid; place-items: center; width: 22px; height: 22px; border-radius: 50%; background: var(--ws-blue); color: #fff; }
    .ws-cal-day.has-tasks { background: var(--ws-soft); }
    .ws-cal-day.picked { box-shadow: inset 0 0 0 2px var(--ws-blue); }
    .ws-cal-dots { display: flex; gap: 2px; }
    .ws-cal-dot { display: inline-block; width: 6px; height: 6px; border-radius: 50%; background: var(--ws-blue); }
    .ws-cal-dot.overdue, .ws-cal-dot.today { background: var(--ws-red); }
    .ws-cal-dot.soon { background: var(--ws-amber); }
    .ws-cal-dot.waiting { background: var(--ws-green); }
    .ws-cal-legend { display: flex; flex-wrap: wrap; gap: .3rem .75rem; margin-top: .55rem; color: var(--ws-muted); font-size: .7rem; }
    .ws-cal-legend span { display: inline-flex; align-items: center; gap: .3rem; }
    .ws-cal-picked { display: grid; gap: .35rem; margin-top: .65rem; padding-top: .6rem; border-top: 1px solid var(--ws-line); }
    .ws-cal-picked-date { margin: 0; font-size: .8rem; font-weight: 750; }

    @media (max-width: 480px) {
        .workspace-tabs button span:not(.ws-count) { font-size: .8rem; }
        .ws-column { padding: .6rem .5rem 1.2rem; }
        .ws-person-perms { width: 100%; padding-left: 2.6rem; }
    }
    @media (prefers-reduced-motion: reduce) {
        .ws-todo, .ws-tl-dot, .workspace-tabs button { transition: none; }
    }
</style>
