<style>
    /* LSPBot uses the same visual language as the regular direct messages
       (heading, feed, bubbles, typing dots and compose bar). */
    .assistant-page { display:flex; width:100%; min-height:0; flex:1; justify-content:center; padding:clamp(.75rem, 2vw, 1.5rem); color:var(--text); }
    .assistant-card { display:flex; width:min(760px, 100%); min-height:0; max-height:100%; flex-direction:column; overflow:hidden; border:1px solid var(--border); border-radius:14px; background:var(--surface); }

    /* Heading */
    .assistant-head { display:flex; align-items:center; gap:.7rem; min-height:68px; padding:.85rem 1.2rem; border-bottom:1px solid var(--border); }
    .assistant-title { display:flex; min-width:0; flex:1; align-items:center; gap:.7rem; }
    .assistant-mark { display:grid; width:40px; height:40px; flex:none; place-items:center; overflow:hidden; border:1px solid #000; border-radius:50%; background:#000; }
    .assistant-mark img { width:100%; height:100%; padding:5px; object-fit:contain; }
    .assistant-title h1 { margin:0; font-size:1rem; }
    .assistant-title p { margin:.2rem 0 0; color:var(--text-muted); font-size:.82rem; }

    /* Round back arrow. Embedded: mobile only. */
    .assistant-link,.assistant-back { display:grid; flex:none; width:36px; height:36px; place-items:center; padding:0; border:1px solid var(--border); border-radius:50%; background:var(--surface); color:var(--text); font:inherit; font-size:1.2rem; line-height:1; text-decoration:none; cursor:pointer; }
    .assistant-link:hover,.assistant-back:hover { border-color:var(--accent); color:var(--accent); }
    .assistant-back { display:none; }

    /* Feed + bubbles */
    .assistant-feed { display:flex; min-height:190px; flex:1; flex-direction:column; gap:.65rem; overflow-y:auto; padding:1.2rem; background:var(--bg); overscroll-behavior:contain; }
    .assistant-message { max-width:min(78%, 550px); padding:.72rem .9rem; border:1px solid var(--border); border-radius:14px 14px 14px 4px; background:var(--surface); color:var(--text); overflow-wrap:anywhere; white-space:pre-wrap; }
    .assistant-message.user { align-self:flex-end; border-color:var(--accent); border-radius:14px 14px 4px 14px; background:var(--accent); color:#fff; }
    /* Dark mode: the accent is light, so the text on it must be dark (this was the invisible message). */
    html[data-theme="dark"] .assistant-message.user { color:#000; }
    .assistant-answer-link { display:inline-flex; margin-top:.45rem; color:var(--accent); font-weight:700; text-decoration:underline; text-underline-offset:2px; }
    .assistant-message.user .assistant-answer-link { color:inherit; }

    /* Typing dots */
    .assistant-typing { display:flex; align-items:center; align-self:flex-start; gap:4px; min-height:38px; padding:.65rem .8rem; border:1px solid var(--border); border-radius:15px 15px 15px 4px; background:var(--surface); }
    .assistant-typing span { width:6px; height:6px; border-radius:50%; background:var(--text-muted); animation:assistant-typing-bounce 1s ease-in-out infinite; }
    .assistant-typing span:nth-child(2) { animation-delay:.12s; }
    .assistant-typing span:nth-child(3) { animation-delay:.24s; }
    @keyframes assistant-typing-bounce { 0%,60%,100% { transform:translateY(0); opacity:.55; } 30% { transform:translateY(-4px); opacity:1; } }

    .assistant-error { margin:0; padding:.6rem 1rem; color:var(--danger); font-size:.85rem; line-height:1.45; }
    .assistant-error[hidden] { display:none; }

    /* Compose bar */
    .assistant-compose { display:flex; align-items:center; gap:.4rem; padding:.85rem; border-top:1px solid var(--border); }
    .assistant-compose textarea { min-height:44px; max-height:140px; flex:1; resize:vertical; padding:.72rem .85rem; border:1px solid var(--border); border-radius:9px; background:var(--bg); color:var(--text); font:inherit; }

    /* Send: just a ">" with no box. Follows the theme text color. */
    .assistant-compose button { display:grid; width:44px; height:44px; flex:none; place-items:center; padding:0; border:0; border-radius:50%; background:transparent; color:var(--text); font:inherit; cursor:pointer; transition:color .15s ease, transform .15s ease, opacity .15s ease; }
    .assistant-compose button svg { display:block; }
    .assistant-compose button:hover:not(:disabled) { color:var(--accent); }
    .assistant-compose button:active:not(:disabled) { transform:scale(.88); }
    .assistant-compose button:disabled { opacity:.4; cursor:wait; }

    /* Softer focus ring (the old one looked like a thick double border) */
    .assistant-compose textarea:focus-visible { outline:none; border-color:var(--accent); box-shadow:0 0 0 2px color-mix(in srgb,var(--accent) 30%,transparent); }
    .assistant-page button:focus-visible,.assistant-page a:focus-visible { outline:2px solid color-mix(in srgb,var(--accent) 45%,transparent); outline-offset:2px; }

    .assistant-config-note { margin:auto; padding:1.2rem; color:var(--text-muted); font-size:.86rem; line-height:1.55; text-align:center; }
    .assistant-config-note strong { color:var(--text); }
    .assistant-page[hidden] { display:none!important; }

    /* Widget (iframe) mode */
    .chat-frame-page .assistant-page { padding:0; }
    .chat-frame-page .assistant-card { border:0; border-radius:0; }
    .chat-frame-page .assistant-head { min-height:58px; padding:.55rem .8rem; }
    .chat-frame-page .assistant-feed { padding:.85rem; }
    .chat-frame-page .assistant-compose { gap:.3rem; padding:.6rem; }
    .chat-frame-page .assistant-compose textarea { min-height:42px; max-height:90px; resize:none; padding:.6rem .7rem; font-size:.88rem; }
    .chat-frame-page .assistant-compose button { width:42px; height:42px; }

    /* Embedded in Messages */
    .messages-main.assistant-active > :not(#builtInHelpChatbot) { display:none; }
    .messages-main.assistant-active #builtInHelpChatbot { position:absolute; inset:0; padding:0; }
    .messages-main.assistant-active .assistant-card { width:100%; max-height:none; border:0; border-radius:0; }

    @media (max-width:760px) {
        .assistant-back { display:grid; }
        .assistant-message { max-width:88%; }
    }
    @media (prefers-reduced-motion:reduce) {
        .assistant-page * { scroll-behavior:auto!important; }
        .assistant-typing span { animation:none; }
    }
</style>