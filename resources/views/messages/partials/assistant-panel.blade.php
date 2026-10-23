<main class="assistant-page" id="builtInHelpChatbot"
    data-endpoint="{{ route('messages.assistant.reply') }}"
    data-csrf="{{ csrf_token() }}" @if ($embedded) hidden @endif>
    <section class="assistant-card" aria-label="LSPBot assistant chat">
        <header class="assistant-head">
            @if ($embedded)
                <button class="assistant-back" type="button" id="assistantBack" aria-label="Back to chats">&larr;</button>
            @else
                <a class="assistant-link" href="{{ route('messages.index', request()->boolean('widget') ? ['widget' => 1] : []) }}" aria-label="Back to chats">&larr;</a>
            @endif
            <div class="assistant-title">
                <span class="assistant-mark"><img src="{{ asset('images/LSP_logo_white.png') }}?v={{ filemtime(public_path('images/LSP_logo_white.png')) }}" alt=""></span>
                <div>
                    <h1>LSPBot</h1>
                    <p>Your LSPDMS Guide and AI Assistant</p>
                </div>
            </div>
        </header>
        <div class="assistant-feed" id="assistantFeed" role="log" aria-live="polite" aria-relevant="additions text">
            <div class="assistant-message" id="assistantGreeting">Hi! I am LSPBot! I can help you find features and answer permission-approved record questions. That said though, I don&rsquo;t change records 🙂</div>
        </div>
        <p class="assistant-error" id="assistantError" role="alert" hidden></p>
        <form class="assistant-compose" id="assistantForm">
            <textarea id="assistantInput" maxlength="500" rows="2" aria-label="Question for LSPBot" placeholder="Message LSPBot…" required></textarea>
           <button type="submit" id="assistantSend" aria-label="Send" title="Send"><svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path d="M9 5l7 7-7 7" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
        </form>
    </section>
</main>