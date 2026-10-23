{{-- To-Do tab of the chat window (each person's private list). Behaviour: resources/js/workspace.js --}}
<section class="workspace-panel ws-scroll" data-workspace-panel="todo" role="tabpanel" aria-labelledby="wsTabTodo" hidden>
    <div class="ws-column">
        <div class="ws-card ws-todo-card">
            <header class="ws-card-head">
                <div>
                    <h2>To-Do</h2>
                    <p>Only you can see this list. Click a to-do to edit it.</p>
                </div>
            </header>
            <form class="ws-todo-add" id="todoAddForm">
                <textarea id="todoAddInput" rows="1" maxlength="10000" placeholder="Add a to-do… (only you can see it)" aria-label="New to-do"></textarea>
                <label class="ws-date-btn" title="Due date (optional)">
                    <svg viewBox="0 0 24 24" class="ws-icon" aria-hidden="true"><path d="M8 3v3M16 3v3M4 9h16"/><path d="M5 5h14a1 1 0 0 1 1 1v13H4V6a1 1 0 0 1 1-1Z"/></svg>
                    <input type="date" id="todoAddDue" aria-label="Due date (optional)">
                </label>
                <button type="submit" class="ws-btn primary">Add</button>
            </form>
            <div class="ws-timeline" id="todoTimeline" hidden></div>
            <p class="ws-status" id="todoStatusLine" role="status" aria-live="polite"></p>
            <ul class="ws-todo-list" id="todoList" aria-live="polite"></ul>
        </div>
    </div>
</section>
