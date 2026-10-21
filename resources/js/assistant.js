const root = document.getElementById('builtInHelpChatbot');

if (root) {
    const feed = document.getElementById('assistantFeed');
    const form = document.getElementById('assistantForm');
    const input = document.getElementById('assistantInput');
    const send = document.getElementById('assistantSend');
    const error = document.getElementById('assistantError');
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

    form.addEventListener('submit', async event => {
        event.preventDefault();
        const text = input.value.trim();
        if (!text || send.disabled) return;

        error.hidden = true;
        error.textContent = '';
        document.getElementById('assistantWelcome')?.remove();
        const userBubble = appendMessage('user', text);
        input.value = '';
        input.disabled = true;
        send.disabled = true;
        send.textContent = 'Looking…';

        try {
            const response = await fetch(root.dataset.endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': root.dataset.csrf,
                },
                body: JSON.stringify({ message: text }),
            });
            const data = await response.json().catch(() => ({}));
            if (!response.ok) {
                const validationMessage = data.errors ? Object.values(data.errors).flat()[0] : null;
                throw new Error(validationMessage || data.message || 'The assistant could not reply. Please try again.');
            }

            appendMessage('model', data.reply, data.link);
        } catch (requestError) {
            userBubble.remove();
            input.value = text;
            error.textContent = requestError.message || 'The assistant could not reply. Please try again.';
            error.hidden = false;
            input.focus();
        } finally {
            input.disabled = false;
            send.disabled = false;
            send.textContent = 'Send';
            input.focus();
        }
    });

    input.addEventListener('keydown', event => {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            form.requestSubmit();
        }
    });

    document.getElementById('assistantClear').addEventListener('click', () => {
        feed.replaceChildren();
        const welcome = document.createElement('p');
        welcome.className = 'assistant-welcome';
        welcome.id = 'assistantWelcome';
        const heading = document.createElement('strong');
        heading.textContent = 'Hi! I’m LSPBot! Your LSPDMS Guide and AI Assistant!';
        welcome.append(heading, 'Ask me where to find a tool, how to complete a workflow, or—if enabled—questions like “How many MS are there?” I’ll point you to the right place, but I can’t edit records.');
        feed.append(welcome);
        error.hidden = true;
        error.textContent = '';
        input.value = '';
        input.focus();
    });
}
