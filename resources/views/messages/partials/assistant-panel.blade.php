<main class="assistant-page" id="builtInHelpChatbot"
    data-endpoint="{{ route('messages.assistant.reply') }}"
    data-csrf="{{ csrf_token() }}" @if ($embedded) hidden @endif>
    <section class="assistant-card" aria-label="LSPBot assistant chat">
        <header class="assistant-head">
            <div class="assistant-title">
                <span class="assistant-mark"><img src="{{ asset('images/LSP_logo_white.png') }}?v={{ filemtime(public_path('images/LSP_logo_white.png')) }}" alt=""></span>
                <div>
                    <h1>LSPBot</h1>
                    <p>Your LSPDMS Guide and AI Assistant</p>
                </div>
            </div>
            <div class="assistant-head-actions">
                @if ($embedded)
                    <button class="assistant-back" type="button" id="assistantBack">Back to chats</button>
                @else
                    <a class="assistant-link" href="{{ route('messages.index', request()->boolean('widget') ? ['widget' => 1] : []) }}">Back to chats</a>
                @endif
                <button class="assistant-clear" type="button" id="assistantClear" aria-label="Clear this conversation">Clear</button>
            </div>
        </header>
        <aside class="assistant-disclaimer">
            <strong>Built-in LSPDMS guide — no external AI service.</strong> I can help find features and answer permission-approved record questions. I never change records.
        </aside>
        <div class="assistant-feed" id="assistantFeed" role="log" aria-live="polite" aria-relevant="additions text">
            <p class="assistant-welcome" id="assistantWelcome"><strong>Hi! I’m LSPBot! Your LSPDMS Guide and AI Assistant!</strong>Ask me where to find a tool, how to complete a workflow, or—if enabled—questions like “How many MS are there?” I’ll point you to the right place, but I can’t edit records.</p>
        </div>
        <p class="assistant-error" id="assistantError" role="alert" hidden></p>
        <form class="assistant-compose" id="assistantForm">
            <textarea id="assistantInput" maxlength="500" rows="2" aria-label="Question for LSPBot" placeholder="Message LSPBot…" required></textarea>
            <button type="submit" id="assistantSend">Send</button>
        </form>
    </section>
</main>
