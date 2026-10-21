<style>
    .assistant-page { display:flex; width:100%; min-height:0; flex:1; justify-content:center; padding:clamp(.75rem, 2vw, 1.5rem); color:var(--text); }
    .assistant-card { display:flex; width:min(760px, 100%); min-height:0; max-height:100%; flex-direction:column; overflow:hidden; border:1px solid var(--border); border-radius:16px; background:var(--surface); box-shadow:0 10px 30px rgba(15,23,42,.08); }
    .assistant-head { display:flex; align-items:center; justify-content:space-between; gap:1rem; padding:1rem 1.15rem; border-bottom:1px solid var(--border); }
    .assistant-title { display:flex; min-width:0; align-items:center; gap:.8rem; }
    .assistant-mark { display:grid; width:42px; height:42px; flex:none; place-items:center; overflow:hidden; border:1px solid #000; border-radius:50%; background:#000; }
    .assistant-mark img { width:100%; height:100%; padding:5px; object-fit:contain; }
    .assistant-title h1 { margin:0; font-size:1rem; }
    .assistant-title p { margin:.2rem 0 0; color:var(--text-muted); font-size:.78rem; }
    .assistant-head-actions { display:flex; flex:none; align-items:center; gap:.5rem; }
    .assistant-link,.assistant-clear,.assistant-back { display:inline-flex; min-height:36px; align-items:center; justify-content:center; padding:.45rem .65rem; border:1px solid var(--border); border-radius:8px; background:var(--surface); color:var(--text); font:inherit; font-size:.78rem; font-weight:600; line-height:1.2; text-decoration:none; cursor:pointer; }
    .assistant-link:hover,.assistant-clear:hover,.assistant-back:hover { border-color:var(--accent); color:var(--accent); }
    .assistant-disclaimer { margin:.8rem 1rem 0; padding:.7rem .8rem; border:1px solid var(--border); border-left:3px solid var(--accent); border-radius:8px; background:var(--bg); color:var(--text-muted); font-size:.75rem; line-height:1.5; }
    .assistant-disclaimer strong { color:var(--text); }
    .assistant-feed { display:flex; min-height:190px; flex:1; flex-direction:column; gap:.8rem; overflow:auto; padding:1rem; overscroll-behavior:contain; }
    .assistant-welcome { max-width:34rem; margin:auto; padding:1rem; color:var(--text-muted); text-align:center; line-height:1.55; }
    .assistant-welcome strong { display:block; margin-bottom:.35rem; color:var(--text); font-size:1.05rem; }
    .assistant-message { max-width:min(86%, 590px); padding:.75rem .9rem; border:1px solid var(--border); border-radius:14px 14px 14px 4px; background:var(--bg); overflow-wrap:anywhere; white-space:pre-wrap; line-height:1.55; }
    .assistant-message.user { align-self:flex-end; border-color:var(--accent); border-radius:14px 14px 4px 14px; background:var(--accent); color:#fff; }
    .assistant-answer-link { display:inline-flex; margin-top:.45rem; color:var(--accent); font-weight:700; text-decoration:underline; text-underline-offset:2px; }
    .assistant-error { margin:0 1rem; padding:.6rem .75rem; border-radius:8px; background:color-mix(in srgb,var(--danger) 9%,var(--surface)); color:var(--danger); font-size:.82rem; line-height:1.45; }
    .assistant-error[hidden] { display:none; }
    .assistant-compose { display:flex; align-items:flex-end; gap:.6rem; padding:.8rem; border-top:1px solid var(--border); }
    .assistant-compose textarea { min-height:44px; max-height:130px; flex:1; resize:vertical; padding:.72rem .8rem; border:1px solid var(--border); border-radius:9px; background:var(--bg); color:var(--text); font:inherit; font-size:.9rem; }
    .assistant-compose button { min-height:44px; padding:.65rem .9rem; border:0; border-radius:9px; background:var(--accent); color:#fff; font:inherit; font-size:.86rem; font-weight:600; line-height:1; cursor:pointer; }
    .assistant-compose button:disabled { opacity:.55; cursor:wait; }
    .assistant-compose textarea:focus-visible,.assistant-page button:focus-visible,.assistant-page a:focus-visible { outline:3px solid color-mix(in srgb,var(--accent) 35%,transparent); outline-offset:2px; }
    .assistant-config-note { margin:auto; padding:1.2rem; color:var(--text-muted); font-size:.86rem; line-height:1.55; text-align:center; }
    .assistant-config-note strong { color:var(--text); }
    .assistant-page[hidden] { display:none!important; }
    .chat-frame-page .assistant-page { padding:.5rem; }
    .chat-frame-page .assistant-card { border:0; border-radius:0; box-shadow:none; }
    .chat-frame-page .assistant-head { padding:.75rem; }
    .chat-frame-page .assistant-disclaimer { margin:.5rem .65rem 0; }
    .chat-frame-page .assistant-feed { padding:.75rem; }
    .messages-main.assistant-active > :not(#builtInHelpChatbot) { display:none; }
    .messages-main.assistant-active #builtInHelpChatbot { position:absolute; inset:0; padding:.5rem; }
    .messages-main.assistant-active .assistant-card { width:100%; border:0; border-radius:0; box-shadow:none; }
    .messages-main.assistant-active .assistant-head { padding:.7rem .85rem; }
    .messages-main.assistant-active .assistant-disclaimer { margin:.6rem .8rem 0; }
    .messages-main.assistant-active .assistant-feed { padding:.85rem; }
    .messages-main.assistant-active .assistant-compose { padding:.7rem; }
    @media (max-width:520px) {
        .assistant-page { padding:.5rem; }
        .assistant-card { border-radius:12px; }
        .assistant-head { flex-wrap:wrap; align-items:flex-start; padding:.75rem; }
        .assistant-title { flex:1 1 100%; gap:.55rem; }
        .assistant-title h1 { font-size:1.05rem; }
        .assistant-title p { font-size:.78rem; }
        .assistant-head-actions { width:100%; justify-content:flex-end; }
        .assistant-mark { width:36px; height:36px; }
        .assistant-link,.assistant-clear,.assistant-back { min-height:34px; padding:.4rem .5rem; font-size:.73rem; }
        .assistant-compose { gap:.45rem; padding:.65rem; }
        .assistant-compose button { padding:.6rem .7rem; }
        .assistant-message { max-width:94%; }
    }
    @media (prefers-reduced-motion:reduce) { .assistant-page * { scroll-behavior:auto!important; } }
</style>
