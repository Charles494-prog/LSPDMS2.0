// Typing here counts as activity for idle sign-out (also inside the chat window).
import './idle-session';

const root = document.getElementById('builtInHelpChatbot');

if (root) {
    const feed = document.getElementById('assistantFeed');
    const form = document.getElementById('assistantForm');
    const input = document.getElementById('assistantInput');
    const send = document.getElementById('assistantSend');
    const error = document.getElementById('assistantError');
    let session = 0; // bumped on every reset, so a reply that arrives after the panel was closed is discarded
    let fresh = true; // the first message after a reset tells the server to forget the previous question
    const GREETING = 'Hi! I am LSPBot! I can help you find features and answer permission-approved record questions. That said though, I don\u2019t change records \u{1F642}';
    function appendMessage(role, text, link = null) {
        const bubble = document.createElement('div');
        bubble.className = `assistant-message${role === 'user' ? ' user' : ''}`;
        bubble.textContent = text;
        bubble.setAttribute('aria-label', role === 'user' ? 'Your message' : 'Assistant reply');
        if (role !== 'user' && link?.url && link?.label) {
            const anchor = document.createElement('a');
            anchor.className = 'assistant-answer-link';
            anchor.href = link.url;
            anchor.target = '_top';
            anchor.textContent = link.label;
            bubble.append(document.createElement('br'), anchor);
        }
        feed.append(bubble);
        feed.scrollTop = feed.scrollHeight;
        return bubble;
    }

    // Quick replies under an answer ("List them", "Per purok", "Did you mean…?"). Each is a string
    // or {label, message}; tapping one sends it as if typed. Only the newest set stays on screen.
    function appendSuggestions(suggestions) {
        feed.querySelectorAll('.assistant-suggestions').forEach(row => row.remove());
        if (!Array.isArray(suggestions) || suggestions.length === 0) return;
        const row = document.createElement('div');
        row.className = 'assistant-suggestions';
        suggestions.slice(0, 4).forEach(item => {
            const label = typeof item === 'string' ? item : item?.label;
            const message = typeof item === 'string' ? item : (item?.message || item?.label);
            if (!label || !message) return;
            const chip = document.createElement('button');
            chip.type = 'button';
            chip.className = 'assistant-suggestion';
            chip.textContent = label;
            chip.addEventListener('click', () => {
                if (send.disabled) return;
                input.value = message;
                form.requestSubmit();
            });
            row.append(chip);
        });
        if (row.childElementCount > 0) {
            feed.append(row);
            feed.scrollTop = feed.scrollHeight;
        }
    }

    // Same three bouncing dots the regular chats show while the other person is typing.
    function showTyping() {
        const typing = document.createElement('div');
        typing.className = 'assistant-typing';
        typing.setAttribute('aria-label', 'LSPBot is typing');
        typing.append(document.createElement('span'), document.createElement('span'), document.createElement('span'));
        feed.append(typing);
        feed.scrollTop = feed.scrollHeight;
        return typing;
    }

    form.addEventListener('submit', async event => {
        event.preventDefault();
        const text = input.value.trim();
        if (!text || send.disabled) return;

        error.hidden = true;
        error.textContent = '';
        feed.querySelectorAll('.assistant-suggestions').forEach(row => row.remove());
        const userBubble = appendMessage('user', text);
        const typing = showTyping();
        const mySession = session;
        input.value = '';
        input.disabled = true;
        send.disabled = true;

        try {
            const response = await fetch(root.dataset.endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': root.dataset.csrf,
                },
                body: JSON.stringify(fresh ? { message: text, fresh: true } : { message: text }),
            });
            const data = await response.json().catch(() => ({}));
            if (response.status === 429) {
                const wait = Number(response.headers.get('Retry-After')) || 30;
                throw new Error(`You're sending messages quickly. Please wait ${wait} second${wait === 1 ? '' : 's'} and try again.`);
            }
            if (!response.ok) {
                const validationMessage = data.errors ? Object.values(data.errors).flat()[0] : null;
                throw new Error(validationMessage || data.message || 'The assistant could not reply. Please try again.');
            }

            if (mySession !== session) return;
            fresh = false;
            typing.remove();
            appendMessage('model', data.reply, data.link);
            appendSuggestions(data.suggestions);
        } catch (requestError) {
            userBubble.remove();
            input.value = text;
            error.textContent = requestError.message || 'The assistant could not reply. Please try again.';
            error.hidden = false;
        } finally {
            typing.remove();
            input.disabled = false;
            send.disabled = false;
            input.focus();
        }
    });

    input.addEventListener('keydown', event => {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            form.requestSubmit();
        }
    });

    // LSPBot keeps no memory: whenever its panel is closed, the conversation is erased
    // and the next time it opens it starts again from the greeting.
    function resetConversation() {
        session++;
        fresh = true;
        feed.replaceChildren();
        const greeting = document.createElement('div');
        greeting.className = 'assistant-message';
        greeting.id = 'assistantGreeting';
        greeting.textContent = GREETING;
        feed.append(greeting);
        error.hidden = true;
        error.textContent = '';
        input.value = '';
        input.disabled = false;
        send.disabled = false;
    }

    const main = root.closest('.messages-main');
    let wasOpen = !root.hidden && (!main || main.classList.contains('assistant-active'));
    const syncOpenState = () => {
        const open = !root.hidden && (!main || main.classList.contains('assistant-active'));
        if (wasOpen && !open) resetConversation();
        wasOpen = open;
    };
    const observer = new MutationObserver(syncOpenState);
    observer.observe(root, { attributes: true, attributeFilter: ['hidden'] });
    if (main) observer.observe(main, { attributes: true, attributeFilter: ['class'] });
    window.addEventListener('pagehide', resetConversation);
}