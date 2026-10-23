@extends(request()->boolean('widget') ? 'layouts.chat' : 'layouts.app')

@section('title', 'Messages')

@push('styles')
@include('messages.partials.assistant-styles')
<style>
    .messages-page { display:grid; grid-template-columns:minmax(230px, 300px) minmax(0, 1fr); width:100%; height:100%; min-height:0; flex:1; background:var(--surface); border:1px solid var(--border); border-radius:14px; overflow:hidden; }
    .messages-sidebar { display:flex; flex-direction:column; min-width:0; min-height:0; overflow:hidden; border-right:1px solid var(--border); }
    .messages-sidebar-head { padding:1rem; border-bottom:1px solid var(--border); }
    .messages-sidebar-head h2 { margin:0 0 .8rem; font-size:1.05rem; }
    .messages-search { width:100%; padding:.68rem .8rem; border:1px solid var(--border); border-radius:8px; background:var(--bg); color:var(--text); font:inherit; }
    .messages-list { min-height:0; overflow-y:auto; flex:1; overscroll-behavior:contain; }
    .account-search-results { max-height:38%; overflow-y:auto; flex:none; overscroll-behavior:contain; border-bottom:1px solid var(--border); }
    .account-search-label { margin:0; padding:.65rem 1rem .35rem; color:var(--text-muted); font-size:.72rem; font-weight:700; letter-spacing:.06em; text-transform:uppercase; }
    .message-person { display:flex; gap:.75rem; align-items:center; width:100%; padding:.85rem 1rem; color:var(--text); background:transparent; border:0; border-bottom:1px solid var(--border); text-align:left; cursor:pointer; }
    .message-person:hover,.message-person.selected { background:color-mix(in srgb,var(--accent) 8%,var(--surface)); }
    .message-avatar { position:relative; display:grid; place-items:center; width:40px; height:40px; flex:none; overflow:hidden; border-radius:50%; background:color-mix(in srgb,var(--accent) 15%,var(--surface)); color:var(--accent); font-weight:700; }
    .message-avatar img { position:absolute; inset:0; width:100%; height:100%; border-radius:inherit; object-fit:cover; }
    .message-person-body { min-width:0; flex:1; }
    .message-person-name,.message-person-preview { display:block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .message-person-name { font-weight:650; }
    .message-person-preview { margin-top:.2rem; color:var(--text-muted); font-size:.82rem; }
    .message-person.bot-contact { border-bottom-color:color-mix(in srgb,var(--accent) 30%,var(--border)); }
    .message-person.bot-contact .message-avatar { border:1px solid #000; background:#000; }
    .message-person.bot-contact .message-avatar img { padding:5px; object-fit:contain; }
    .assistant-contact-badge { flex:none; padding:.2rem .38rem; border-radius:999px; background:color-mix(in srgb,var(--accent) 12%,var(--surface)); color:var(--accent); font-size:.62rem; font-weight:800; letter-spacing:.04em; }
    .message-person.suspended { opacity:.72; cursor:not-allowed; }
    .message-person.suspended:hover { background:transparent; }
    .message-person.suspended .message-person-preview { white-space:normal; }
    .account-status { display:inline-block; margin-top:.25rem; color:var(--danger); font-size:.76rem; font-weight:650; }
    .messages-main { position:relative; display:flex; flex-direction:column; min-width:0; min-height:0; overflow:hidden; }
    .messages-heading { display:flex; align-items:center; gap:.7rem; min-height:68px; padding:.85rem 1.2rem; border-bottom:1px solid var(--border); }
    .messages-heading h2 { margin:0; font-size:1rem; }
    .messages-heading p { margin:.2rem 0 0; color:var(--text-muted); font-size:.82rem; }
    .messages-feed { display:flex; flex:1; flex-direction:column; min-height:0; gap:.65rem; overflow-y:auto; overscroll-behavior:contain; padding:1.2rem; background:var(--bg); }
    .messages-empty { margin:auto; max-width:30rem; padding:2rem; color:var(--text-muted); text-align:center; line-height:1.6; }
    .message-row { display:flex; }
    .message-row.mine { justify-content:flex-end; }
    .message-bubble { max-width:min(78%, 550px); padding:.72rem .9rem; border:1px solid var(--border); border-radius:14px 14px 14px 4px; background:var(--surface); overflow-wrap:anywhere; }
    .message-row.mine .message-bubble { border-color:var(--accent); border-radius:14px 14px 4px 14px; background:var(--accent); color:#fff; }
    .message-text { margin:0; white-space:pre-wrap; }
    .message-image { display:block; max-width:min(360px, 100%); max-height:300px; margin-top:.55rem; border-radius:8px; object-fit:contain; }
    .message-meta { margin-top:.35rem; color:var(--text-muted); font-size:.7rem; text-align:right; }
    .message-row.mine .message-meta { color:rgba(255,255,255,.8); }
    .message-progress { width:100%; height:5px; margin-top:.6rem; overflow:hidden; border-radius:99px; background:rgba(100,116,139,.22); }
    .message-progress-bar { width:0; height:100%; background:#80c5ff; transition:width .12s linear; }
    .message-row.incoming .message-progress { background:var(--border); }
    .message-row.incoming .message-progress-bar { background:var(--accent); }
    .typing-row { display:flex; align-items:flex-end; gap:.5rem; align-self:flex-start; }
    .typing-row[hidden] { display:none; }
    .typing-avatar { width:28px; height:28px; font-size:.68rem; }
    .typing-bubble { display:flex; align-items:center; gap:4px; min-height:38px; padding:.65rem .8rem; border:1px solid var(--border); border-radius:15px 15px 15px 4px; background:var(--surface); }
    .typing-bubble span { width:6px; height:6px; border-radius:50%; background:var(--text-muted); animation:typing-bounce 1s ease-in-out infinite; }
    .typing-bubble span:nth-child(2) { animation-delay:.12s; }
    .typing-bubble span:nth-child(3) { animation-delay:.24s; }
    @keyframes typing-bounce { 0%,60%,100% { transform:translateY(0); opacity:.55; } 30% { transform:translateY(-4px); opacity:1; } }
    .typing-label { margin-left:.25rem; color:var(--text-muted); font-size:.75rem; }
    .messages-status { padding:.45rem 1.2rem; border-bottom:1px solid var(--border); color:var(--text-muted); font-size:.76rem; }
    .messages-status.offline { color:#b54708; background:#fffaeb; }
    .messages-notice { position:absolute; z-index:5; top:78px; left:50%; max-width:calc(100% - 2rem); padding:.65rem 1rem; transform:translateX(-50%); border:1px solid var(--border); border-radius:999px; background:var(--surface); box-shadow:0 8px 28px rgba(15,23,42,.18); color:var(--text); font-size:.85rem; }
    .messages-notice[hidden] { display:none; }
    .messages-image-preview { display:none; align-items:center; gap:.7rem; padding:.55rem .9rem; border-top:1px solid var(--border); background:var(--surface); }
    .messages-image-preview.visible { display:flex; }
    .messages-image-preview img { width:48px; height:48px; border-radius:7px; object-fit:cover; }
    .messages-image-preview span { flex:1; overflow:hidden; color:var(--text-muted); font-size:.82rem; text-overflow:ellipsis; white-space:nowrap; }
    .messages-image-preview button { border:0; background:transparent; color:var(--danger); cursor:pointer; font-size:1.2rem; }
    .messages-compose { display:flex; align-items:flex-end; gap:.6rem; padding:.85rem; border-top:1px solid var(--border); }
    .messages-compose textarea { flex:1; min-height:44px; max-height:140px; resize:vertical; padding:.72rem .85rem; border:1px solid var(--border); border-radius:9px; background:var(--bg); color:var(--text); font:inherit; }
    .messages-compose button,.messages-start { border:0; border-radius:9px; padding:.75rem 1rem; background:var(--accent); color:#fff; font-weight:650; cursor:pointer; }
    .messages-compose button:disabled { opacity:.5; cursor:wait; }
    .messages-attach { width:42px; height:42px; padding:.6rem!important; background:var(--bg)!important; border:1px solid var(--border)!important; color:var(--text)!important; }
    .messages-alert { padding:.6rem 1rem; color:var(--danger); font-size:.85rem; }
    .messages-sidebar-alert { padding:.65rem .8rem; border-bottom:1px solid var(--border); background:color-mix(in srgb,var(--danger) 9%,var(--surface)); }
    .messages-count { display:grid; min-width:20px; height:20px; place-items:center; padding:0 .35rem; border-radius:999px; background:#e02424; color:#fff; text-align:center; font-size:.7rem; font-weight:750; line-height:1; }
    .messages-back { display:none; flex:none; width:36px; height:36px; place-items:center; border:1px solid var(--border); border-radius:50%; background:var(--surface); color:var(--text); cursor:pointer; font-size:1.2rem; }
    .chat-frame-page .messages-page { border:0; border-radius:0; }
    .chat-frame-page .messages-sidebar-head { padding:.8rem; }
    .chat-frame-page .messages-sidebar-head h2 { display:none; }
    .chat-frame-page .messages-search { padding:.6rem .7rem; font-size:.88rem; }
    .chat-frame-page .message-person { gap:.55rem; padding:.65rem .75rem; }
    .chat-frame-page .message-avatar { width:34px; height:34px; }
    .chat-frame-page .messages-heading { min-height:58px; padding:.55rem .8rem; }
    .chat-frame-page .messages-feed { padding:.85rem; }
    .chat-frame-page .messages-compose { gap:.45rem; padding:.6rem; }
    .chat-frame-page .messages-compose textarea { min-height:42px; max-height:90px; resize:none; padding:.6rem .7rem; font-size:.88rem; }
    .chat-frame-page .messages-compose button { padding:.65rem .8rem; }
    .chat-frame-page .messages-attach { width:40px; height:40px; }
    @media(max-width:760px) {
        .messages-page { grid-template-columns:1fr; grid-template-rows:minmax(0, 1fr); }
        .messages-page:not(.conversation-open) .messages-main,
        .messages-page.conversation-open .messages-sidebar { display:none; }
        .messages-page:not(.conversation-open) .messages-sidebar { border:0; }
        .messages-page.conversation-open .messages-main { min-height:0; }
        .messages-back { display:grid; }
        .message-bubble { max-width:88%; }
    }
    /* Sent bubble: readable text on the accent background */
.message-row.mine .message-bubble { background:var(--accent); border-color:var(--accent); color:var(--on-accent); }
.message-row.mine .message-text,
.message-row.mine .message-meta { color:var(--on-accent); }
.message-row.mine .message-meta { opacity:.65; }

/* Send button: ">" icon, no box */
#sendButton { display:grid; place-items:center; width:44px; height:44px; padding:0; border:0; border-radius:50%; background:transparent; color:var(--text); font-size:0; cursor:pointer; transition:color .15s ease, transform .15s ease; }
#sendButton::after { content:"›"; font-size:2rem; line-height:1; }
#sendButton:hover:not(:disabled) { color:var(--text-muted); }
#sendButton:active:not(:disabled) { transform:scale(.88); }
#sendButton:disabled { opacity:.35; cursor:default; }

/* Soften input focus ring */
textarea:focus-visible, input:focus-visible { outline:none; border-color:var(--text-muted); }
</style>
@endpush

@section('content')
<section class="messages-page" id="messagesApp"
    data-user-id="{{ $authenticatedUser['id'] }}"
    data-bot-avatar="{{ asset('images/LSP_logo_white.png') }}?v={{ filemtime(public_path('images/LSP_logo_white.png')) }}"
    data-accounts-url="{{ route('messages.accounts') }}"
    data-conversations-url="{{ route('messages.conversations') }}"
    data-create-url="{{ route('messages.conversations.create') }}"
    data-messages-url="{{ route('messages.list', ['conversation' => '__ID__']) }}"
    data-send-url="{{ route('messages.send', ['conversation' => '__ID__']) }}"
    data-typing-url="{{ route('messages.typing', ['conversation' => '__ID__']) }}"
    data-delivered-url="{{ route('messages.delivered', ['message' => '__ID__']) }}"
    data-read-url="{{ route('messages.read', ['message' => '__ID__']) }}"
    data-auth-url="{{ route('broadcasting.auth') }}"
    data-reverb-configured="{{ $reverbConfigured ? 'true' : 'false' }}"
    data-csrf="{{ csrf_token() }}">
    <aside class="messages-sidebar">
        <div class="messages-sidebar-head">
            <h2>Direct messages</h2>
            <input class="messages-search" type="search" id="accountSearch" placeholder="Search name, email, or role…" autocomplete="off" aria-label="Search registered accounts">
        </div>
        <div class="messages-alert messages-sidebar-alert" id="messagesSidebarAlert" role="alert" hidden></div>
        <div class="account-search-results" id="accountSearchResults" aria-live="polite" hidden></div>
        <div class="messages-list" id="conversationList" aria-live="polite">
            <div class="messages-empty">Loading your conversations…</div>
        </div>
    </aside>
    <section class="messages-main" id="messagesMain" aria-label="Conversation">
        @include('messages.partials.assistant-panel', ['embedded' => true])
        <div class="messages-notice" id="messageNotice" role="status" hidden></div>
        <header class="messages-heading" id="conversationHeading"><div class="messages-empty">Search registered accounts to start a private conversation.</div></header>
        <div class="messages-status{{ $reverbConfigured ? '' : ' offline' }}" id="realtimeStatus" role="status">
            {{ $reverbConfigured ? 'Connecting to live messaging…' : 'Live updates are off. Configure Reverb in .env, then rebuild assets and start php artisan reverb:start.' }}
        </div>
        <div class="messages-feed" id="messageFeed" aria-live="polite">
            <div class="messages-empty">Messages and attachments are private to the two participants. Messages are automatically removed after one year.</div>
            <div class="typing-row" id="typingStatus" aria-live="polite" hidden>
                <span class="message-avatar typing-avatar" id="typingAvatar" aria-hidden="true"></span>
                <span class="typing-bubble" aria-label="Typing"><span></span><span></span><span></span></span>
                <span class="typing-label" id="typingLabel">is typing</span>
            </div>
        </div>
        <div class="messages-alert" id="messagesAlert" role="alert" hidden></div>
        <div class="messages-image-preview" id="imagePreview">
            <img id="imagePreviewImage" alt="Selected image preview">
            <span id="imagePreviewName"></span>
            <button type="button" id="removeImage" aria-label="Remove selected image">&times;</button>
        </div>
        <form class="messages-compose" id="messageForm">
            <input type="file" id="attachmentInput" accept="image/jpeg,image/png,image/gif,image/webp" hidden>
            <button class="messages-attach" type="button" id="attachmentButton" aria-label="Attach image" title="Attach image">＋</button>
            <textarea id="messageBody" maxlength="5000" placeholder="Write a message… (Enter to send, Shift+Enter for a new line)" aria-label="Message" disabled></textarea>
            <button type="submit" id="sendButton" disabled>Send</button>
        </form>
    </section>
</section>
@endsection

@push('scripts')
@vite('resources/js/messaging.js')
@vite('resources/js/assistant.js')
@endpush
