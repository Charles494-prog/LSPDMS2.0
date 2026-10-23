import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

const root = document.getElementById('messagesApp');
if (root) {
    const userId = Number(root.dataset.userId);
    const csrf = root.dataset.csrf;
    const endpoint = (template, id) => template.replace('__ID__', id);
    const listElement = document.getElementById('conversationList');
    const messagesMain = document.getElementById('messagesMain');
    const assistantPanel = document.getElementById('builtInHelpChatbot');
    const searchResultsElement = document.getElementById('accountSearchResults');
    const heading = document.getElementById('conversationHeading');
    const feed = document.getElementById('messageFeed');
    const form = document.getElementById('messageForm');
    const bodyInput = document.getElementById('messageBody');
    const attachmentInput = document.getElementById('attachmentInput');
    const attachmentButton = document.getElementById('attachmentButton');
    const imagePreview = document.getElementById('imagePreview');
    const imagePreviewImage = document.getElementById('imagePreviewImage');
    const imagePreviewName = document.getElementById('imagePreviewName');
    const removeImage = document.getElementById('removeImage');
    const sendButton = document.getElementById('sendButton');
    const searchInput = document.getElementById('accountSearch');
    const alertBox = document.getElementById('messagesAlert');
    const sidebarAlertBox = document.getElementById('messagesSidebarAlert');
    const typingStatus = document.getElementById('typingStatus');
    const realtimeStatus = document.getElementById('realtimeStatus');
    const messageNotice = document.getElementById('messageNotice');
    const messagesPage = document.querySelector('.messages-page');
    const conversations = new Map();
    const subscribedConversations = new Set();
    const processedIncomingIds = new Set();
    const assistantHiddenStates = new Map();
    let selected = null;
    let assistantSelected = false;
    let typingTimer = null;
    let noticeTimer = null;
    let typingCooldown = false;
    let searchTimer = null;
    let searchRequestId = 0;
    let previewUrl = null;

    const reverbConfigured = root.dataset.reverbConfigured === 'true' && Boolean(import.meta.env.VITE_REVERB_APP_KEY);
    const echo = reverbConfigured ? new Echo({
        broadcaster: 'reverb',
        key: import.meta.env.VITE_REVERB_APP_KEY,
        wsHost: import.meta.env.VITE_REVERB_HOST || window.location.hostname,
        wsPort: Number(import.meta.env.VITE_REVERB_PORT || 8080),
        wssPort: Number(import.meta.env.VITE_REVERB_PORT || 8080),
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME || 'http') === 'https',
        enabledTransports: ['ws', 'wss'],
        authEndpoint: root.dataset.authUrl,
        auth: { headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' } },
    }) : null;

    function setRealtimeStatus(message, connected = false) {
        realtimeStatus.textContent = message;
        realtimeStatus.classList.toggle('offline', !connected);
    }

    if (echo) {
        const connection = echo.connector.pusher.connection;
        connection.bind('state_change', ({ current }) => {
            setRealtimeStatus(current === 'connected'
                ? 'Live messaging connected'
                : `Live messaging ${current.replaceAll('-', ' ')}…`, current === 'connected');
        });
        connection.bind('error', () => setRealtimeStatus('Live messaging connection failed. Check that Reverb is running.'));
    }

    async function request(url, options = {}) {
        const response = await fetch(url, {
            credentials: 'same-origin',
            ...options,
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrf,
                ...(options.body instanceof FormData ? {} : { 'Content-Type': 'application/json' }),
                ...options.headers,
            },
        });
        const data = response.status === 204 ? null : await response.json().catch(() => ({}));
        if (response.redirected || (response.status === 401 && data?.session_invalid)) {
            // The chat runs inside an iframe: send the whole window to the login page.
            (window.top || window).location.assign('/login');
            throw new Error('Your session has expired. Please sign in again.');
        }
        if (!response.ok) {
            const firstError = data.errors ? Object.values(data.errors).flat()[0] : null;
            throw new Error(firstError || data.message || 'Unable to complete that request.');
        }
        return data;
    }

    function showError(message = '') {
        alertBox.textContent = message;
        alertBox.hidden = !message;
        sidebarAlertBox.textContent = message;
        sidebarAlertBox.hidden = !message;
    }

    function showNotice(message) {
        messageNotice.textContent = message;
        messageNotice.hidden = false;
        clearTimeout(noticeTimer);
        noticeTimer = window.setTimeout(() => { messageNotice.hidden = true; }, 4500);
    }

    function displayName(account) {
        return account.name || account.email || 'Account';
    }

    function makeAvatar(name, imageUrl = null) {
        const avatar = document.createElement('span');
        avatar.className = 'message-avatar';
        const initials = document.createElement('span');
        initials.textContent = name.trim().split(/\s+/).slice(0, 2).map(part => part[0]).join('').toUpperCase() || '?';
        avatar.append(initials);
        if (imageUrl) {
            const image = document.createElement('img');
            image.src = imageUrl;
            image.alt = '';
            image.addEventListener('error', () => image.remove(), { once: true });
            avatar.append(image);
        }

        return avatar;
    }

    function renderConversationList(items) {
        conversations.clear();
        items.forEach(item => conversations.set(Number(item.id), item));
        listElement.replaceChildren();
        const bot = document.createElement('button');
        bot.className = `message-person bot-contact${assistantSelected ? ' selected' : ''}`;
        bot.type = 'button';
        bot.setAttribute('aria-label', 'Open LSPBot, your LSPDMS Guide and AI Assistant');
        bot.addEventListener('click', openAssistant);
        bot.append(makeAvatar('LSPBot', root.dataset.botAvatar));
        const botDetails = document.createElement('span');
        botDetails.className = 'message-person-body';
        const botName = document.createElement('span');
        botName.className = 'message-person-name';
        botName.textContent = 'LSPBot';
        const botPreview = document.createElement('span');
        botPreview.className = 'message-person-preview';
        botPreview.textContent = 'Your LSPDMS guide · Ask me a question';
        botDetails.append(botName, botPreview);
        const botBadge = document.createElement('span');
        botBadge.className = 'assistant-contact-badge';
        botBadge.textContent = 'BOT';
        bot.append(botDetails, botBadge);
        listElement.append(bot);
        if (!items.length) {
            const empty = document.createElement('div');
            empty.className = 'messages-empty';
            empty.textContent = 'No conversations yet. Search an account above to begin.';
            listElement.append(empty);
        }
        items.forEach(item => {
            const button = document.createElement('button');
            const suspended = !item.user.can_message;
            button.className = `message-person${Number(item.id) === selected ? ' selected' : ''}${suspended ? ' suspended' : ''}`;
            button.type = 'button';
            button.disabled = suspended;
            if (!suspended) button.addEventListener('click', () => openConversation(item.id, item.user));
            button.append(makeAvatar(displayName(item.user), item.user.avatar_url));
            const details = document.createElement('span');
            details.className = 'message-person-body';
            const name = document.createElement('span');
            name.className = 'message-person-name';
            name.textContent = displayName(item.user);
            const preview = document.createElement('span');
            preview.className = 'message-person-preview';
            preview.textContent = suspended
                ? "Can't message this user, this account has been suspended."
                : item.last_message?.body || (item.last_message?.attachment_name ? '📷 Image' : item.user.email);
            details.append(name, preview);
            button.append(details);
            if (item.unread_count) {
                const count = document.createElement('span');
                count.className = 'messages-count';
                count.textContent = item.unread_count;
                count.setAttribute('aria-label', `${item.unread_count} unread messages`);
                count.title = `${item.unread_count} unread messages`;
                button.append(count);
            }
            listElement.append(button);
        });
    }

    async function loadConversations() {
        const items = await request(root.dataset.conversationsUrl);
        renderConversationList(items);
        return items;
    }

    function openAssistant() {
        assistantSelected = true;
        selected = null;
        messagesPage.classList.add('conversation-open');
        messagesMain.classList.add('assistant-active');
        for (const child of messagesMain.children) {
            if (child !== assistantPanel) {
                if (!assistantHiddenStates.has(child)) assistantHiddenStates.set(child, child.hidden);
                child.hidden = true;
            }
        }
        assistantPanel.hidden = false;
        listElement.querySelectorAll('.message-person').forEach(button => button.classList.remove('selected'));
        listElement.querySelectorAll('.bot-contact').forEach(button => button.classList.add('selected'));
        showError();
        document.getElementById('assistantInput')?.focus();
    }

    function closeAssistant() {
        assistantSelected = false;
        messagesMain.classList.remove('assistant-active');
        assistantPanel.hidden = true;
        assistantHiddenStates.forEach((wasHidden, child) => { child.hidden = wasHidden; });
        assistantHiddenStates.clear();
        listElement.querySelectorAll('.bot-contact').forEach(button => button.classList.remove('selected'));
    }

    document.getElementById('assistantBack')?.addEventListener('click', () => {
        closeAssistant();
        messagesPage.classList.remove('conversation-open');
    });

    function appendMessage(message, { pending = false, progress = null, localImage = null } = {}) {
        if (feed.querySelector(`[data-message-id="${message.id}"]`)) return;
        const empty = feed.querySelector('.messages-empty');
        empty?.remove();
        const mine = Number(message.sender_id) === userId;
        const row = document.createElement('div');
        row.className = `message-row${mine ? ' mine' : ' incoming'}${pending ? ' pending' : ''}`;
        row.dataset.messageId = message.id;
        const bubble = document.createElement('article');
        bubble.className = 'message-bubble';
        if (message.body) {
            const text = document.createElement('p');
            text.className = 'message-text';
            text.textContent = message.body;
            bubble.append(text);
        }
        if (localImage || message.attachment_url) {
            const image = document.createElement('img');
            image.className = 'message-image';
            image.src = localImage || message.attachment_url;
            image.alt = message.attachment_name || 'Message attachment';
            image.loading = 'lazy';
            // The saved image file can be missing on this PC (for example after a restore).
            image.addEventListener('error', () => {
                const unavailable = document.createElement('span');
                unavailable.className = 'message-text';
                unavailable.textContent = 'Image unavailable';
                image.replaceWith(unavailable);
            }, { once: true });
            bubble.append(image);
        }
        const meta = document.createElement('div');
        meta.className = 'message-meta';
        const created = message.created_at ? new Date(message.created_at).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' }) : '';
        meta.dataset.created = created;
        meta.textContent = pending ? 'Sending…' : mine ? `${created}${message.read_at ? ' · Seen' : message.delivered_at ? ' · Delivered' : ' · Sent'}` : created;
        bubble.append(meta);
        if (progress !== null) {
            const progressTrack = document.createElement('div');
            progressTrack.className = 'message-progress';
            const progressBar = document.createElement('div');
            progressBar.className = 'message-progress-bar';
            progressBar.style.width = `${progress}%`;
            progressTrack.append(progressBar);
            bubble.append(progressTrack);
            row.dataset.progress = 'true';
        }
        row.append(bubble);
        feed.insertBefore(row, typingStatus);
        feed.scrollTop = feed.scrollHeight;
        return row;
    }

    function renderMessages(items) {
        feed.replaceChildren();
        feed.append(typingStatus);
        if (!items.length) {
            const empty = document.createElement('div');
            empty.className = 'messages-empty';
            empty.textContent = 'This is the beginning of your conversation.';
            feed.append(empty);
        }
        items.forEach(appendMessage);
        feed.scrollTop = feed.scrollHeight;
    }

    function setComposerEnabled(enabled) {
        bodyInput.disabled = !enabled;
        sendButton.disabled = !enabled;
        attachmentButton.disabled = !enabled;
    }

    function refreshParentUnreadCount() {
        if (window.parent !== window) {
            window.parent.postMessage({ type: 'chat:unread-refresh' }, window.location.origin);
        }
    }

    function isMessagesViewVisible() {
        if (window.parent === window) return true;
        const chatWindow = window.parent.document.getElementById('chatWindow');
        return Boolean(chatWindow && !chatWindow.hidden);
    }

    function subscribeConversation(conversationId) {
        const id = Number(conversationId);
        if (!echo || subscribedConversations.has(id)) return;

        subscribedConversations.add(id);
        const channelName = `conversation.${id}`;
        echo.private(channelName)
            .listen('.message.sent', payload => handleIncomingMessage(payload.message))
            .listen('.message.receipt', receipt => {
                if (selected !== id) return;
                const row = feed.querySelector(`[data-message-id="${receipt.id}"]`);
                const meta = row?.querySelector('.message-meta');
                if (meta && row.classList.contains('mine')) {
                    const status = receipt.read_at ? 'Seen' : receipt.delivered_at ? 'Delivered' : 'Sent';
                    meta.textContent = `${meta.dataset.created || ''} · ${status}`;
                }
            })
            .listen('.user.typing', payload => {
                if (selected !== id || Number(payload.user_id) === userId) return;
                document.getElementById('typingAvatar').textContent = makeAvatar(payload.name).textContent;
                document.getElementById('typingLabel').textContent = `${payload.name} is typing`;
                typingStatus.hidden = false;
                clearTimeout(typingTimer);
                typingTimer = window.setTimeout(() => { typingStatus.hidden = true; }, 3200);
            });
    }

    async function handleIncomingMessage(message) {
        const messageId = Number(message.id);
        if (Number(message.sender_id) === userId || processedIncomingIds.has(messageId)) return;
        processedIncomingIds.add(messageId);
        showNotice(`New message from ${message.sender_name || 'your contact'}`);

        try {
            await request(endpoint(root.dataset.deliveredUrl, message.id), { method: 'POST', body: '{}' });
            if (selected === Number(message.conversation_id)
                && isMessagesViewVisible()
                && document.visibilityState === 'visible'
                && document.hasFocus()) {
                await request(endpoint(root.dataset.readUrl, message.id), { method: 'POST', body: '{}' });
            }
        } catch (error) {
            showError(error.message);
        }

        if (selected === Number(message.conversation_id)) appendMessage(message);
        loadConversations()
            .then(items => {
                const conversation = items.find(item => Number(item.id) === Number(message.conversation_id));
                if (conversation) subscribeConversation(conversation.id);
                refreshParentUnreadCount();
            })
            .catch(error => showError(error.message));
    }

    async function openConversation(id, account) {
        if (!account.can_message) {
            showError("Can't message this user, this account has been suspended.");
            setComposerEnabled(false);
            return;
        }
        closeAssistant();
        selected = Number(id);
        messagesPage.classList.add('conversation-open');
        showError();
        subscribeConversation(selected);
        setComposerEnabled(true);
        const backButton = document.createElement('button');
        backButton.type = 'button';
        backButton.className = 'messages-back';
        backButton.setAttribute('aria-label', 'Back to conversations');
        backButton.textContent = '‹';
        backButton.addEventListener('click', () => messagesPage.classList.remove('conversation-open'));
        heading.replaceChildren(makeAvatar(displayName(account), account.avatar_url));
        const info = document.createElement('div');
        const name = document.createElement('h2');
        name.textContent = displayName(account);
        const email = document.createElement('p');
        email.textContent = account.email || '';
        info.append(name, email);
        heading.prepend(backButton);
        heading.append(info);
        feed.replaceChildren(typingStatus);
        try {
            const items = await request(endpoint(root.dataset.messagesUrl, selected));
            if (selected === Number(id)) {
                renderMessages(items);
                refreshParentUnreadCount();
            }
            await loadConversations();
        } catch (error) {
            showError(error.message);
        }
    }

    async function searchAccounts(term) {
        const requestId = ++searchRequestId;
        const accounts = await request(`${root.dataset.accountsUrl}?q=${encodeURIComponent(term)}`);
        if (requestId !== searchRequestId) return;
        searchResultsElement.replaceChildren();
        searchResultsElement.hidden = false;
        const label = document.createElement('p');
        label.className = 'account-search-label';
        label.textContent = 'Accounts';
        searchResultsElement.append(label);
        if (!accounts.length) {
            const empty = document.createElement('div');
            empty.className = 'messages-empty';
            empty.textContent = 'No other accounts matched. Try another name, email, or role.';
            searchResultsElement.append(empty);
        }
        accounts.forEach(account => {
            const button = document.createElement('button');
            const suspended = !account.can_message;
            button.className = `message-person${suspended ? ' suspended' : ''}`;
            button.type = 'button';
            button.disabled = suspended;
            if (!suspended) button.addEventListener('click', async () => {
                try {
                    const conversation = await request(root.dataset.createUrl, { method: 'POST', body: JSON.stringify({ target_user_id: account.id }) });
                    await openConversation(conversation.id, account);
                    searchInput.value = '';
                    searchRequestId++;
                    searchResultsElement.replaceChildren();
                    searchResultsElement.hidden = true;
                    await loadConversations();
                } catch (error) { showError(error.message); }
            });
            button.append(makeAvatar(displayName(account), account.avatar_url));
            const details = document.createElement('span');
            details.className = 'message-person-body';
            const name = document.createElement('span');
            name.className = 'message-person-name';
            name.textContent = displayName(account);
            const email = document.createElement('span');
            email.className = 'message-person-preview';
            email.textContent = suspended
                ? "Can't message this user, this account has been suspended."
                : [account.role, account.email].filter(Boolean).join(' · ');
            details.append(name, email);
            button.append(details);
            searchResultsElement.append(button);
        });
    }

    searchInput.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = window.setTimeout(() => searchAccounts(searchInput.value).catch(error => showError(error.message)), 250);
    });
    searchInput.addEventListener('focus', () => searchAccounts(searchInput.value).catch(error => showError(error.message)));

    attachmentButton.addEventListener('click', () => attachmentInput.click());
    bodyInput.addEventListener('input', () => {
        if (!selected || !bodyInput.value.trim() || typingCooldown) return;
        typingCooldown = true;
        request(endpoint(root.dataset.typingUrl, selected), { method: 'POST', body: '{}' })
            .catch(error => showError(error.message))
            .finally(() => window.setTimeout(() => { typingCooldown = false; }, 2200));
    });

    bodyInput.addEventListener('keydown', event => {
        if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
            event.preventDefault();
            form.requestSubmit();
        }
    });

    function clearSelectedImage() {
        if (previewUrl) URL.revokeObjectURL(previewUrl);
        previewUrl = null;
        attachmentInput.value = '';
        imagePreview.classList.remove('visible');
        imagePreviewImage.removeAttribute('src');
        imagePreviewName.textContent = '';
        attachmentButton.title = 'Attach image';
    }

    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (!selected) return;
        const content = bodyInput.value.trim();
        const file = attachmentInput.files?.[0];
        if (!content && !file) return;
        if (file && file.size > 10 * 1024 * 1024) {
            showError('Images must be 10 MB or smaller.');
            return;
        }
        sendButton.disabled = true;
        showError();
        const pendingId = `pending-${Date.now()}`;
        let pendingRow = appendMessage({
            id: pendingId,
            sender_id: userId,
            body: content || null,
            created_at: new Date().toISOString(),
        }, { pending: true, progress: file ? 0 : null, localImage: previewUrl });
        try {
            const data = new FormData();
            if (content) data.append('body', content);
            if (file) data.append('attachment', file);
            const message = await uploadMessage(endpoint(root.dataset.sendUrl, selected), data, percent => {
                const bar = pendingRow?.querySelector('.message-progress-bar');
                if (bar) bar.style.width = `${percent}%`;
            });
            const alreadyReceived = feed.querySelector(`[data-message-id="${message.id}"]`);
            pendingRow?.remove();
            pendingRow = null;
            if (!alreadyReceived) appendMessage(message);
            bodyInput.value = '';
            clearSelectedImage();
            await loadConversations();
        } catch (error) {
            pendingRow?.remove();
            showError(error.message);
        } finally {
            sendButton.disabled = bodyInput.disabled;
        }
    });

    function uploadMessage(url, formData, onProgress) {
        return new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            xhr.open('POST', url);
            xhr.withCredentials = true;
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.setRequestHeader('X-CSRF-TOKEN', csrf);
            xhr.upload.addEventListener('progress', event => {
                if (event.lengthComputable) onProgress(Math.round((event.loaded / event.total) * 100));
            });
            xhr.addEventListener('load', () => {
                let data = {};
                try { data = JSON.parse(xhr.responseText); } catch {}
                if (xhr.status >= 200 && xhr.status < 300 && data.id) resolve(data);
                else {
                    const firstError = data.errors ? Object.values(data.errors).flat()[0] : null;
                    reject(new Error(firstError || data.message || 'Unable to send the message.'));
                }
            });
            xhr.addEventListener('error', () => reject(new Error('Network error while sending the message.')));
            xhr.addEventListener('abort', () => reject(new Error('Message sending was cancelled.')));
            xhr.send(formData);
        });
    }

    attachmentInput.addEventListener('change', () => {
        const file = attachmentInput.files?.[0];
        if (!file) return;
        if (previewUrl) URL.revokeObjectURL(previewUrl);
        previewUrl = URL.createObjectURL(file);
        imagePreviewImage.src = previewUrl;
        imagePreviewName.textContent = file.name;
        imagePreview.classList.add('visible');
        attachmentButton.title = `Image selected: ${file.name}`;
    });

    removeImage.addEventListener('click', clearSelectedImage);
    window.addEventListener('message', async event => {
        if (event.origin !== window.location.origin || event.source !== window.parent) return;
        if (event.data?.type !== 'chat:panel-opened' || !selected) return;

        try {
            const items = await request(endpoint(root.dataset.messagesUrl, selected));
            renderMessages(items);
            await loadConversations();
            refreshParentUnreadCount();
        } catch (error) {
            showError(error.message);
        }
    });
    if (echo) {
        echo.private(`user.${userId}`).listen('.message.sent', payload => handleIncomingMessage(payload.message));
    }
    loadConversations().then(items => items.forEach(item => subscribeConversation(item.id))).catch(error => showError(error.message));
}
